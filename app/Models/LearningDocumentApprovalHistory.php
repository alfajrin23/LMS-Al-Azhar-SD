<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningDocumentApprovalHistory extends Model
{
    protected $fillable = [
        'request_id',
        'previous_status',
        'new_status',
        'action_by',
        'notes',
    ];

    public function request()
    {
        return $this->belongsTo(LearningDocumentRequest::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
