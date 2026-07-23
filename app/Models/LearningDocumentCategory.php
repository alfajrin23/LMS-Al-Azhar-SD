<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningDocumentCategory extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_sensitive_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_sensitive_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function documents()
    {
        return $this->hasMany(LearningDocument::class, 'document_type', 'code');
    }
}
