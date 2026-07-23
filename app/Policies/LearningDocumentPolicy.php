<?php

namespace App\Policies;

use App\Models\LearningDocument;
use App\Models\LearningDocumentRequest;
use App\Models\User;

class LearningDocumentPolicy
{
    public function manage(User $user): bool
    {
        return in_array($user->role, ['kepala_sekolah', 'admin'], true);
    }

    public function viewCatalog(User $user, LearningDocument $document): bool
    {
        if ($this->manage($user)) {
            return true;
        }

        if ($user->role !== 'guru') {
            return false;
        }

        return $document->school_level === 'SD'
            && $document->is_active
            && !$document->is_sensitive
            && $document->classification_status === LearningDocument::STATUS_CLASSIFIED;
    }

    public function download(User $user, LearningDocument $document, ?LearningDocumentRequest $request = null): bool
    {
        if ($this->manage($user)) {
            return $document->is_active && $document->hasStoredFile();
        }

        if ($user->role !== 'guru' || !$user->guru || $user->guru->status !== 'aktif') {
            return false;
        }

        if (!$document->is_active || $document->school_level !== 'SD' || !$document->hasStoredFile()) {
            return false;
        }

        if ($request) {
            return $this->requestAuthorizesDocument($user, $document, $request);
        }

        return LearningDocumentRequest::query()
            ->where('teacher_id', $user->guru->id)
            ->where('approved_document_id', $document->id)
            ->where('status', LearningDocumentRequest::STATUS_APPROVED)
            ->whereNull('revoked_at')
            ->get()
            ->contains(fn (LearningDocumentRequest $candidate) => $candidate->isAccessibleNow());
    }

    private function requestAuthorizesDocument(User $user, LearningDocument $document, LearningDocumentRequest $request): bool
    {
        return $request->teacher_id === $user->guru?->id
            && (int) $request->approved_document_id === (int) $document->id
            && $request->isAccessibleNow();
    }
}
