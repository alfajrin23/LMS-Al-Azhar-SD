<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningDocuments\ReviewLearningDocumentRequest;
use App\Http\Requests\LearningDocuments\StoreLearningDocumentUploadRequest;
use App\Http\Requests\LearningDocuments\UpdateLearningDocumentMetadataRequest;
use App\Models\LearningDocument;
use App\Models\LearningDocumentApprovalHistory;
use App\Models\LearningDocumentRequest as DocumentRequest;
use App\Models\Pesan;
use App\Models\User;
use App\Services\LearningDocumentClassifier;
use App\Services\LearningDocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KepalaLearningDocumentController extends Controller
{
    public function __construct(
        private readonly LearningDocumentStorageService $storage,
        private readonly LearningDocumentClassifier $classifier,
    ) {
    }

    public function store(StoreLearningDocumentUploadRequest $request)
    {
        $files = collect([$request->file('file')])
            ->filter()
            ->merge($request->file('files', []));

        if ($files->isEmpty()) {
            return redirect()->back()->with('error', 'Pilih minimal satu file untuk diunggah.');
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        $singleFile = $files->count() === 1;

        foreach ($files as $file) {
            try {
                $classification = $this->classifier->classify($file->getRealPath(), $file->getClientOriginalName());
                $metadata = $this->metadataFromUpload($request, $classification, $singleFile);
                $result = $this->storage->storeUploadedFile($file, $metadata, $request->user());
                $created += $result['created'] ? 1 : 0;
                $duplicates += $result['duplicate'] ? 1 : 0;
            } catch (\Throwable $exception) {
                $errors[] = $file->getClientOriginalName().': '.$exception->getMessage();
            }
        }

        $message = 'Upload selesai. Dokumen baru: '.$created.'. Duplikat/sudah ada: '.$duplicates.'.';
        if ($errors) {
            return redirect()->back()
                ->with('error', $message.' Gagal: '.implode(' | ', $errors))
                ->withInput();
        }

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', $message);
    }

    public function update(UpdateLearningDocumentMetadataRequest $request, LearningDocument $document)
    {
        $this->storage->updateMetadata($document, $this->metadataFromRequest($request));

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', 'Metadata dokumen berhasil diperbarui.');
    }

    public function bulkUpdate(Request $request)
    {
        if (!in_array($request->user()?->role, ['kepala_sekolah', 'admin'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:learning_documents,id'],
            'document_type' => ['nullable', Rule::in(array_keys(config('learning_documents.document_types')))],
            'subject_id' => ['nullable', 'exists:mapel,id'],
            'grade_level' => ['nullable', 'string', 'max:20'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:kelas,id'],
            'semester' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'classification_status' => ['nullable', Rule::in([LearningDocument::STATUS_CLASSIFIED, LearningDocument::STATUS_NEEDS_VERIFICATION])],
            'is_active' => ['nullable', 'boolean'],
            'is_sensitive' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $metadata = array_filter($data, fn ($value, $key) => !in_array($key, ['document_ids'], true) && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH);

        LearningDocument::query()
            ->whereIn('id', $data['document_ids'])
            ->get()
            ->each(fn (LearningDocument $document) => $this->storage->updateMetadata($document, $metadata));

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', count($data['document_ids']).' dokumen berhasil diperbarui massal.');
    }

    public function approve(ReviewLearningDocumentRequest $request, DocumentRequest $learningDocumentRequest)
    {
        if ($learningDocumentRequest->status !== DocumentRequest::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Hanya permintaan pending yang dapat disetujui.');
        }

        $data = $request->validated();
        $document = LearningDocument::findOrFail($data['approved_document_id']);

        if (!$document->is_active || !$document->hasStoredFile()) {
            return redirect()->back()->with('error', 'Dokumen yang dipilih belum aktif atau belum memiliki file asli.');
        }

        DB::transaction(function () use ($request, $learningDocumentRequest, $document, $data) {
            $from = $learningDocumentRequest->status;
            $learningDocumentRequest->update([
                'status' => DocumentRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'reviewer_notes' => $data['reviewer_notes'] ?? null,
                'approved_document_id' => $document->id,
                'access_starts_at' => $data['access_starts_at'] ?? now(),
                'access_expires_at' => $data['access_expires_at'] ?? null,
                'max_downloads' => $data['max_downloads'] ?? null,
                'revoked_at' => null,
            ]);

            $this->history($learningDocumentRequest, $from, DocumentRequest::STATUS_APPROVED, $request->user()->id, $data['reviewer_notes'] ?? null);
            $this->notifyTeacher($learningDocumentRequest, 'Permintaan dokumen disetujui', 'Permintaan '.$learningDocumentRequest->request_number.' telah disetujui. Dokumen dapat diunduh sesuai masa akses.');
        });

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', 'Permintaan dokumen berhasil disetujui.');
    }

    public function reject(ReviewLearningDocumentRequest $request, DocumentRequest $learningDocumentRequest)
    {
        return $this->reviewWithNotes($request, $learningDocumentRequest, DocumentRequest::STATUS_REJECTED, 'Permintaan dokumen ditolak');
    }

    public function clarify(ReviewLearningDocumentRequest $request, DocumentRequest $learningDocumentRequest)
    {
        return $this->reviewWithNotes($request, $learningDocumentRequest, DocumentRequest::STATUS_CLARIFICATION_REQUESTED, 'Permintaan dokumen perlu klarifikasi');
    }

    public function revoke(ReviewLearningDocumentRequest $request, DocumentRequest $learningDocumentRequest)
    {
        if ($learningDocumentRequest->status !== DocumentRequest::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'Hanya akses approved yang dapat dicabut.');
        }

        DB::transaction(function () use ($request, $learningDocumentRequest) {
            $from = $learningDocumentRequest->status;
            $learningDocumentRequest->update([
                'status' => DocumentRequest::STATUS_REVOKED,
                'revoked_at' => now(),
                'reviewer_notes' => $request->validated()['reviewer_notes'] ?? $learningDocumentRequest->reviewer_notes,
            ]);

            $this->history($learningDocumentRequest, $from, DocumentRequest::STATUS_REVOKED, $request->user()->id, $request->input('reviewer_notes'));
            $this->notifyTeacher($learningDocumentRequest, 'Akses dokumen dicabut', 'Akses dokumen untuk '.$learningDocumentRequest->request_number.' telah dicabut oleh Kepala Sekolah.');
        });

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', 'Akses dokumen berhasil dicabut.');
    }

    private function reviewWithNotes(ReviewLearningDocumentRequest $request, DocumentRequest $learningDocumentRequest, string $status, string $subject)
    {
        if ($learningDocumentRequest->status !== DocumentRequest::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Hanya permintaan pending yang dapat direview.');
        }

        $data = $request->validated();

        DB::transaction(function () use ($request, $learningDocumentRequest, $status, $subject, $data) {
            $from = $learningDocumentRequest->status;
            $learningDocumentRequest->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'reviewer_notes' => $data['reviewer_notes'],
                'approved_document_id' => null,
            ]);

            $this->history($learningDocumentRequest, $from, $status, $request->user()->id, $data['reviewer_notes']);
            $this->notifyTeacher($learningDocumentRequest, $subject, 'Status permintaan '.$learningDocumentRequest->request_number.': '.$learningDocumentRequest->statusLabel().'. Catatan: '.$data['reviewer_notes']);
        });

        return redirect()->route('dashboard', ['tab' => 'dokumen_pembelajaran'])
            ->with('success', 'Permintaan berhasil direview.');
    }

    private function metadataFromUpload(StoreLearningDocumentUploadRequest $request, array $classification, bool $singleFile): array
    {
        $data = $request->validated();
        $metadata = $classification;

        foreach (['description', 'document_type', 'subject_id', 'grade_level', 'class_ids', 'semester', 'academic_year', 'classification_status', 'internal_notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $metadata[$field] = $data[$field];
            }
        }

        if ($singleFile && !empty($data['title'])) {
            $metadata['title'] = $data['title'];
        }

        $metadata['is_active'] = $request->boolean('is_active', true);
        $metadata['is_sensitive'] = $request->has('is_sensitive')
            ? $request->boolean('is_sensitive')
            : (bool) ($metadata['is_sensitive'] ?? false);

        return $metadata;
    }

    private function metadataFromRequest(UpdateLearningDocumentMetadataRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['is_sensitive'] = $request->boolean('is_sensitive');

        return $data;
    }

    private function history(DocumentRequest $request, ?string $from, string $to, ?int $actorId, ?string $notes = null): void
    {
        LearningDocumentApprovalHistory::create([
            'request_id' => $request->id,
            'previous_status' => $from,
            'new_status' => $to,
            'action_by' => $actorId,
            'notes' => $notes,
        ]);
    }

    private function notifyTeacher(DocumentRequest $request, string $subject, string $body): void
    {
        $teacherUser = $request->teacher?->user;
        if (!$teacherUser) {
            return;
        }

        Pesan::create([
            'pengirim_id' => auth()->id(),
            'penerima_id' => $teacherUser->id,
            'subjek' => $subject,
            'kategori' => 'Dokumen Pembelajaran',
            'isi' => $body,
            'tanggal' => now(),
            'dibaca' => false,
            'thread_id' => 'learning-documents-'.$request->id,
        ]);
    }
}
