@php
    $docTypes = $learningDocumentTypes ?? [];
    $stats = $learningDocumentStats ?? [];
    $documentsForApproval = $learningDocumentApprovalDocuments ?? collect();
@endphp

<div x-data="{ docSubtab: 'semua' }">
    <div class="content-header">
        <div>
            <h1>Dokumen Pembelajaran</h1>
            <p style="font-size:14px;color:var(--gray-400);margin-top:2px">Repositori private file pembelajaran asli SD</p>
        </div>
        <div class="header-right">
            <span class="badge teal" style="font-size:12px;padding:6px 14px"><i class="fas fa-lock"></i> Private Storage</span>
            <div class="avatar teal">KS</div>
        </div>
    </div>

    <div class="grid-4" style="margin-bottom:16px">
        @foreach([
            ['Total Dokumen', $stats['total_dokumen'] ?? 0, 'fa-folder-open', 'grad-teal'],
            ['Materi', $stats['total_materi'] ?? 0, 'fa-book-open', 'grad-blue'],
            ['Soal', $stats['total_soal'] ?? 0, 'fa-file-circle-question', 'grad-orange'],
            ['Perlu Verifikasi', $stats['perlu_verifikasi'] ?? 0, 'fa-triangle-exclamation', 'grad-purple'],
        ] as [$label, $value, $icon, $grad])
            <div class="admin-stat-card">
                <div class="asc-icon {{ $grad }}"><i class="fas {{ $icon }}"></i></div>
                <div class="asc-body">
                    <div class="asc-number">{{ $value }}</div>
                    <div class="asc-label">{{ $label }}</div>
                    <div class="asc-compare up">Data dihitung dari database</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid-4" style="margin-bottom:16px">
        @foreach([
            ['Kisi-Kisi', $stats['total_kisi'] ?? 0],
            ['Kunci Jawaban', $stats['total_kunci'] ?? 0],
            ['Pending', $stats['pending'] ?? 0],
            ['Download Hari Ini', $stats['download_hari_ini'] ?? 0],
        ] as [$label, $value])
            <div class="card" style="padding:14px">
                <div style="font-size:22px;font-weight:800;color:var(--teal)">{{ $value }}</div>
                <div style="font-size:12px;color:var(--gray-500);font-weight:700">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3><i class="fas fa-upload" style="color:var(--teal)"></i> Upload File Asli</h3>
            <span class="badge light blue">PDF, Word, Excel, PowerPoint, gambar, ZIP, RAR</span>
        </div>
        <form id="headDocumentUploadForm" method="POST" action="{{ route('kepala.documents.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid-2" style="gap:12px">
                <div>
                    <label style="font-size:12px;font-weight:700;color:var(--gray-500)">File</label>
                    <input id="headDocumentFiles" type="file" name="files[]" multiple required
                        style="width:100%;border:1px dashed var(--border);border-radius:6px;padding:14px;background:var(--gray-50)">
                    <div id="headDocumentFileCount" style="font-size:11px;color:var(--gray-400);margin-top:6px">Belum ada file dipilih</div>
                    <progress id="headDocumentUploadProgress" max="100" value="0" style="display:none;width:100%;height:8px;margin-top:8px"></progress>
                </div>
                <div class="grid-2" style="gap:10px">
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Jenis</label>
                        <select name="document_type" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                            <option value="">Deteksi otomatis</option>
                            @foreach($docTypes as $code => $type)
                                <option value="{{ $code }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Mapel</label>
                        <select name="subject_id" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                            <option value="">Deteksi otomatis</option>
                            @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                                <option value="{{ $mapel->id }}">{{ $mapel->nama_mapel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Tingkat</label>
                        <select name="grade_level" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                            <option value="">Deteksi otomatis</option>
                            @foreach([1,2,3,4,5,6] as $grade)
                                <option value="{{ $grade }}">Kelas {{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Semester</label>
                        <select name="semester" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                            <option value="">Kosongkan jika tidak tercantum</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-top:12px;flex-wrap:wrap">
                <label style="font-size:12px"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
                <label style="font-size:12px"><input type="checkbox" name="is_sensitive" value="1"> Sensitif</label>
                <button type="submit" class="btn-small teal" style="border:none;cursor:pointer;padding:8px 14px"><i class="fas fa-cloud-arrow-up"></i> Upload</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach([
                'semua' => 'Semua Dokumen',
                'materi' => 'Materi Pelajaran',
                'soal_latihan' => 'Soal Latihan',
                'soal_ujian' => 'Soal Ujian',
                'kisi' => 'Kisi-Kisi',
                'kunci' => 'Kunci Jawaban',
                'tugas' => 'Tugas dan Lembar Kerja',
                'lainnya' => 'Dokumen Lainnya',
                'permintaan' => 'Permintaan Guru',
                'riwayat' => 'Riwayat Persetujuan',
                'verifikasi' => 'Dokumen Perlu Verifikasi',
            ] as $key => $label)
                <button type="button" class="btn-small" @click="docSubtab='{{ $key }}'"
                    :class="docSubtab === '{{ $key }}' ? 'teal' : 'outline'"
                    style="border:1px solid var(--border);cursor:pointer;padding:7px 10px">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div x-show="!['permintaan','riwayat','verifikasi'].includes(docSubtab)">
        <div class="card" style="margin-bottom:16px">
            <form method="GET" action="{{ route('dashboard') }}">
                <input type="hidden" name="tab" value="dokumen_pembelajaran">
                <div class="grid-4" style="gap:10px">
                    <input type="text" name="doc_search" value="{{ request('doc_search') }}" placeholder="Nama file atau judul"
                        style="border:1px solid var(--border);border-radius:4px;padding:8px">
                    <select name="doc_class_id" style="border:1px solid var(--border);border-radius:4px;padding:8px">
                        <option value="">Semua kelas</option>
                        @foreach($learningDocumentKelasOptions ?? [] as $kelas)
                            <option value="{{ $kelas->id }}" @selected(request('doc_class_id') == $kelas->id)>{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>
                    <select name="doc_subject_id" style="border:1px solid var(--border);border-radius:4px;padding:8px">
                        <option value="">Semua mapel</option>
                        @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                            <option value="{{ $mapel->id }}" @selected(request('doc_subject_id') == $mapel->id)>{{ $mapel->nama_mapel }}</option>
                        @endforeach
                    </select>
                    <select name="doc_type" style="border:1px solid var(--border);border-radius:4px;padding:8px">
                        <option value="">Semua jenis</option>
                        @foreach($docTypes as $code => $type)
                            <option value="{{ $code }}" @selected(request('doc_type') === $code)>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                    <select name="doc_classification_status" style="border:1px solid var(--border);border-radius:4px;padding:8px">
                        <option value="">Semua status klasifikasi</option>
                        <option value="classified" @selected(request('doc_classification_status') === 'classified')>Terverifikasi</option>
                        <option value="needs_verification" @selected(request('doc_classification_status') === 'needs_verification')>Perlu Verifikasi</option>
                    </select>
                    <select name="doc_sensitive" style="border:1px solid var(--border);border-radius:4px;padding:8px">
                        <option value="">Sensitif/non-sensitif</option>
                        <option value="1" @selected(request('doc_sensitive') === '1')>Sensitif</option>
                        <option value="0" @selected(request('doc_sensitive') === '0')>Tidak sensitif</option>
                    </select>
                    <button class="btn-small teal" style="border:none;cursor:pointer;padding:8px 12px"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-database" style="color:var(--teal)"></i> Inventaris Dokumen</h3>
                <span class="badge light blue">{{ isset($learningDocuments) ? $learningDocuments->total() : 0 }} record</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Kelas/Mapel</th>
                            <th>Jenis</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($learningDocuments ?? [] as $document)
                            @php
                                $type = $document->document_type;
                                $showExpression = "docSubtab === 'semua'"
                                    ." || (docSubtab === 'materi' && ['materi_pelajaran','modul_ajar','bahan_ajar'].includes('{$type}'))"
                                    ." || (docSubtab === 'soal_latihan' && '{$type}' === 'soal_latihan')"
                                    ." || (docSubtab === 'soal_ujian' && '{$type}'.startsWith('soal_') && '{$type}' !== 'soal_latihan')"
                                    ." || (docSubtab === 'kisi' && '{$type}' === 'kisi_kisi')"
                                    ." || (docSubtab === 'kunci' && '{$type}' === 'kunci_jawaban')"
                                    ." || (docSubtab === 'tugas' && ['tugas','lembar_kerja'].includes('{$type}'))"
                                    ." || (docSubtab === 'lainnya' && !['materi_pelajaran','modul_ajar','bahan_ajar','soal_latihan','kisi_kisi','kunci_jawaban','tugas','lembar_kerja'].includes('{$type}') && !'{$type}'.startsWith('soal_'))";
                            @endphp
                            <tr x-show="{{ $showExpression }}">
                                <td>
                                    <strong>{{ $document->title }}</strong>
                                    <div style="font-size:11px;color:var(--gray-400)">{{ $document->original_filename ?? 'Metadata tanpa file' }}</div>
                                    @if($document->internal_notes)
                                        <details style="font-size:11px;color:var(--gray-500);margin-top:4px">
                                            <summary>Catatan</summary>
                                            {{ $document->internal_notes }}
                                        </details>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $document->classes->pluck('nama_kelas')->join(', ') ?: ($document->grade_level ? 'Seluruh Kelas '.$document->grade_level : '-') }}</div>
                                    <small style="color:var(--gray-400)">{{ $document->subject->nama_mapel ?? 'Perlu Verifikasi' }}</small>
                                </td>
                                <td>{{ $document->documentTypeLabel() }}</td>
                                <td>
                                    <div>{{ strtoupper($document->file_extension ?? '-') }} / {{ $document->fileSizeLabel() }}</div>
                                    <small style="color:var(--gray-400)">{{ $document->checksum_sha256 ? substr($document->checksum_sha256, 0, 16).'...' : '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge {{ $document->classification_status === 'classified' ? 'green' : 'orange' }}">{{ $document->classificationStatusLabel() }}</span>
                                    @if($document->is_sensitive)
                                        <span class="badge red">Sensitif</span>
                                    @endif
                                    @unless($document->is_active)
                                        <span class="badge light">Nonaktif</span>
                                    @endunless
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        @if($document->hasStoredFile())
                                            <a href="{{ route('learning-documents.download', $document) }}" class="btn-small outline" style="text-decoration:none"><i class="fas fa-download"></i></a>
                                        @endif
                                        <details>
                                            <summary class="btn-small outline" style="cursor:pointer;list-style:none">Koreksi</summary>
                                            <form method="POST" action="{{ route('kepala.documents.update', $document) }}" style="margin-top:8px;width:260px">
                                                @csrf
                                                @method('PUT')
                                                <input name="title" value="{{ $document->title }}" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                <select name="document_type" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    @foreach($docTypes as $code => $typeMeta)
                                                        <option value="{{ $code }}" @selected($document->document_type === $code)>{{ $typeMeta['label'] }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="subject_id" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    <option value="">Perlu Verifikasi</option>
                                                    @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                                                        <option value="{{ $mapel->id }}" @selected($document->subject_id === $mapel->id)>{{ $mapel->nama_mapel }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="classification_status" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    <option value="classified" @selected($document->classification_status === 'classified')>Terverifikasi</option>
                                                    <option value="needs_verification" @selected($document->classification_status === 'needs_verification')>Perlu Verifikasi</option>
                                                </select>
                                                <input type="hidden" name="is_active" value="0">
                                                <label style="font-size:11px"><input type="checkbox" name="is_active" value="1" @checked($document->is_active)> Aktif</label>
                                                <input type="hidden" name="is_sensitive" value="0">
                                                <label style="font-size:11px;margin-left:8px"><input type="checkbox" name="is_sensitive" value="1" @checked($document->is_sensitive)> Sensitif</label>
                                                <button class="btn-small teal" style="border:none;margin-top:6px;cursor:pointer;width:100%">Simpan</button>
                                            </form>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:20px">Belum ada dokumen</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ ($learningDocuments ?? null)?->links('vendor.pagination.custom') }}</div>
        </div>
    </div>

    <div x-show="docSubtab === 'permintaan'">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-inbox" style="color:var(--orange)"></i> Permintaan Guru</h3>
                <span class="badge orange">{{ $stats['pending'] ?? 0 }} pending</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Guru</th>
                            <th>Kebutuhan</th>
                            <th>Status</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($learningDocumentRequestsForReview ?? [] as $docRequest)
                            <tr>
                                <td>
                                    <strong>{{ $docRequest->request_number }}</strong>
                                    <div style="font-size:11px;color:var(--gray-400)">Dibutuhkan: {{ $docRequest->required_at?->format('d M Y') ?? '-' }}</div>
                                </td>
                                <td>{{ $docRequest->teacher->nama ?? '-' }}</td>
                                <td>
                                    <div>{{ $docRequest->requested_title }}</div>
                                    <small style="color:var(--gray-400)">{{ $docRequest->kelas->nama_kelas ?? '-' }} / {{ $docRequest->subject->nama_mapel ?? '-' }} / {{ $docRequest->documentTypeLabel() }}</small>
                                </td>
                                <td><span class="badge {{ $docRequest->status === 'approved' ? 'green' : ($docRequest->status === 'rejected' ? 'red' : 'orange') }}">{{ $docRequest->statusLabel() }}</span></td>
                                <td>
                                    @if($docRequest->status === 'pending')
                                        <details>
                                            <summary class="btn-small teal" style="cursor:pointer;list-style:none">Review</summary>
                                            <form method="POST" action="{{ route('kepala.documents.requests.approve', $docRequest) }}" style="margin-top:8px;width:280px">
                                                @csrf
                                                <select name="approved_document_id" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    <option value="">Pilih file asli</option>
                                                    @foreach($documentsForApproval as $approvalDocument)
                                                        <option value="{{ $approvalDocument->id }}">{{ $approvalDocument->title }} ({{ $approvalDocument->subject->nama_mapel ?? '-' }})</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="access_expires_at" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                <input type="number" min="1" name="max_downloads" placeholder="Maks download" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                <textarea name="reviewer_notes" rows="2" placeholder="Catatan opsional" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px"></textarea>
                                                <button class="btn-small teal" style="border:none;cursor:pointer">Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('kepala.documents.requests.reject', $docRequest) }}" style="margin-top:8px;width:280px">
                                                @csrf
                                                <textarea name="reviewer_notes" required rows="2" placeholder="Alasan penolakan" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px"></textarea>
                                                <button class="btn-small red" style="border:none;cursor:pointer">Tolak</button>
                                            </form>
                                            <form method="POST" action="{{ route('kepala.documents.requests.clarify', $docRequest) }}" style="margin-top:8px;width:280px">
                                                @csrf
                                                <textarea name="reviewer_notes" required rows="2" placeholder="Catatan klarifikasi" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px"></textarea>
                                                <button class="btn-small outline" style="cursor:pointer">Minta Klarifikasi</button>
                                            </form>
                                        </details>
                                    @elseif($docRequest->status === 'approved')
                                        <form method="POST" action="{{ route('kepala.documents.requests.revoke', $docRequest) }}">
                                            @csrf
                                            <button class="btn-small red" style="border:none;cursor:pointer">Cabut</button>
                                        </form>
                                    @else
                                        <small style="color:var(--gray-400)">{{ $docRequest->reviewer_notes ?: '-' }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:20px">Belum ada permintaan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ ($learningDocumentRequestsForReview ?? null)?->links('vendor.pagination.custom') }}</div>
        </div>
    </div>

    <div x-show="docSubtab === 'riwayat'">
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-clock-rotate-left" style="color:var(--teal)"></i> Riwayat Persetujuan</h3></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Request</th><th>Status</th><th>Aktor</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @forelse($learningDocumentApprovalHistories ?? [] as $history)
                                <tr>
                                    <td>{{ $history->request->request_number ?? '-' }}</td>
                                    <td>{{ $history->previous_status ?? '-' }} &rarr; {{ $history->new_status }}</td>
                                    <td>{{ $history->actor->name ?? '-' }}</td>
                                    <td>{{ $history->notes ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:var(--gray-400)">Belum ada riwayat</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-download" style="color:var(--blue)"></i> Audit Download</h3></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Dokumen</th><th>User</th><th>Waktu</th><th>Checksum</th></tr></thead>
                        <tbody>
                            @forelse($learningDocumentDownloads ?? [] as $download)
                                <tr>
                                    <td>{{ $download->document->title ?? '-' }}</td>
                                    <td>{{ $download->user->name ?? '-' }}</td>
                                    <td>{{ $download->downloaded_at?->format('d M Y H:i') }}</td>
                                    <td><small>{{ substr($download->checksum_at_download, 0, 12) }}...</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:var(--gray-400)">Belum ada download</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div x-show="docSubtab === 'verifikasi'">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-triangle-exclamation" style="color:var(--orange)"></i> Dokumen Perlu Verifikasi</h3>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dokumen</th><th>Mapel</th><th>Kelas</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @forelse($documentsNeedVerification ?? [] as $document)
                            <tr>
                                <td>{{ $document->title }}</td>
                                <td>{{ $document->subject->nama_mapel ?? 'Perlu Verifikasi' }}</td>
                                <td>{{ $document->grade_level ? 'Kelas '.$document->grade_level : 'Perlu Verifikasi' }}</td>
                                <td>{{ $document->internal_notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:20px">Tidak ada dokumen yang perlu verifikasi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('headDocumentFiles');
    const count = document.getElementById('headDocumentFileCount');
    if (input && count) {
        input.addEventListener('change', function () {
            count.textContent = this.files.length ? `${this.files.length} file dipilih` : 'Belum ada file dipilih';
        });
    }
});
</script>
@endpush
