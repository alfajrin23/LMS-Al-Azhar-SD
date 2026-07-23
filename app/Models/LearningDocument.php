<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LearningDocument extends Model
{
    use SoftDeletes;

    public const STATUS_CLASSIFIED = 'classified';
    public const STATUS_NEEDS_VERIFICATION = 'needs_verification';

    protected $fillable = [
        'title',
        'description',
        'document_type',
        'subject_id',
        'school_level',
        'grade_level',
        'semester',
        'academic_year',
        'original_filename',
        'stored_filename',
        'file_extension',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'is_active',
        'is_sensitive',
        'classification_status',
        'internal_notes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_sensitive' => 'boolean',
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(LearningDocumentCategory::class, 'document_type', 'code');
    }

    public function subject()
    {
        return $this->belongsTo(Mapel::class, 'subject_id');
    }

    public function classes()
    {
        return $this->belongsToMany(Kelas::class, 'learning_document_classes', 'learning_document_id', 'class_id')
            ->withTimestamps();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function requests()
    {
        return $this->hasMany(LearningDocumentRequest::class);
    }

    public function approvedRequests()
    {
        return $this->hasMany(LearningDocumentRequest::class, 'approved_document_id');
    }

    public function downloads()
    {
        return $this->hasMany(LearningDocumentDownload::class);
    }

    public function scopeVisibleInTeacherCatalog($query)
    {
        return $query->where('is_active', true)
            ->where('is_sensitive', false)
            ->where('classification_status', self::STATUS_CLASSIFIED);
    }

    public function scopeNeedsVerification($query)
    {
        return $query->where('classification_status', self::STATUS_NEEDS_VERIFICATION);
    }

    public function hasStoredFile(): bool
    {
        return (bool) $this->storage_path
            && Storage::disk($this->storage_disk ?: 'local')->exists($this->storage_path);
    }

    public function documentTypeLabel(): string
    {
        return config('learning_documents.document_types.'.$this->document_type.'.label')
            ?? $this->category?->name
            ?? str($this->document_type)->replace('_', ' ')->title()->toString();
    }

    public function classificationStatusLabel(): string
    {
        return match ($this->classification_status) {
            self::STATUS_CLASSIFIED => 'Terverifikasi',
            self::STATUS_NEEDS_VERIFICATION => 'Perlu Verifikasi',
            default => str($this->classification_status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function fileSizeLabel(): string
    {
        if ($this->file_size === null) {
            return '-';
        }

        $size = (float) $this->file_size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return number_format($size, $unit === 'B' ? 0 : 2).' '.$unit;
            }
            $size /= 1024;
        }

        return number_format($size, 2).' GB';
    }
}
