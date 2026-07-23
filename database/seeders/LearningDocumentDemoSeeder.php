<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\LearningDocument;
use App\Models\LearningDocumentApprovalHistory;
use App\Models\LearningDocumentRequest;
use App\Models\Mapel;
use App\Models\Pesan;
use App\Models\User;
use App\Services\LearningDocumentStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningDocumentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $kepala = User::query()->where('role', 'kepala_sekolah')->first();
        $teacher = Guru::query()->whereHas('user', fn ($query) => $query->where('email', 'guru.demo@alazharjayaindonesia.sch.id'))->first()
            ?: Guru::query()->first();

        if (!$teacher) {
            return;
        }

        $documents = [
            [
                'key' => 'demo-materi-matematika-kelas-4',
                'title' => 'Materi Matematika Kelas 4',
                'filename' => 'KELAS 4 MTK.xlsx',
                'document_type' => 'materi_pelajaran',
                'subject' => 'Matematika',
                'grade' => '4',
                'class_code' => '4',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
            [
                'key' => 'demo-modul-bahasa-indonesia-kelas-5',
                'title' => 'Modul Bahasa Indonesia Kelas 5',
                'filename' => 'KELAS 5 B. INDO.xlsx',
                'document_type' => 'modul_ajar',
                'subject' => 'Bahasa Indonesia',
                'grade' => '5',
                'class_code' => '5A',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
            [
                'key' => 'demo-soal-sts-ipas-kelas-6',
                'title' => 'Soal STS IPAS Kelas 6',
                'filename' => null,
                'document_type' => 'soal_sts',
                'subject' => 'IPAS',
                'grade' => '6',
                'class_code' => '6A',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
            [
                'key' => 'demo-kisi-sas-matematika-kelas-3',
                'title' => 'Kisi-Kisi SAS Matematika Kelas 3',
                'filename' => null,
                'document_type' => 'kisi_kisi',
                'subject' => 'Matematika',
                'grade' => '3',
                'class_code' => '3',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
            [
                'key' => 'demo-kunci-jawaban-bahasa-inggris-kelas-4',
                'title' => 'Kunci Jawaban Bahasa Inggris Kelas 4',
                'filename' => null,
                'document_type' => 'kunci_jawaban',
                'subject' => 'Bahasa Inggris',
                'grade' => '4',
                'class_code' => '4',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
            [
                'key' => 'demo-lkpd-pkn-kelas-2',
                'title' => 'Lembar Kerja Pendidikan Pancasila Kelas 2',
                'filename' => null,
                'document_type' => 'lembar_kerja',
                'subject' => 'Pendidikan Kewarganegaraan',
                'grade' => '2',
                'class_code' => '2',
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
            ],
        ];

        $createdDocuments = [];
        foreach ($documents as $meta) {
            $createdDocuments[$meta['key']] = $this->document($meta, $kepala);
        }

        $this->requestFlow($teacher, $kepala, $createdDocuments);
    }

    private function document(array $meta, ?User $kepala): LearningDocument
    {
        $subject = Mapel::query()->where('nama_mapel', $meta['subject'])->first();
        $classes = Kelas::query()
            ->where('jenjang', 'SD')
            ->where(function ($query) use ($meta) {
                $query->where('kode_kelas', 'like', $meta['grade'].'%')
                    ->orWhere('nama_kelas', 'like', 'Kelas '.$meta['grade'].'%');
            })
            ->pluck('id')
            ->all();

        $payload = [
            'title' => $meta['title'],
            'description' => 'Data demo modul Pusat Dokumen Pembelajaran SD.',
            'document_type' => $meta['document_type'],
            'subject_id' => $subject?->id,
            'school_level' => 'SD',
            'grade_level' => $meta['grade'],
            'class_ids' => $classes,
            'semester' => $meta['semester'],
            'academic_year' => $meta['academic_year'],
            'classification_status' => LearningDocument::STATUS_CLASSIFIED,
            'is_sensitive' => (bool) (config('learning_documents.document_types.'.$meta['document_type'].'.sensitive') ?? false),
            'is_active' => true,
            'internal_notes' => $meta['filename']
                ? 'Demo menggunakan file asli yang tersedia dari folder sumber.'
                : 'Metadata demo tanpa file karena file contoh final tidak tersedia; tidak ada PDF atau soal palsu yang dibuat.',
        ];

        $source = $this->sourcePath($meta['filename']);
        if ($source) {
            return app(LearningDocumentStorageService::class)
                ->storeLocalFile($source, $payload, $kepala)['document'];
        }

        $documentPayload = $payload;
        unset($documentPayload['class_ids']);

        $document = LearningDocument::updateOrCreate(
            ['title' => $meta['title'], 'document_type' => $meta['document_type']],
            array_merge($documentPayload, [
                'original_filename' => null,
                'stored_filename' => null,
                'file_extension' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum_sha256' => null,
                'storage_disk' => 'local',
                'storage_path' => null,
                'uploaded_by' => $kepala?->id,
                'uploaded_at' => now(),
            ])
        );
        $document->classes()->sync($classes);

        return $document->refresh();
    }

    private function requestFlow(Guru $teacher, ?User $kepala, array $documents): void
    {
        $flows = [
            ['demo-pending', 'demo-materi-matematika-kelas-4', LearningDocumentRequest::STATUS_PENDING, null],
            ['demo-approved', 'demo-modul-bahasa-indonesia-kelas-5', LearningDocumentRequest::STATUS_APPROVED, null],
            ['demo-rejected', 'demo-soal-sts-ipas-kelas-6', LearningDocumentRequest::STATUS_REJECTED, 'Contoh alasan penolakan demo.'],
            ['demo-clarification', 'demo-kisi-sas-matematika-kelas-3', LearningDocumentRequest::STATUS_CLARIFICATION_REQUESTED, 'Mohon perjelas ruang lingkup materi yang diminta.'],
            ['demo-expired', 'demo-kunci-jawaban-bahasa-inggris-kelas-4', LearningDocumentRequest::STATUS_APPROVED, 'Akses demo sudah kedaluwarsa.'],
        ];

        foreach ($flows as [$suffix, $documentKey, $status, $notes]) {
            $document = $documents[$documentKey];
            $kelas = $document->classes()->first();

            DB::transaction(function () use ($teacher, $kepala, $document, $kelas, $suffix, $status, $notes) {
                $request = LearningDocumentRequest::updateOrCreate(
                    ['request_number' => 'DOC-DEMO-'.strtoupper($suffix)],
                    [
                        'teacher_id' => $teacher->id,
                        'learning_document_id' => $document->id,
                        'class_id' => $kelas?->id,
                        'subject_id' => $document->subject_id,
                        'document_type' => $document->document_type,
                        'requested_title' => $document->title,
                        'purpose' => 'Keperluan pembelajaran demo untuk '.$document->title.'.',
                        'required_at' => now()->addWeek()->toDateString(),
                        'status' => $status,
                        'requested_at' => now()->subDays(5),
                        'reviewed_by' => in_array($status, [LearningDocumentRequest::STATUS_PENDING], true) ? null : $kepala?->id,
                        'reviewed_at' => in_array($status, [LearningDocumentRequest::STATUS_PENDING], true) ? null : now()->subDays(2),
                        'reviewer_notes' => $notes,
                        'approved_document_id' => $status === LearningDocumentRequest::STATUS_APPROVED ? $document->id : null,
                        'access_starts_at' => $status === LearningDocumentRequest::STATUS_APPROVED ? now()->subDay() : null,
                        'access_expires_at' => $suffix === 'demo-expired' ? now()->subHour() : null,
                        'max_downloads' => $status === LearningDocumentRequest::STATUS_APPROVED ? 5 : null,
                        'download_count' => 0,
                        'revoked_at' => null,
                    ]
                );

                LearningDocumentApprovalHistory::updateOrCreate(
                    ['request_id' => $request->id, 'new_status' => $status],
                    [
                        'previous_status' => LearningDocumentRequest::STATUS_PENDING,
                        'action_by' => $kepala?->id,
                        'notes' => $notes ?: 'Seeder demo status '.$status.'.',
                    ]
                );
            });
        }

        if ($kepala && $teacher->user) {
            Pesan::firstOrCreate(
                [
                    'pengirim_id' => $kepala->id,
                    'penerima_id' => $teacher->user_id,
                    'subjek' => 'Demo Dokumen Pembelajaran Disetujui',
                ],
                [
                    'isi' => 'Permintaan dokumen demo telah disetujui dan dapat diunduh sesuai masa akses.',
                    'dibaca' => false,
                    'kategori' => 'Dokumen Pembelajaran',
                    'tanggal' => now(),
                    'thread_id' => 'learning-documents-demo-'.$teacher->id,
                ]
            );
        }
    }

    private function sourcePath(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        $path = rtrim(config('learning_documents.source_path'), '/\\').DIRECTORY_SEPARATOR.$filename;

        return is_file($path) ? $path : null;
    }
}
