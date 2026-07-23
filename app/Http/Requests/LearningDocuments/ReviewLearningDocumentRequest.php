<?php

namespace App\Http\Requests\LearningDocuments;

use Illuminate\Foundation\Http\FormRequest;

class ReviewLearningDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kepala_sekolah', 'admin'], true);
    }

    public function rules(): array
    {
        if ($this->routeIs('kepala.documents.requests.approve')) {
            return [
                'approved_document_id' => ['required', 'exists:learning_documents,id'],
                'reviewer_notes' => ['nullable', 'string', 'max:4000'],
                'access_starts_at' => ['nullable', 'date'],
                'access_expires_at' => ['nullable', 'date', 'after_or_equal:access_starts_at'],
                'max_downloads' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ];
        }

        if ($this->routeIs('kepala.documents.requests.reject')) {
            return [
                'reviewer_notes' => ['required', 'string', 'max:4000'],
            ];
        }

        if ($this->routeIs('kepala.documents.requests.clarify')) {
            return [
                'reviewer_notes' => ['required', 'string', 'max:4000'],
            ];
        }

        return [
            'reviewer_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
