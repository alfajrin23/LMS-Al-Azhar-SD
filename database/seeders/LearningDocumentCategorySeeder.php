<?php

namespace Database\Seeders;

use App\Models\LearningDocumentCategory;
use Illuminate\Database\Seeder;

class LearningDocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $order = 1;
        foreach (config('learning_documents.document_types') as $code => $meta) {
            LearningDocumentCategory::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $meta['label'],
                    'description' => 'Kategori dokumen pembelajaran: '.$meta['label'],
                    'is_sensitive_default' => (bool) ($meta['sensitive'] ?? false),
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );
        }
    }
}
