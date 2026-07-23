<?php

namespace App\Http\Requests\LearningDocuments;

use App\Models\LearningDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['kepala_sekolah', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'max:'.config('learning_documents.max_upload_kb')],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.config('learning_documents.max_upload_kb')],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
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
        ];
    }
}
