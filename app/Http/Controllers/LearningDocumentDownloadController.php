<?php

namespace App\Http\Controllers;

use App\Models\LearningDocument;
use App\Models\LearningDocumentDownload;
use App\Models\LearningDocumentRequest as DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LearningDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, LearningDocument $document): Response
    {
        $accessRequest = $this->resolveAccessRequest($request, $document);
        Gate::authorize('download', [$document, $accessRequest]);

        $disk = $document->storage_disk ?: 'local';
        $path = Storage::disk($disk)->path($document->storage_path);
        if (!is_file($path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        $checksum = hash_file('sha256', $path);
        if ($document->checksum_sha256 && $checksum !== $document->checksum_sha256) {
            abort(409, 'Checksum file tersimpan tidak sesuai metadata.');
        }

        if ($request->user()->role === 'guru' && $accessRequest) {
            DB::transaction(function () use ($request, $document, $accessRequest, $checksum) {
                LearningDocumentDownload::create([
                    'learning_document_id' => $document->id,
                    'request_id' => $accessRequest->id,
                    'user_id' => $request->user()->id,
                    'downloaded_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                    'checksum_at_download' => $checksum,
                ]);

                $accessRequest->increment('download_count');
                $accessRequest->forceFill(['last_downloaded_at' => now()])->save();
            });
        }

        return response()->download($path, $document->original_filename ?: basename($path));
    }

    private function resolveAccessRequest(Request $request, LearningDocument $document): ?DocumentRequest
    {
        if ($request->filled('request_id')) {
            return DocumentRequest::query()->findOrFail((int) $request->query('request_id'));
        }

        if ($request->user()->role !== 'guru' || !$request->user()->guru) {
            return null;
        }

        return DocumentRequest::query()
            ->where('teacher_id', $request->user()->guru->id)
            ->where('approved_document_id', $document->id)
            ->where('status', DocumentRequest::STATUS_APPROVED)
            ->whereNull('revoked_at')
            ->orderByDesc('reviewed_at')
            ->get()
            ->first(fn (DocumentRequest $candidate) => $candidate->isAccessibleNow());
    }
}
