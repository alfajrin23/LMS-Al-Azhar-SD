<?php

namespace App\Services;

use App\Models\LearningDocument;
use App\Models\LearningDocumentApprovalHistory;
use App\Models\LearningDocumentCategory;
use App\Models\LearningDocumentDownload;
use App\Models\LearningDocumentRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class LearningDocumentImportService
{
    public function __construct(
        private readonly LearningDocumentClassifier $classifier,
        private readonly LearningDocumentStorageService $storage,
    ) {
    }

    public function scan(string $sourcePath): Collection
    {
        $sourcePath = $this->normalizePath($sourcePath);
        if (!is_dir($sourcePath)) {
            throw new InvalidArgumentException('Folder sumber tidak ditemukan: '.$sourcePath);
        }

        $rows = collect();
        $seen = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, config('learning_documents.allowed_extensions', []), true)) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($sourcePath))), '/');
            $checksum = hash_file('sha256', $absolutePath);
            $size = $file->getSize();
            $duplicateKey = $checksum.'|'.$size;
            $classification = $this->classifier->classify($absolutePath, $relativePath);
            $duplicateOf = $seen[$duplicateKey] ?? null;
            if ($duplicateOf) {
                $classification['internal_notes'] = trim(($classification['internal_notes'] ? $classification['internal_notes'].' ' : '')
                    .'Duplikat byte-identik dari '.$duplicateOf.'.');
                $classification['classification_status'] = LearningDocument::STATUS_NEEDS_VERIFICATION;
            }
            $seen[$duplicateKey] = $seen[$duplicateKey] ?? $relativePath;

            $archiveNote = $this->archiveStructureNote($absolutePath, $extension);
            if ($archiveNote) {
                $classification['internal_notes'] = trim(($classification['internal_notes'] ? $classification['internal_notes'].' ' : '').$archiveNote);
            }

            $rows->push([
                'absolute_path' => $absolutePath,
                'relative_path' => $relativePath,
                'filename' => $file->getFilename(),
                'extension' => $extension,
                'size' => $size,
                'checksum_sha256' => $checksum,
                'duplicate_of' => $duplicateOf,
                'classification' => $classification,
            ]);
        }

        return $rows->sortBy('relative_path')->values();
    }

    public function writeInventoryMarkdown(Collection $rows, string $outputPath): string
    {
        $outputPath = $this->absoluteProjectPath($outputPath);
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0777, true);
        }

        $classified = $rows->where('classification.classification_status', LearningDocument::STATUS_CLASSIFIED)->count();
        $needsVerification = $rows->where('classification.classification_status', LearningDocument::STATUS_NEEDS_VERIFICATION)->count();
        $duplicates = $rows->whereNotNull('duplicate_of')->count();

        $lines = [
            '# Inventaris Dokumen Pembelajaran SD',
            '',
            'Dokumen ini dibuat otomatis dari folder sumber `'.str_replace('\\', '/', config('learning_documents.source_path')).'`.',
            'File sumber tidak dikonversi, tidak dirender ulang, dan tidak diubah.',
            '',
            '## Ringkasan',
            '',
            '- Total file terbaca: '.$rows->count(),
            '- Berhasil diklasifikasikan: '.$classified,
            '- Perlu verifikasi: '.$needsVerification,
            '- Duplikat byte-identik: '.$duplicates,
            '',
            '## Tabel Inventaris',
            '',
            '| No | Nama file asli | Ekstensi | Ukuran | Lokasi file sumber | Jenis dokumen | Mata pelajaran | Kelas | Semester | Tahun ajaran | Status klasifikasi | Catatan | Checksum SHA-256 |',
            '|---:|---|---|---:|---|---|---|---|---|---|---|---|---|',
        ];

        foreach ($rows as $index => $row) {
            $classification = $row['classification'];
            $lines[] = '| '.($index + 1)
                .' | '.$this->md($row['filename'])
                .' | '.$this->md($row['extension'])
                .' | '.$this->formatSize($row['size'])
                .' | '.$this->md($row['absolute_path'])
                .' | '.$this->md($this->documentTypeLabel($classification['document_type']))
                .' | '.$this->md($classification['subject_name'] ?: 'Perlu Verifikasi')
                .' | '.$this->md($classification['grade_level'] ? 'Kelas '.$classification['grade_level'] : 'Perlu Verifikasi')
                .' | '.$this->md($classification['semester'] ?: '-')
                .' | '.$this->md($classification['academic_year'] ?: '-')
                .' | '.$this->md($classification['classification_status'] === LearningDocument::STATUS_CLASSIFIED ? 'Terverifikasi' : 'Perlu Verifikasi')
                .' | '.$this->md($classification['internal_notes'] ?: '-')
                .' | `'.$row['checksum_sha256'].'` |';
        }

        file_put_contents($outputPath, implode(PHP_EOL, $lines).PHP_EOL);

        return $outputPath;
    }

    public function import(string $sourcePath, ?User $uploader = null): array
    {
        $rows = $this->scan($sourcePath);
        $summary = [
            'total' => $rows->count(),
            'created' => 0,
            'updated_or_existing' => 0,
            'duplicates_skipped' => 0,
            'needs_verification' => 0,
            'documents' => [],
        ];

        foreach ($rows as $row) {
            if ($row['duplicate_of']) {
                $summary['duplicates_skipped']++;
                continue;
            }

            $classification = $row['classification'];
            $result = $this->storage->storeLocalFile($row['absolute_path'], [
                'title' => $classification['title'],
                'description' => $classification['description'],
                'document_type' => $classification['document_type'],
                'subject_id' => $classification['subject_id'],
                'school_level' => $classification['school_level'],
                'grade_level' => $classification['grade_level'],
                'class_ids' => $classification['class_ids'],
                'semester' => $classification['semester'],
                'academic_year' => $classification['academic_year'],
                'classification_status' => $classification['classification_status'],
                'is_sensitive' => $classification['is_sensitive'],
                'internal_notes' => $classification['internal_notes'],
                'is_active' => true,
            ], $uploader);

            if ($result['created']) {
                $summary['created']++;
            } else {
                $summary['updated_or_existing']++;
            }

            if ($classification['classification_status'] === LearningDocument::STATUS_NEEDS_VERIFICATION) {
                $summary['needs_verification']++;
            }

            $summary['documents'][] = $result['document']->id;
        }

        return $summary;
    }

    public function verify(): array
    {
        $summary = [
            'checked' => 0,
            'ok' => 0,
            'missing' => 0,
            'mismatch' => 0,
            'details' => [],
        ];

        LearningDocument::query()
            ->whereNotNull('storage_path')
            ->orderBy('id')
            ->each(function (LearningDocument $document) use (&$summary) {
                $summary['checked']++;
                $disk = $document->storage_disk ?: 'local';

                if (!Storage::disk($disk)->exists($document->storage_path)) {
                    $summary['missing']++;
                    $summary['details'][] = [$document->id, $document->original_filename, 'missing'];
                    return;
                }

                $path = Storage::disk($disk)->path($document->storage_path);
                $checksum = hash_file('sha256', $path);
                if ($checksum !== $document->checksum_sha256) {
                    $summary['mismatch']++;
                    $summary['details'][] = [$document->id, $document->original_filename, 'checksum_mismatch'];
                    return;
                }

                $summary['ok']++;
            });

        return $summary;
    }

    public function syncSqlDump(string $path): string
    {
        $absolutePath = $this->absoluteProjectPath($path);
        $existing = is_file($absolutePath) ? file_get_contents($absolutePath) : '';
        $block = $this->buildSqlSyncBlock();

        $existing = preg_replace(
            '/\R-- BEGIN LEARNING DOCUMENT CENTER SYNC.*?-- END LEARNING DOCUMENT CENTER SYNC\R/s',
            PHP_EOL,
            $existing
        ) ?? $existing;

        if (str_contains($existing, 'SET FOREIGN_KEY_CHECKS=1;')) {
            $existing = str_replace('SET FOREIGN_KEY_CHECKS=1;', $block.PHP_EOL.'SET FOREIGN_KEY_CHECKS=1;', $existing);
        } else {
            $existing = rtrim($existing).PHP_EOL.PHP_EOL.$block.PHP_EOL;
        }

        file_put_contents($absolutePath, $existing);

        return $absolutePath;
    }

    private function buildSqlSyncBlock(): string
    {
        $ddl = <<<'SQL'
-- BEGIN LEARNING DOCUMENT CENTER SYNC
DROP TABLE IF EXISTS `learning_document_approval_histories`;
DROP TABLE IF EXISTS `learning_document_downloads`;
DROP TABLE IF EXISTS `learning_document_requests`;
DROP TABLE IF EXISTS `learning_document_classes`;
DROP TABLE IF EXISTS `learning_documents`;
DROP TABLE IF EXISTS `learning_document_categories`;

CREATE TABLE `learning_document_categories` (
  `code` varchar(80) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NULL,
  `is_sensitive_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`code`),
  KEY `learning_document_categories_sensitive_index` (`is_sensitive_default`),
  KEY `learning_document_categories_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `learning_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NULL,
  `document_type` varchar(80) NOT NULL DEFAULT 'dokumen_lainnya',
  `subject_id` bigint unsigned NULL,
  `school_level` varchar(20) NOT NULL DEFAULT 'SD',
  `grade_level` varchar(20) NULL,
  `semester` varchar(20) NULL,
  `academic_year` varchar(20) NULL,
  `original_filename` varchar(255) NULL,
  `stored_filename` varchar(255) NULL,
  `file_extension` varchar(20) NULL,
  `mime_type` varchar(255) NULL,
  `file_size` bigint unsigned NULL,
  `checksum_sha256` varchar(64) NULL,
  `storage_disk` varchar(50) NOT NULL DEFAULT 'local',
  `storage_path` varchar(255) NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  `classification_status` varchar(50) NOT NULL DEFAULT 'needs_verification',
  `internal_notes` text NULL,
  `uploaded_by` bigint unsigned NULL,
  `uploaded_at` datetime NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_documents_file_unique` (`checksum_sha256`, `file_size`, `original_filename`),
  KEY `learning_documents_document_type_index` (`document_type`),
  KEY `learning_documents_subject_id_foreign` (`subject_id`),
  KEY `learning_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `learning_documents_catalog_idx` (`subject_id`, `grade_level`, `document_type`),
  KEY `learning_documents_visibility_idx` (`is_active`, `is_sensitive`, `classification_status`),
  CONSTRAINT `learning_documents_document_type_foreign` FOREIGN KEY (`document_type`) REFERENCES `learning_document_categories` (`code`),
  CONSTRAINT `learning_documents_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `mapel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `learning_document_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learning_document_id` bigint unsigned NOT NULL,
  `class_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_document_class_unique` (`learning_document_id`, `class_id`),
  KEY `learning_document_class_lookup` (`class_id`, `learning_document_id`),
  CONSTRAINT `learning_document_classes_document_foreign` FOREIGN KEY (`learning_document_id`) REFERENCES `learning_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_document_classes_class_foreign` FOREIGN KEY (`class_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `learning_document_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `learning_document_id` bigint unsigned NULL,
  `class_id` bigint unsigned NULL,
  `subject_id` bigint unsigned NULL,
  `document_type` varchar(80) NULL,
  `requested_title` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `required_at` date NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `requested_at` datetime NULL,
  `reviewed_by` bigint unsigned NULL,
  `reviewed_at` datetime NULL,
  `reviewer_notes` text NULL,
  `approved_document_id` bigint unsigned NULL,
  `access_starts_at` datetime NULL,
  `access_expires_at` datetime NULL,
  `max_downloads` int unsigned NULL,
  `download_count` int unsigned NOT NULL DEFAULT 0,
  `last_downloaded_at` datetime NULL,
  `revoked_at` datetime NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_document_requests_request_number_unique` (`request_number`),
  KEY `learning_document_requests_teacher_idx` (`teacher_id`, `status`, `created_at`),
  KEY `learning_document_requests_scope_idx` (`class_id`, `subject_id`, `document_type`),
  CONSTRAINT `learning_document_requests_teacher_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_document_requests_requested_document_foreign` FOREIGN KEY (`learning_document_id`) REFERENCES `learning_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_document_requests_class_foreign` FOREIGN KEY (`class_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_document_requests_subject_foreign` FOREIGN KEY (`subject_id`) REFERENCES `mapel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_document_requests_type_foreign` FOREIGN KEY (`document_type`) REFERENCES `learning_document_categories` (`code`) ON DELETE SET NULL,
  CONSTRAINT `learning_document_requests_reviewer_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_document_requests_approved_document_foreign` FOREIGN KEY (`approved_document_id`) REFERENCES `learning_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `learning_document_downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learning_document_id` bigint unsigned NOT NULL,
  `request_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `downloaded_at` datetime NOT NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` text NULL,
  `checksum_at_download` varchar(64) NOT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `learning_document_downloads_user_idx` (`user_id`, `downloaded_at`),
  KEY `learning_document_downloads_document_idx` (`learning_document_id`, `downloaded_at`),
  CONSTRAINT `learning_document_downloads_document_foreign` FOREIGN KEY (`learning_document_id`) REFERENCES `learning_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_document_downloads_request_foreign` FOREIGN KEY (`request_id`) REFERENCES `learning_document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_document_downloads_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `learning_document_approval_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `previous_status` varchar(50) NULL,
  `new_status` varchar(50) NOT NULL,
  `action_by` bigint unsigned NULL,
  `notes` text NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `learning_document_history_request_idx` (`request_id`, `created_at`),
  KEY `learning_document_history_actor_idx` (`action_by`, `new_status`),
  CONSTRAINT `learning_document_histories_request_foreign` FOREIGN KEY (`request_id`) REFERENCES `learning_document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `learning_document_histories_actor_foreign` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $tables = [
            'learning_document_categories' => ['code', 'name', 'description', 'is_sensitive_default', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'learning_documents' => ['id', 'title', 'description', 'document_type', 'subject_id', 'school_level', 'grade_level', 'semester', 'academic_year', 'original_filename', 'stored_filename', 'file_extension', 'mime_type', 'file_size', 'checksum_sha256', 'storage_disk', 'storage_path', 'is_active', 'is_sensitive', 'classification_status', 'internal_notes', 'uploaded_by', 'uploaded_at', 'created_at', 'updated_at', 'deleted_at'],
            'learning_document_classes' => ['id', 'learning_document_id', 'class_id', 'created_at', 'updated_at'],
            'learning_document_requests' => ['id', 'request_number', 'teacher_id', 'learning_document_id', 'class_id', 'subject_id', 'document_type', 'requested_title', 'purpose', 'required_at', 'status', 'requested_at', 'reviewed_by', 'reviewed_at', 'reviewer_notes', 'approved_document_id', 'access_starts_at', 'access_expires_at', 'max_downloads', 'download_count', 'last_downloaded_at', 'revoked_at', 'created_at', 'updated_at'],
            'learning_document_downloads' => ['id', 'learning_document_id', 'request_id', 'user_id', 'downloaded_at', 'ip_address', 'user_agent', 'checksum_at_download', 'created_at', 'updated_at'],
            'learning_document_approval_histories' => ['id', 'request_id', 'previous_status', 'new_status', 'action_by', 'notes', 'created_at', 'updated_at'],
        ];

        $sql = [$ddl];
        foreach ($tables as $table => $columns) {
            $rows = DB::table($table)->orderBy($columns[0])->get($columns);
            $sql[] = $this->insertStatement($table, $columns, $rows);
        }
        $sql[] = '-- END LEARNING DOCUMENT CENTER SYNC';

        return implode(PHP_EOL.PHP_EOL, array_filter($sql));
    }

    private function insertStatement(string $table, array $columns, Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $columnList = implode(', ', array_map(fn ($column) => '`'.$column.'`', $columns));
        $values = $rows->map(function ($row) use ($columns) {
            return '('.implode(', ', array_map(fn ($column) => $this->sqlValue($row->{$column}), $columns)).')';
        })->implode(','.PHP_EOL);

        return 'INSERT INTO `'.$table.'` ('.$columnList.') VALUES'.PHP_EOL.$values.';';
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? "'1'" : "'0'";
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }

    private function archiveStructureNote(string $path, string $extension): ?string
    {
        if ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return 'Struktur ZIP tidak dapat dibaca; file tetap disimpan sebagai arsip final.';
            }

            $entries = [];
            for ($i = 0; $i < min($zip->numFiles, 20); $i++) {
                $entry = $zip->statIndex($i);
                if ($entry && isset($entry['name'])) {
                    $entries[] = $entry['name'];
                }
            }
            $count = $zip->numFiles;
            $zip->close();

            return 'Struktur ZIP terbaca: '.implode(', ', $entries).($count > 20 ? ' dan '.($count - 20).' entri lain.' : '.');
        }

        if ($extension === 'rar') {
            return 'RAR disimpan sebagai file final; struktur internal memerlukan ekstensi RAR eksternal bila ingin diaudit rinci.';
        }

        return null;
    }

    private function documentTypeLabel(string $type): string
    {
        return config('learning_documents.document_types.'.$type.'.label')
            ?? str($type)->replace('_', ' ')->title()->toString();
    }

    private function formatSize(int $bytes): string
    {
        $size = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return number_format($size, $unit === 'B' ? 0 : 2).' '.$unit;
            }
            $size /= 1024;
        }

        return number_format($size, 2).' GB';
    }

    private function md(string $value): string
    {
        return str_replace('|', '\|', str_replace(["\r", "\n"], ' ', $value));
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function absoluteProjectPath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
