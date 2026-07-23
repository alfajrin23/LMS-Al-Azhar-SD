<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningDocuments\StoreLearningDocumentAccessRequest;
use App\Models\Kelas;
use App\Models\LearningDocument;
use App\Models\LearningDocumentApprovalHistory;
use App\Models\LearningDocumentRequest as DocumentRequest;
use App\Models\Pesan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GuruLearningDocumentController extends Controller
{
    public function store(StoreLearningDocumentAccessRequest $request)
    {
        $guru = $this->currentGuru($request);
        $data = $request->validated();

        if (!$this->canRequestScope($guru, (int) $data['class_id'], (int) $data['subject_id'])) {
            return redirect()->back()->with('error', 'Kelas atau mapel tidak sesuai dengan penugasan mengajar SD Anda.')->withInput();
        }

        if (!empty($data['learning_document_id'])) {
            $document = LearningDocument::findOrFail($data['learning_document_id']);
            Gate::authorize('viewCatalog', $document);
        }

        $status = ($data['submit_action'] ?? 'pending') === DocumentRequest::STATUS_DRAFT
            ? DocumentRequest::STATUS_DRAFT
            : DocumentRequest::STATUS_PENDING;

        $documentRequest = DB::transaction(function () use ($request, $guru, $data, $status) {
            $documentRequest = DocumentRequest::create([
                'request_number' => $this->nextRequestNumber(),
                'teacher_id' => $guru->id,
                'learning_document_id' => $data['learning_document_id'] ?? null,
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'document_type' => $data['document_type'],
                'requested_title' => $data['requested_title'],
                'purpose' => $this->purposeWithNotes($data),
                'required_at' => $data['required_at'] ?? null,
                'status' => $status,
                'requested_at' => $status === DocumentRequest::STATUS_PENDING ? now() : null,
            ]);

            LearningDocumentApprovalHistory::create([
                'request_id' => $documentRequest->id,
                'previous_status' => null,
                'new_status' => $status,
                'action_by' => $request->user()->id,
                'notes' => $status === DocumentRequest::STATUS_PENDING ? 'Permintaan dikirim guru.' : 'Draft permintaan disimpan.',
            ]);

            if ($status === DocumentRequest::STATUS_PENDING) {
                $this->notifyKepala($request->user()->id, $documentRequest);
            }

            return $documentRequest;
        });

        return redirect()->route('dashboard', ['tab' => 'permintaan_dokumen'])
            ->with('success', $documentRequest->status === DocumentRequest::STATUS_PENDING
                ? 'Permintaan dokumen berhasil dikirim.'
                : 'Draft permintaan dokumen berhasil disimpan.');
    }

    public function update(StoreLearningDocumentAccessRequest $request, DocumentRequest $learningDocumentRequest)
    {
        $guru = $this->currentGuru($request);
        if ($learningDocumentRequest->teacher_id !== $guru->id) {
            abort(403);
        }

        if (!in_array($learningDocumentRequest->status, [DocumentRequest::STATUS_DRAFT, DocumentRequest::STATUS_CLARIFICATION_REQUESTED], true)) {
            return redirect()->back()->with('error', 'Hanya draft atau permintaan yang diminta klarifikasi yang dapat diperbaiki.');
        }

        $data = $request->validated();
        if (!$this->canRequestScope($guru, (int) $data['class_id'], (int) $data['subject_id'])) {
            return redirect()->back()->with('error', 'Kelas atau mapel tidak sesuai dengan penugasan mengajar SD Anda.')->withInput();
        }

        DB::transaction(function () use ($request, $learningDocumentRequest, $data) {
            $from = $learningDocumentRequest->status;
            $status = ($data['submit_action'] ?? 'pending') === DocumentRequest::STATUS_DRAFT
                ? DocumentRequest::STATUS_DRAFT
                : DocumentRequest::STATUS_PENDING;

            $learningDocumentRequest->update([
                'learning_document_id' => $data['learning_document_id'] ?? null,
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'document_type' => $data['document_type'],
                'requested_title' => $data['requested_title'],
                'purpose' => $this->purposeWithNotes($data),
                'required_at' => $data['required_at'] ?? null,
                'status' => $status,
                'requested_at' => $status === DocumentRequest::STATUS_PENDING ? now() : null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'reviewer_notes' => null,
                'approved_document_id' => null,
            ]);

            LearningDocumentApprovalHistory::create([
                'request_id' => $learningDocumentRequest->id,
                'previous_status' => $from,
                'new_status' => $status,
                'action_by' => $request->user()->id,
                'notes' => $status === DocumentRequest::STATUS_PENDING ? 'Permintaan diperbaiki dan dikirim ulang.' : 'Permintaan diperbarui sebagai draft.',
            ]);

            if ($status === DocumentRequest::STATUS_PENDING) {
                $this->notifyKepala($request->user()->id, $learningDocumentRequest);
            }
        });

        return redirect()->route('dashboard', ['tab' => 'permintaan_dokumen'])
            ->with('success', 'Permintaan dokumen berhasil diperbarui.');
    }

    public function submit(Request $request, DocumentRequest $learningDocumentRequest)
    {
        $guru = $this->currentGuru($request);
        if ($learningDocumentRequest->teacher_id !== $guru->id) {
            abort(403);
        }

        if (!in_array($learningDocumentRequest->status, [DocumentRequest::STATUS_DRAFT, DocumentRequest::STATUS_CLARIFICATION_REQUESTED], true)) {
            return redirect()->back()->with('error', 'Status permintaan tidak dapat dikirim.');
        }

        DB::transaction(function () use ($request, $learningDocumentRequest) {
            $from = $learningDocumentRequest->status;
            $learningDocumentRequest->update([
                'status' => DocumentRequest::STATUS_PENDING,
                'requested_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'reviewer_notes' => null,
            ]);

            LearningDocumentApprovalHistory::create([
                'request_id' => $learningDocumentRequest->id,
                'previous_status' => $from,
                'new_status' => DocumentRequest::STATUS_PENDING,
                'action_by' => $request->user()->id,
                'notes' => 'Permintaan dikirim guru.',
            ]);

            $this->notifyKepala($request->user()->id, $learningDocumentRequest);
        });

        return redirect()->route('dashboard', ['tab' => 'permintaan_dokumen'])
            ->with('success', 'Permintaan dokumen berhasil dikirim.');
    }

    public function cancel(Request $request, DocumentRequest $learningDocumentRequest)
    {
        $guru = $this->currentGuru($request);
        if ($learningDocumentRequest->teacher_id !== $guru->id) {
            abort(403);
        }

        if ($learningDocumentRequest->status !== DocumentRequest::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Hanya permintaan pending yang dapat dibatalkan.');
        }

        DB::transaction(function () use ($request, $learningDocumentRequest) {
            $from = $learningDocumentRequest->status;
            $learningDocumentRequest->update([
                'status' => DocumentRequest::STATUS_DRAFT,
                'requested_at' => null,
            ]);

            LearningDocumentApprovalHistory::create([
                'request_id' => $learningDocumentRequest->id,
                'previous_status' => $from,
                'new_status' => DocumentRequest::STATUS_DRAFT,
                'action_by' => $request->user()->id,
                'notes' => 'Permintaan pending dibatalkan guru.',
            ]);
        });

        return redirect()->route('dashboard', ['tab' => 'permintaan_dokumen'])
            ->with('success', 'Permintaan dokumen dibatalkan dan kembali menjadi draft.');
    }

    private function canRequestScope($guru, int $classId, int $subjectId): bool
    {
        $kelas = Kelas::query()->find($classId);
        if (!$kelas || $kelas->jenjang !== 'SD') {
            return false;
        }

        return $this->guruTeaches($guru, $classId, $subjectId)
            || (int) $kelas->guru_id === (int) $guru->id;
    }

    private function nextRequestNumber(): string
    {
        do {
            $number = 'DOC-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (DocumentRequest::query()->where('request_number', $number)->exists());

        return $number;
    }

    private function purposeWithNotes(array $data): string
    {
        $purpose = $data['purpose'];
        if (!empty($data['additional_notes'])) {
            $purpose .= "\n\nCatatan tambahan: ".$data['additional_notes'];
        }

        return $purpose;
    }

    private function notifyKepala(int $senderId, DocumentRequest $documentRequest): void
    {
        User::query()
            ->where('role', 'kepala_sekolah')
            ->get()
            ->each(function (User $kepala) use ($senderId, $documentRequest) {
                Pesan::create([
                    'pengirim_id' => $senderId,
                    'penerima_id' => $kepala->id,
                    'subjek' => 'Permintaan dokumen baru',
                    'kategori' => 'Dokumen Pembelajaran',
                    'isi' => 'Guru mengirim permintaan '.$documentRequest->request_number.' untuk '.$documentRequest->requested_title.'.',
                    'tanggal' => now(),
                    'dibaca' => false,
                    'thread_id' => 'learning-documents-'.$documentRequest->id,
                ]);
            });
    }
}
