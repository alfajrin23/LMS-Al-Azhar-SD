<?php

namespace App\Http\Requests\LearningDocuments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningDocumentAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'guru' && $this->user()?->guru?->status === 'aktif';
    }

    public function rules(): array
    {
        return [
            'learning_document_id' => ['nullable', 'exists:learning_documents,id'],
            'class_id' => ['required', 'exists:kelas,id'],
            'subject_id' => ['required', 'exists:mapel,id'],
            'document_type' => ['required', Rule::in(array_keys(config('learning_documents.document_types')))],
            'requested_title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:4000'],
            'required_at' => ['nullable', 'date'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'submit_action' => ['nullable', Rule::in(['draft', 'pending'])],
        ];
    }
}
