<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningDocumentRequest extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLARIFICATION_REQUESTED = 'clarification_requested';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'request_number',
        'teacher_id',
        'learning_document_id',
        'class_id',
        'subject_id',
        'document_type',
        'requested_title',
        'purpose',
        'required_at',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
        'approved_document_id',
        'access_starts_at',
        'access_expires_at',
        'max_downloads',
        'download_count',
        'last_downloaded_at',
        'revoked_at',
    ];

    protected $casts = [
        'required_at' => 'date',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'access_starts_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'revoked_at' => 'datetime',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
    ];

    public function teacher()
    {
        return $this->belongsTo(Guru::class, 'teacher_id');
    }

    public function requestedDocument()
    {
        return $this->belongsTo(LearningDocument::class, 'learning_document_id');
    }

    public function approvedDocument()
    {
        return $this->belongsTo(LearningDocument::class, 'approved_document_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Mapel::class, 'subject_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function downloads()
    {
        return $this->hasMany(LearningDocumentDownload::class, 'request_id');
    }

    public function approvalHistories()
    {
        return $this->hasMany(LearningDocumentApprovalHistory::class, 'request_id');
    }

    public function isAccessibleNow(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || $this->revoked_at) {
            return false;
        }

        if ($this->access_starts_at && now()->lt($this->access_starts_at)) {
            return false;
        }

        if ($this->access_expires_at && now()->gt($this->access_expires_at)) {
            return false;
        }

        if ($this->max_downloads !== null && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return (bool) $this->approved_document_id;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CLARIFICATION_REQUESTED => 'Perlu Klarifikasi',
            self::STATUS_REVOKED => 'Revoked',
            self::STATUS_EXPIRED => 'Expired',
            default => str($this->status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function documentTypeLabel(): string
    {
        return config('learning_documents.document_types.'.$this->document_type.'.label')
            ?? str((string) $this->document_type)->replace('_', ' ')->title()->toString();
    }
}
