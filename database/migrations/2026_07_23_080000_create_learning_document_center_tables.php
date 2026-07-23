<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_document_categories', function (Blueprint $table) {
            $table->string('code', 80)->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_sensitive_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('document_type', 80)->default('dokumen_lainnya')->index();
            $table->foreignId('subject_id')->nullable()->constrained('mapel')->nullOnDelete();
            $table->string('school_level', 20)->default('SD')->index();
            $table->string('grade_level', 20)->nullable()->index();
            $table->string('semester', 20)->nullable()->index();
            $table->string('academic_year', 20)->nullable()->index();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('file_extension', 20)->nullable()->index();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->index();
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_sensitive')->default(false)->index();
            $table->string('classification_status', 50)->default('needs_verification')->index();
            $table->text('internal_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['checksum_sha256', 'file_size', 'original_filename'], 'learning_documents_file_unique');
            $table->index(['subject_id', 'grade_level', 'document_type'], 'learning_documents_catalog_idx');
            $table->index(['is_active', 'is_sensitive', 'classification_status'], 'learning_documents_visibility_idx');
            $table->foreign('document_type')->references('code')->on('learning_document_categories')->restrictOnDelete();
        });

        Schema::create('learning_document_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_document_id')->constrained('learning_documents')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('kelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_document_id', 'class_id'], 'learning_document_class_unique');
            $table->index(['class_id', 'learning_document_id'], 'learning_document_class_lookup');
        });

        Schema::create('learning_document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('teacher_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('learning_document_id')->nullable()->constrained('learning_documents')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('mapel')->nullOnDelete();
            $table->string('document_type', 80)->nullable()->index();
            $table->string('requested_title');
            $table->text('purpose');
            $table->date('required_at')->nullable()->index();
            $table->string('status', 50)->default('draft')->index();
            $table->timestamp('requested_at')->nullable()->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('approved_document_id')->nullable()->constrained('learning_documents')->nullOnDelete();
            $table->timestamp('access_starts_at')->nullable();
            $table->timestamp('access_expires_at')->nullable()->index();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['teacher_id', 'status', 'created_at'], 'learning_document_requests_teacher_idx');
            $table->index(['class_id', 'subject_id', 'document_type'], 'learning_document_requests_scope_idx');
            $table->foreign('document_type')->references('code')->on('learning_document_categories')->nullOnDelete();
        });

        Schema::create('learning_document_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_document_id')->constrained('learning_documents')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('learning_document_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('downloaded_at')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('checksum_at_download', 64);
            $table->timestamps();

            $table->index(['user_id', 'downloaded_at'], 'learning_document_downloads_user_idx');
            $table->index(['learning_document_id', 'downloaded_at'], 'learning_document_downloads_document_idx');
        });

        Schema::create('learning_document_approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('learning_document_requests')->cascadeOnDelete();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'created_at'], 'learning_document_history_request_idx');
            $table->index(['action_by', 'new_status'], 'learning_document_history_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_document_approval_histories');
        Schema::dropIfExists('learning_document_downloads');
        Schema::dropIfExists('learning_document_requests');
        Schema::dropIfExists('learning_document_classes');
        Schema::dropIfExists('learning_documents');
        Schema::dropIfExists('learning_document_categories');
    }
};
