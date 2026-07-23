<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningDocumentDownload extends Model
{
    protected $fillable = [
        'learning_document_id',
        'request_id',
        'user_id',
        'downloaded_at',
        'ip_address',
        'user_agent',
        'checksum_at_download',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(LearningDocument::class, 'learning_document_id');
    }

    public function request()
    {
        return $this->belongsTo(LearningDocumentRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
