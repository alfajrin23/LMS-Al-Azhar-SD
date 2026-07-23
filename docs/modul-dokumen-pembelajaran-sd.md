# Modul Dokumen Pembelajaran SD

Modul **Pusat Dokumen Pembelajaran SD** menyimpan file final sekolah dalam bentuk asli. File tidak dikonversi, tidak dirender ulang, dan tidak disalin menjadi HTML. Database hanya menyimpan metadata, checksum, dan lokasi private storage.

## Lokasi Menu

- Kepala Sekolah: `Dokumen Pembelajaran`
- Guru: `Permintaan Dokumen`

## Fitur Kepala Sekolah

- Upload satu atau banyak file.
- Upload file final `pdf`, `doc`, `docx`, `xls`, `xlsx`, `xlsm`, `ppt`, `pptx`, `jpg`, `jpeg`, `png`, `zip`, dan `rar`.
- Mengoreksi metadata dokumen.
- Menandai dokumen aktif/nonaktif dan sensitif/non-sensitif.
- Melihat dokumen perlu verifikasi.
- Melihat permintaan guru.
- Approve, reject, minta klarifikasi, dan cabut akses.
- Mengunduh file asli dari private storage.
- Melihat riwayat approval dan audit download.

## Fitur Guru

- Melihat katalog metadata dokumen non-sensitif yang relevan dengan kelas/mapel yang diajar.
- Mengajukan permintaan dokumen tertentu atau permintaan umum.
- Menyimpan draft.
- Melihat status permintaan.
- Memperbaiki permintaan yang diminta klarifikasi.
- Mengunduh dokumen hanya setelah request approved.
- Melihat riwayat download.

## Data Yang Disimpan

Tabel utama:

- `learning_document_categories`
- `learning_documents`
- `learning_document_classes`
- `learning_document_requests`
- `learning_document_downloads`
- `learning_document_approval_histories`

Relasi kelas memakai pivot agar satu file fisik dapat berlaku untuk banyak kelas/rombel tanpa penggandaan file.
