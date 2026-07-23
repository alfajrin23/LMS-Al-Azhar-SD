<?php

namespace App\Services;

use App\Models\LearningDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LearningDocumentStorageService
{
    public function storeUploadedFile(UploadedFile $file, array $metadata, ?User $user = null): array
    {
        return $this->storeFile(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $metadata,
            $user
        );
    }

    public function storeLocalFile(string $sourcePath, array $metadata, ?User $user = null): array
    {
        return $this->storeFile($sourcePath, basename($sourcePath), $metadata, $user);
    }

    public function updateMetadata(LearningDocument $document, array $metadata): LearningDocument
    {
        return DB::transaction(function () use ($document, $metadata) {
            $document->update($this->documentAttributes($metadata, $document->toArray()));

            if (array_key_exists('class_ids', $metadata)) {
                $document->classes()->sync(array_filter((array) $metadata['class_ids']));
            }

            return $document->refresh();
        });
    }

    private function storeFile(string $sourcePath, string $originalFilename, array $metadata, ?User $user): array
    {
        if (!is_file($sourcePath)) {
            throw ValidationException::withMessages(['file' => 'File sumber tidak ditemukan.']);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $this->ensureExtensionAllowed($extension);

        $mimeType = $this->detectMimeType($sourcePath);
        $this->ensureMimeAllowed($mimeType, $extension);

        $fileSize = filesize($sourcePath);
        $checksum = hash_file('sha256', $sourcePath);

        return DB::transaction(function () use ($sourcePath, $originalFilename, $metadata, $user, $extension, $mimeType, $fileSize, $checksum) {
            $duplicate = LearningDocument::withTrashed()
                ->where('checksum_sha256', $checksum)
                ->where('file_size', $fileSize)
                ->first();

            if ($duplicate) {
                if ($duplicate->trashed()) {
                    $duplicate->restore();
                }

                $notes = trim(($duplicate->internal_notes ? $duplicate->internal_notes.' ' : '')
                    .'Duplikat terdeteksi dari file '.$originalFilename.'; file fisik tidak disalin ulang.');

                $duplicate->update(array_merge(
                    $this->documentAttributes($metadata, $duplicate->toArray()),
                    ['internal_notes' => $notes]
                ));

                if (!empty($metadata['class_ids'])) {
                    $duplicate->classes()->syncWithoutDetaching(array_filter((array) $metadata['class_ids']));
                }

                return ['document' => $duplicate->refresh(), 'created' => false, 'duplicate' => true];
            }

            $disk = $metadata['storage_disk'] ?? 'local';
            $storedFilename = $this->storedFilename($checksum, $originalFilename);
            $directory = trim(config('learning_documents.storage_directory', 'dokumen-pembelajaran'), '/');
            $storagePath = $directory.'/'.now()->format('Y/m').'/'.$storedFilename;

            $stream = fopen($sourcePath, 'rb');
            Storage::disk($disk)->put($storagePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $storedAbsolutePath = Storage::disk($disk)->path($storagePath);
            $storedChecksum = is_file($storedAbsolutePath) ? hash_file('sha256', $storedAbsolutePath) : null;
            if ($storedChecksum !== $checksum) {
                Storage::disk($disk)->delete($storagePath);
                throw new RuntimeException('Checksum file tersimpan tidak sama dengan file sumber.');
            }

            $document = LearningDocument::create(array_merge(
                $this->documentAttributes($metadata),
                [
                    'original_filename' => $originalFilename,
                    'stored_filename' => $storedFilename,
                    'file_extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'checksum_sha256' => $checksum,
                    'storage_disk' => $disk,
                    'storage_path' => $storagePath,
                    'uploaded_by' => $user?->id,
                    'uploaded_at' => now(),
                ]
            ));

            if (!empty($metadata['class_ids'])) {
                $document->classes()->sync(array_filter((array) $metadata['class_ids']));
            }

            return ['document' => $document->refresh(), 'created' => true, 'duplicate' => false];
        });
    }

    private function documentAttributes(array $metadata, array $fallback = []): array
    {
        $documentType = $metadata['document_type'] ?? Arr::get($fallback, 'document_type', 'dokumen_lainnya');

        return [
            'title' => $metadata['title'] ?? Arr::get($fallback, 'title', 'Dokumen Pembelajaran'),
            'description' => $metadata['description'] ?? Arr::get($fallback, 'description'),
            'document_type' => $documentType,
            'subject_id' => $metadata['subject_id'] ?? Arr::get($fallback, 'subject_id'),
            'school_level' => $metadata['school_level'] ?? Arr::get($fallback, 'school_level', 'SD'),
            'grade_level' => $metadata['grade_level'] ?? Arr::get($fallback, 'grade_level'),
            'semester' => $metadata['semester'] ?? Arr::get($fallback, 'semester'),
            'academic_year' => $metadata['academic_year'] ?? Arr::get($fallback, 'academic_year'),
            'is_active' => array_key_exists('is_active', $metadata)
                ? (bool) $metadata['is_active']
                : (bool) Arr::get($fallback, 'is_active', true),
            'is_sensitive' => array_key_exists('is_sensitive', $metadata)
                ? (bool) $metadata['is_sensitive']
                : (bool) (config('learning_documents.document_types.'.$documentType.'.sensitive') ?? Arr::get($fallback, 'is_sensitive', false)),
            'classification_status' => $metadata['classification_status'] ?? Arr::get($fallback, 'classification_status', LearningDocument::STATUS_NEEDS_VERIFICATION),
            'internal_notes' => $metadata['internal_notes'] ?? Arr::get($fallback, 'internal_notes'),
        ];
    }

    private function ensureExtensionAllowed(string $extension): void
    {
        if (!in_array($extension, config('learning_documents.allowed_extensions', []), true)) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi .'.$extension.' tidak diizinkan untuk dokumen pembelajaran.',
            ]);
        }
    }

    private function ensureMimeAllowed(string $mimeType, string $extension): void
    {
        $prefixes = config('learning_documents.allowed_mime_prefixes', []);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return;
            }
        }

        $lenientLocalMimes = ['application/octet-stream', 'text/plain', 'application/xml', 'text/xml'];
        if (app()->environment(['local', 'testing']) && in_array($mimeType, $lenientLocalMimes, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => 'MIME type '.$mimeType.' tidak cocok untuk ekstensi .'.$extension.'.',
        ]);
    }

    private function detectMimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($path) ?: 'application/octet-stream';
    }

    private function storedFilename(string $checksum, string $originalFilename): string
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeBase = str($base)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();

        return substr($checksum, 0, 16).'-'.($safeBase ?: 'dokumen').'.'.$extension;
    }
}
