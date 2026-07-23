@php
    $docTypes = $learningDocumentTypes ?? [];
    $requestStats = $learningDocumentRequestStats ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0];
@endphp

<div x-data="{ requestSubtab: 'ajukan' }">
    <div class="content-header">
        <div>
            <h1>Permintaan Dokumen</h1>
            <p style="font-size:14px;color:var(--gray-400);margin-top:2px">Ajukan akses file pembelajaran asli kepada Kepala Sekolah</p>
        </div>
        <div class="header-right">
            <span class="badge light blue"><i class="fas fa-lock"></i> Download hanya setelah approved</span>
            <div class="avatar blue">{{ strtoupper(substr($guru->nama ?? 'G', 0, 1)) }}</div>
        </div>
    </div>

    <div class="grid-4" style="margin-bottom:16px">
        <div class="card" style="padding:14px">
            <div style="font-size:22px;font-weight:800;color:var(--orange)">{{ $requestStats['pending'] }}</div>
            <div style="font-size:12px;color:var(--gray-500);font-weight:700">Pending</div>
        </div>
        <div class="card" style="padding:14px">
            <div style="font-size:22px;font-weight:800;color:var(--teal)">{{ $requestStats['approved'] }}</div>
            <div style="font-size:12px;color:var(--gray-500);font-weight:700">Approved</div>
        </div>
        <div class="card" style="padding:14px">
            <div style="font-size:22px;font-weight:800;color:var(--red)">{{ $requestStats['rejected'] }}</div>
            <div style="font-size:12px;color:var(--gray-500);font-weight:700">Rejected</div>
        </div>
        <div class="card" style="padding:14px">
            <div style="font-size:22px;font-weight:800;color:var(--blue)">{{ ($approvedLearningDocumentRequests ?? collect())->count() }}</div>
            <div style="font-size:12px;color:var(--gray-500);font-weight:700">Dokumen Siap Diunduh</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach([
                'ajukan' => 'Ajukan Permintaan',
                'saya' => 'Permintaan Saya',
                'approved' => 'Dokumen Disetujui',
                'download' => 'Riwayat Download',
            ] as $key => $label)
                <button type="button" class="btn-small" @click="requestSubtab='{{ $key }}'"
                    :class="requestSubtab === '{{ $key }}' ? 'teal' : 'outline'"
                    style="border:1px solid var(--border);cursor:pointer;padding:7px 10px">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div x-show="requestSubtab === 'ajukan'">
        <div class="grid-2" style="align-items:start">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-paper-plane" style="color:var(--teal)"></i> Form Permintaan</h3>
                </div>
                <form method="POST" action="{{ route('guru.documents.requests.store') }}">
                    @csrf
                    <input type="hidden" name="submit_action" value="pending">
                    <div class="grid-2" style="gap:10px">
                        <div>
                            <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Kelas</label>
                            <select name="class_id" required style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                                <option value="">Pilih kelas</option>
                                @foreach($learningDocumentKelasOptions ?? [] as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Mata Pelajaran</label>
                            <select name="subject_id" required style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                                <option value="">Pilih mapel</option>
                                @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                                    <option value="{{ $mapel->id }}">{{ $mapel->nama_mapel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Jenis Dokumen</label>
                            <select name="document_type" required style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                                <option value="">Pilih jenis</option>
                                @foreach($docTypes as $code => $type)
                                    <option value="{{ $code }}">{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Tanggal Dibutuhkan</label>
                            <input type="date" name="required_at" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                        </div>
                    </div>
                    <div style="margin-top:10px">
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Judul atau Topik</label>
                        <input type="text" name="requested_title" required placeholder="Contoh: Soal STS Bahasa Indonesia Kelas 5"
                            style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px">
                    </div>
                    <div style="margin-top:10px">
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Alasan atau Keperluan</label>
                        <textarea name="purpose" rows="3" required style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px"></textarea>
                    </div>
                    <div style="margin-top:10px">
                        <label style="font-size:12px;font-weight:700;color:var(--gray-500)">Catatan Tambahan</label>
                        <textarea name="additional_notes" rows="2" style="width:100%;border:1px solid var(--border);border-radius:4px;padding:8px"></textarea>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px">
                        <button class="btn-small teal" style="border:none;cursor:pointer;padding:8px 14px"><i class="fas fa-paper-plane"></i> Kirim Request</button>
                        <button name="submit_action" value="draft" class="btn-small outline" style="cursor:pointer;padding:8px 14px">Simpan Draft</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="color:var(--blue)"></i> Katalog Metadata</h3>
                    <span class="badge light blue">Tanpa preview file</span>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" style="margin-bottom:10px">
                    <input type="hidden" name="tab" value="permintaan_dokumen">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select name="teacher_doc_subject_id" style="border:1px solid var(--border);border-radius:4px;padding:7px">
                            <option value="">Mapel</option>
                            @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                                <option value="{{ $mapel->id }}" @selected(request('teacher_doc_subject_id') == $mapel->id)>{{ $mapel->nama_mapel }}</option>
                            @endforeach
                        </select>
                        <select name="teacher_doc_type" style="border:1px solid var(--border);border-radius:4px;padding:7px">
                            <option value="">Jenis</option>
                            @foreach($docTypes as $code => $type)
                                <option value="{{ $code }}" @selected(request('teacher_doc_type') === $code)>{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                        <button class="btn-small outline" style="cursor:pointer">Filter</button>
                    </div>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Judul</th><th>Scope</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @forelse($learningDocumentCatalog ?? [] as $document)
                                <tr>
                                    <td>
                                        <strong>{{ $document->title }}</strong>
                                        <div style="font-size:11px;color:var(--gray-400)">{{ $document->documentTypeLabel() }}</div>
                                    </td>
                                    <td>
                                        {{ $document->classes->pluck('nama_kelas')->join(', ') ?: 'Semua kelas terkait' }}
                                        <div style="font-size:11px;color:var(--gray-400)">{{ $document->subject->nama_mapel ?? '-' }} / {{ $document->semester ?? '-' }} / {{ $document->academic_year ?? '-' }}</div>
                                    </td>
                                    <td><span class="badge light green">Tersedia</span></td>
                                    <td><button type="button" @click="requestSubtab='ajukan'" class="btn-small teal" style="border:none;cursor:pointer">Ajukan</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:20px">Katalog kosong atau dokumen sensitif disembunyikan</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ ($learningDocumentCatalog ?? null)?->links('vendor.pagination.custom') }}</div>
            </div>
        </div>
    </div>

    <div x-show="requestSubtab === 'saya'">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-clipboard-list" style="color:var(--teal)"></i> Permintaan Saya</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Request</th><th>Kebutuhan</th><th>Status</th><th>Catatan Kepala</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($learningDocumentRequests ?? [] as $docRequest)
                            <tr>
                                <td>
                                    <strong>{{ $docRequest->request_number }}</strong>
                                    <div style="font-size:11px;color:var(--gray-400)">{{ $docRequest->created_at?->format('d M Y') }}</div>
                                </td>
                                <td>
                                    {{ $docRequest->requested_title }}
                                    <div style="font-size:11px;color:var(--gray-400)">{{ $docRequest->kelas->nama_kelas ?? '-' }} / {{ $docRequest->subject->nama_mapel ?? '-' }}</div>
                                </td>
                                <td><span class="badge {{ $docRequest->status === 'approved' ? 'green' : ($docRequest->status === 'rejected' ? 'red' : 'orange') }}">{{ $docRequest->statusLabel() }}</span></td>
                                <td>{{ $docRequest->reviewer_notes ?: '-' }}</td>
                                <td>
                                    @if($docRequest->status === 'pending')
                                        <form method="POST" action="{{ route('guru.documents.requests.cancel', $docRequest) }}">
                                            @csrf
                                            <button class="btn-small outline" style="cursor:pointer">Batalkan</button>
                                        </form>
                                    @elseif(in_array($docRequest->status, ['draft','clarification_requested'], true))
                                        <details>
                                            <summary class="btn-small outline" style="cursor:pointer;list-style:none">Perbaiki</summary>
                                            <form method="POST" action="{{ route('guru.documents.requests.update', $docRequest) }}" style="margin-top:8px;width:260px">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="submit_action" value="pending">
                                                <select name="class_id" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    @foreach($learningDocumentKelasOptions ?? [] as $kelas)
                                                        <option value="{{ $kelas->id }}" @selected($docRequest->class_id === $kelas->id)>{{ $kelas->nama_kelas }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="subject_id" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    @foreach($learningDocumentMapelOptions ?? [] as $mapel)
                                                        <option value="{{ $mapel->id }}" @selected($docRequest->subject_id === $mapel->id)>{{ $mapel->nama_mapel }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="document_type" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                    @foreach($docTypes as $code => $type)
                                                        <option value="{{ $code }}" @selected($docRequest->document_type === $code)>{{ $type['label'] }}</option>
                                                    @endforeach
                                                </select>
                                                <input name="requested_title" value="{{ $docRequest->requested_title }}" required style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">
                                                <textarea name="purpose" required rows="2" style="width:100%;margin-bottom:6px;border:1px solid var(--border);border-radius:4px;padding:6px">{{ $docRequest->purpose }}</textarea>
                                                <button class="btn-small teal" style="border:none;cursor:pointer">Kirim Ulang</button>
                                            </form>
                                        </details>
                                    @else
                                        <small style="color:var(--gray-400)">-</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:20px">Belum ada permintaan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ ($learningDocumentRequests ?? null)?->links('vendor.pagination.custom') }}</div>
        </div>
    </div>

    <div x-show="requestSubtab === 'approved'">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-circle-down" style="color:var(--teal)"></i> Dokumen Siap Diunduh</h3>
                <span class="badge green">{{ ($approvedLearningDocumentRequests ?? collect())->count() }} aktif</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dokumen</th><th>Akses</th><th>Download</th></tr></thead>
                    <tbody>
                        @forelse($approvedLearningDocumentRequests ?? [] as $docRequest)
                            <tr>
                                <td>
                                    <strong>{{ $docRequest->approvedDocument->title ?? '-' }}</strong>
                                    <div style="font-size:11px;color:var(--gray-400)">{{ $docRequest->approvedDocument?->documentTypeLabel() }} / {{ $docRequest->subject->nama_mapel ?? '-' }}</div>
                                </td>
                                <td>
                                    <div>Unduh: {{ $docRequest->download_count }}{{ $docRequest->max_downloads ? ' / '.$docRequest->max_downloads : '' }}</div>
                                    <small style="color:var(--gray-400)">Berlaku sampai {{ $docRequest->access_expires_at?->format('d M Y H:i') ?? 'tanpa batas' }}</small>
                                </td>
                                <td>
                                    <a class="btn-small teal" style="text-decoration:none"
                                        href="{{ route('learning-documents.download', ['document' => $docRequest->approvedDocument, 'request_id' => $docRequest->id]) }}">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center;color:var(--gray-400);padding:20px">Belum ada dokumen approved yang aktif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div x-show="requestSubtab === 'download'">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-clock-rotate-left" style="color:var(--blue)"></i> Riwayat Download</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dokumen</th><th>Waktu</th><th>Checksum</th></tr></thead>
                    <tbody>
                        @forelse($learningDocumentDownloads ?? [] as $download)
                            <tr>
                                <td>{{ $download->document->title ?? '-' }}</td>
                                <td>{{ $download->downloaded_at?->format('d M Y H:i') }}</td>
                                <td><small>{{ substr($download->checksum_at_download, 0, 16) }}...</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center;color:var(--gray-400);padding:20px">Belum ada riwayat download</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
