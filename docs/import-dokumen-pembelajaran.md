# Import Dokumen Pembelajaran

Folder sumber default:

```text
D:/LMS Al Azhar/MATERI SD AL AZHAR
```

Nilai tersebut dapat diubah melalui:

```env
LEARNING_DOCUMENT_SOURCE_PATH=
LEARNING_DOCUMENT_STORAGE_DIR=dokumen-pembelajaran
LEARNING_DOCUMENT_MAX_UPLOAD_KB=102400
```

## Command

Inventarisasi tanpa mengubah file:

```bash
php artisan documents:inventory
```

Import file asli ke private storage:

```bash
php artisan documents:import
```

Verifikasi checksum file tersimpan:

```bash
php artisan documents:verify
```

Sinkronisasi blok SQL modul dokumen:

```bash
php artisan documents:sync-sql
```

## Prinsip Import

- File sumber tidak diubah.
- Binary file disalin apa adanya ke `storage/app/private/dokumen-pembelajaran`.
- Checksum SHA-256 dihitung sebelum penyimpanan.
- Checksum file tersimpan dibandingkan ulang setelah penyimpanan.
- Duplikat dideteksi dari checksum dan ukuran file.
- Dokumen yang ambigu masuk status `needs_verification`.
- ZIP dibaca struktur entrinya bila memungkinkan, tetapi tetap disimpan sebagai ZIP final.
- RAR tetap disimpan sebagai RAR final.
