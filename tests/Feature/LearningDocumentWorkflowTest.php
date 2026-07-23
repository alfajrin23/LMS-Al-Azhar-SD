<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\LearningDocument;
use App\Models\LearningDocumentDownload;
use App\Models\LearningDocumentRequest;
use App\Models\Mapel;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Services\LearningDocumentStorageService;
use Database\Seeders\LearningDocumentCategorySeeder;
use Database\Seeders\LearningDocumentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $kepala;
    private User $guruUser;
    private User $otherGuruUser;
    private User $siswaUser;
    private User $siswaSmpUser;
    private User $ortuUser;
    private Guru $guru;
    private Guru $otherGuru;
    private Kelas $kelas1;
    private Kelas $kelas2;
    private Kelas $kelas3;
    private Kelas $kelas4;
    private Kelas $kelas5;
    private Kelas $kelas6;
    private Kelas $kelasSmp;
    private Mapel $matematika;
    private Mapel $bahasaIndonesia;
    private Mapel $ipas;
    private Mapel $bahasaInggris;
    private Mapel $pkn;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(LearningDocumentCategorySeeder::class);

        $this->kepala = User::create(['name' => 'Kepala', 'email' => 'kepala@test.sch.id', 'password' => Hash::make('password'), 'role' => 'kepala_sekolah']);
        $this->guruUser = User::create(['name' => 'Guru SD', 'email' => 'guru@test.sch.id', 'password' => Hash::make('password'), 'role' => 'guru']);
        $this->otherGuruUser = User::create(['name' => 'Guru Lain', 'email' => 'guru2@test.sch.id', 'password' => Hash::make('password'), 'role' => 'guru']);
        $this->siswaUser = User::create(['name' => 'Siswa SD', 'email' => 'siswa@test.sch.id', 'password' => Hash::make('password'), 'role' => 'siswa_sd']);
        $this->siswaSmpUser = User::create(['name' => 'Siswa SMP', 'email' => 'smp@test.sch.id', 'password' => Hash::make('password'), 'role' => 'siswa_smp']);
        $this->ortuUser = User::create(['name' => 'Ortu', 'email' => 'ortu@test.sch.id', 'password' => Hash::make('password'), 'role' => 'orang_tua']);

        $this->matematika = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK']);
        $this->bahasaIndonesia = Mapel::create(['nama_mapel' => 'Bahasa Indonesia', 'kode' => 'B.IND']);
        $this->ipas = Mapel::create(['nama_mapel' => 'IPAS', 'kode' => 'IPAS']);
        $this->bahasaInggris = Mapel::create(['nama_mapel' => 'Bahasa Inggris', 'kode' => 'ING']);
        $this->pkn = Mapel::create(['nama_mapel' => 'Pendidikan Kewarganegaraan', 'kode' => 'PKN']);

        $this->guru = Guru::create(['user_id' => $this->guruUser->id, 'nip' => 'G-1', 'nama' => 'Guru SD', 'mapel_id' => $this->matematika->id, 'status' => 'aktif']);
        $this->otherGuru = Guru::create(['user_id' => $this->otherGuruUser->id, 'nip' => 'G-2', 'nama' => 'Guru Lain', 'mapel_id' => $this->matematika->id, 'status' => 'aktif']);
        $this->guru->mapels()->sync([$this->matematika->id, $this->bahasaIndonesia->id, $this->ipas->id, $this->bahasaInggris->id, $this->pkn->id]);
        $this->otherGuru->mapels()->sync([$this->matematika->id]);

        $this->kelas1 = $this->kelas('1', 'Kelas 1');
        $this->kelas2 = $this->kelas('2', 'Kelas 2');
        $this->kelas3 = $this->kelas('3', 'Kelas 3');
        $this->kelas4 = $this->kelas('4', 'Kelas 4');
        $this->kelas5 = $this->kelas('5A', 'Kelas 5A');
        $this->kelas6 = $this->kelas('6A', 'Kelas 6A');
        $this->kelasSmp = Kelas::create(['kode_kelas' => '7A', 'nama_kelas' => 'Kelas 7A', 'jenjang' => 'SMP']);

        foreach ([
            [$this->kelas1, $this->matematika],
            [$this->kelas2, $this->pkn],
            [$this->kelas3, $this->matematika],
            [$this->kelas4, $this->bahasaInggris],
            [$this->kelas5, $this->bahasaIndonesia],
            [$this->kelas6, $this->ipas],
        ] as [$kelas, $mapel]) {
            Jadwal::create(['kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $this->guru->id, 'hari' => 'Senin', 'jam_mulai' => '07:00', 'jam_selesai' => '07:40']);
        }

        Jadwal::create(['kelas_id' => $this->kelas2->id, 'mapel_id' => $this->matematika->id, 'guru_id' => $this->otherGuru->id, 'hari' => 'Selasa', 'jam_mulai' => '07:00', 'jam_selesai' => '07:40']);

        $siswa = Siswa::create(['user_id' => $this->siswaUser->id, 'nis' => 'S-1', 'nama' => 'Siswa SD', 'kelas_id' => $this->kelas1->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['user_id' => $this->siswaSmpUser->id, 'nis' => 'SMP-1', 'nama' => 'Siswa SMP', 'kelas_id' => $this->kelasSmp->id, 'jenis_kelamin' => 'L']);
        $ortu = OrangTua::create(['user_id' => $this->ortuUser->id, 'nama' => 'Ortu']);
        $ortu->siswa()->sync([$siswa->id]);
    }

    public function test_kepala_sekolah_can_upload_file_and_binary_is_identical(): void
    {
        $content = "%PDF-1.4\nDokumen final upload\n";
        $response = $this->actingAs($this->kepala)->post(route('kepala.documents.store'), [
            'files' => [UploadedFile::fake()->createWithContent('materi-matematika-kelas-1.pdf', $content)],
            'document_type' => 'materi_pelajaran',
            'subject_id' => $this->matematika->id,
            'grade_level' => '1',
            'class_ids' => [$this->kelas1->id],
            'classification_status' => 'classified',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $document = LearningDocument::where('original_filename', 'materi-matematika-kelas-1.pdf')->firstOrFail();
        $stored = Storage::disk('local')->get($document->storage_path);

        $this->assertSame($content, $stored);
        $this->assertSame(hash('sha256', $content), $document->checksum_sha256);
        $this->assertDatabaseHas('learning_document_classes', ['learning_document_id' => $document->id, 'class_id' => $this->kelas1->id]);
    }

    public function test_file_is_not_served_through_public_storage_url(): void
    {
        $document = $this->document();

        $this->get('/storage/'.$document->storage_path)->assertForbidden();
    }

    public function test_guru_can_create_request_and_new_request_is_pending(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.documents.requests.store'), $this->requestPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('learning_document_requests', [
            'teacher_id' => $this->guru->id,
            'requested_title' => 'Materi Matematika Kelas 1',
            'status' => LearningDocumentRequest::STATUS_PENDING,
        ]);
    }

    public function test_pending_request_cannot_download_document(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_PENDING);

        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]))
            ->assertForbidden();
    }

    public function test_kepala_can_approve_and_document_appears_for_teacher(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_PENDING);

        $this->actingAs($this->kepala)->post(route('kepala.documents.requests.approve', $request), [
            'approved_document_id' => $document->id,
            'max_downloads' => 3,
        ])->assertRedirect();

        $this->assertDatabaseHas('learning_document_requests', [
            'id' => $request->id,
            'status' => LearningDocumentRequest::STATUS_APPROVED,
            'approved_document_id' => $document->id,
        ]);

        $this->actingAs($this->guruUser)
            ->get(route('dashboard', ['tab' => 'permintaan_dokumen']))
            ->assertOk()
            ->assertSee('Dokumen Siap Diunduh')
            ->assertSee($document->title);
    }

    public function test_guru_can_download_approved_document_and_checksum_matches(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_APPROVED);

        $response = $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]));

        $response->assertOk();
        $this->assertSame($document->checksum_sha256, hash('sha256', $response->streamedContent()));
        $this->assertDatabaseHas('learning_document_downloads', [
            'learning_document_id' => $document->id,
            'request_id' => $request->id,
            'user_id' => $this->guruUser->id,
            'checksum_at_download' => $document->checksum_sha256,
        ]);
    }

    public function test_rejected_request_cannot_download_document(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_PENDING);

        $this->actingAs($this->kepala)->post(route('kepala.documents.requests.reject', $request), [
            'reviewer_notes' => 'Dokumen belum sesuai kebutuhan.',
        ])->assertRedirect();

        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]))
            ->assertForbidden();
    }

    public function test_clarification_request_can_be_revised_and_resubmitted(): void
    {
        [, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_PENDING);

        $this->actingAs($this->kepala)->post(route('kepala.documents.requests.clarify', $request), [
            'reviewer_notes' => 'Lengkapi topik.',
        ])->assertRedirect();

        $this->actingAs($this->guruUser)->put(route('guru.documents.requests.update', $request), $this->requestPayload([
            'requested_title' => 'Materi Matematika Kelas 1 Bab Bilangan',
        ]))->assertRedirect();

        $this->assertDatabaseHas('learning_document_requests', [
            'id' => $request->id,
            'status' => LearningDocumentRequest::STATUS_PENDING,
            'requested_title' => 'Materi Matematika Kelas 1 Bab Bilangan',
        ]);
    }

    public function test_revoked_and_expired_access_are_denied(): void
    {
        [$document, $revoked] = $this->documentAndRequest(LearningDocumentRequest::STATUS_APPROVED);

        $this->actingAs($this->kepala)->post(route('kepala.documents.requests.revoke', $revoked))->assertRedirect();
        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $revoked->id]))
            ->assertForbidden();

        [$expiredDocument, $expired] = $this->documentAndRequest(LearningDocumentRequest::STATUS_APPROVED, ['access_expires_at' => now()->subMinute()]);
        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $expiredDocument, 'request_id' => $expired->id]))
            ->assertForbidden();
    }

    public function test_other_teacher_students_parents_and_smp_students_cannot_download_sd_document(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_APPROVED);

        $this->actingAs($this->otherGuruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]))
            ->assertForbidden();
        $this->actingAs($this->siswaUser)->get(route('learning-documents.download', $document))->assertForbidden();
        $this->actingAs($this->ortuUser)->get(route('learning-documents.download', $document))->assertForbidden();
        $this->actingAs($this->siswaSmpUser)->get(route('learning-documents.download', $document))->assertForbidden();
    }

    public function test_same_file_is_not_imported_twice_and_one_file_can_have_many_classes(): void
    {
        $first = $this->document('same-file.pdf', 'materi_pelajaran', [$this->kelas1->id, $this->kelas2->id], "%PDF-1.4\nsame\n");
        $second = app(LearningDocumentStorageService::class)->storeUploadedFile(
            UploadedFile::fake()->createWithContent('same-file-copy.pdf', "%PDF-1.4\nsame\n"),
            [
                'title' => 'Copy',
                'document_type' => 'materi_pelajaran',
                'subject_id' => $this->matematika->id,
                'grade_level' => '1',
                'class_ids' => [$this->kelas3->id],
                'classification_status' => 'classified',
                'is_active' => true,
            ],
            $this->kepala
        );

        $this->assertFalse($second['created']);
        $this->assertSame(1, LearningDocument::where('checksum_sha256', $first->checksum_sha256)->count());
        $this->assertDatabaseHas('learning_document_classes', ['learning_document_id' => $first->id, 'class_id' => $this->kelas1->id]);
        $this->assertDatabaseHas('learning_document_classes', ['learning_document_id' => $first->id, 'class_id' => $this->kelas2->id]);
        $this->assertDatabaseHas('learning_document_classes', ['learning_document_id' => $first->id, 'class_id' => $this->kelas3->id]);
    }

    public function test_xlsm_extension_and_original_filename_are_preserved_on_download(): void
    {
        $document = $this->document('macro-final.xlsm', 'materi_pelajaran', [$this->kelas1->id], 'binary-xlsm-content');
        $request = $this->approvedRequest($document);

        $response = $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]));

        $response->assertOk();
        $this->assertSame('xlsm', $document->file_extension);
        $this->assertStringContainsString('macro-final.xlsm', $response->headers->get('content-disposition'));
    }

    public function test_max_downloads_inactive_documents_sensitive_catalog_and_head_download_rules(): void
    {
        [$document, $request] = $this->documentAndRequest(LearningDocumentRequest::STATUS_APPROVED, ['max_downloads' => 1]);

        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]))
            ->assertOk();
        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $document, 'request_id' => $request->id]))
            ->assertForbidden();

        $inactive = $this->document('inactive.pdf');
        $inactive->update(['is_active' => false]);
        $inactiveRequest = $this->approvedRequest($inactive);
        $this->actingAs($this->guruUser)
            ->get(route('learning-documents.download', ['document' => $inactive, 'request_id' => $inactiveRequest->id]))
            ->assertForbidden();

        $sensitive = $this->document('bank-rahasia-xyz987.pdf', 'kunci_jawaban', [$this->kelas1->id], "%PDF-1.4\nunique-sensitive\n");
        $this->actingAs($this->guruUser)
            ->get(route('dashboard', ['tab' => 'permintaan_dokumen']))
            ->assertOk()
            ->assertDontSee($sensitive->title);

        $this->actingAs($this->kepala)
            ->get(route('learning-documents.download', $sensitive))
            ->assertOk();
    }

    public function test_seeders_are_idempotent_migration_can_rollback_and_sql_sync_contains_new_tables(): void
    {
        $this->seed(LearningDocumentDemoSeeder::class);
        $firstCount = LearningDocument::count();
        $this->seed(LearningDocumentDemoSeeder::class);
        $this->assertSame($firstCount, LearningDocument::count());

        $sqlPath = storage_path('framework/testing/lms-documents.sql');
        Artisan::call('documents:sync-sql', ['--path' => $sqlPath]);
        $this->assertStringContainsString('learning_documents', file_get_contents($sqlPath));
        $this->assertStringContainsString('learning_document_requests', file_get_contents($sqlPath));

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_07_23_080000_create_learning_document_center_tables.php']);
        $this->assertFalse(Schema::hasTable('learning_documents'));
        Artisan::call('migrate', ['--path' => 'database/migrations/2026_07_23_080000_create_learning_document_center_tables.php']);
        $this->assertTrue(Schema::hasTable('learning_documents'));
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => $this->kelas1->id,
            'subject_id' => $this->matematika->id,
            'document_type' => 'materi_pelajaran',
            'requested_title' => 'Materi Matematika Kelas 1',
            'purpose' => 'Untuk kegiatan belajar pekan ini.',
            'required_at' => now()->addDays(2)->toDateString(),
            'submit_action' => 'pending',
        ], $overrides);
    }

    private function documentAndRequest(string $status, array $requestOverrides = []): array
    {
        $document = $this->document();
        $request = $status === LearningDocumentRequest::STATUS_APPROVED
            ? $this->approvedRequest($document, $requestOverrides)
            : LearningDocumentRequest::create(array_merge([
                'request_number' => 'REQ-'.uniqid(),
                'teacher_id' => $this->guru->id,
                'learning_document_id' => $document->id,
                'class_id' => $this->kelas1->id,
                'subject_id' => $this->matematika->id,
                'document_type' => $document->document_type,
                'requested_title' => $document->title,
                'purpose' => 'Butuh dokumen.',
                'required_at' => now()->addDay()->toDateString(),
                'status' => $status,
                'requested_at' => now(),
            ], $requestOverrides));

        return [$document, $request];
    }

    private function approvedRequest(LearningDocument $document, array $overrides = []): LearningDocumentRequest
    {
        return LearningDocumentRequest::create(array_merge([
            'request_number' => 'REQ-'.uniqid(),
            'teacher_id' => $this->guru->id,
            'learning_document_id' => $document->id,
            'class_id' => $this->kelas1->id,
            'subject_id' => $document->subject_id,
            'document_type' => $document->document_type,
            'requested_title' => $document->title,
            'purpose' => 'Butuh dokumen approved.',
            'required_at' => now()->addDay()->toDateString(),
            'status' => LearningDocumentRequest::STATUS_APPROVED,
            'requested_at' => now()->subDay(),
            'reviewed_by' => $this->kepala->id,
            'reviewed_at' => now(),
            'approved_document_id' => $document->id,
            'access_starts_at' => now()->subMinute(),
            'access_expires_at' => null,
            'max_downloads' => null,
        ], $overrides));
    }

    private function document(string $filename = 'materi-final.pdf', string $type = 'materi_pelajaran', array $classIds = [], string $content = "%PDF-1.4\nfinal-content\n"): LearningDocument
    {
        $classIds = $classIds ?: [$this->kelas1->id];
        $result = app(LearningDocumentStorageService::class)->storeUploadedFile(
            UploadedFile::fake()->createWithContent($filename, $content),
            [
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'description' => 'Dokumen test.',
                'document_type' => $type,
                'subject_id' => $this->matematika->id,
                'grade_level' => '1',
                'class_ids' => $classIds,
                'semester' => 'Ganjil',
                'academic_year' => '2026/2027',
                'classification_status' => 'classified',
                'is_sensitive' => $type === 'kunci_jawaban',
                'is_active' => true,
            ],
            $this->kepala
        );

        return $result['document'];
    }

    private function kelas(string $kode, string $nama): Kelas
    {
        return Kelas::create(['kode_kelas' => $kode, 'nama_kelas' => $nama, 'jenjang' => 'SD']);
    }
}
