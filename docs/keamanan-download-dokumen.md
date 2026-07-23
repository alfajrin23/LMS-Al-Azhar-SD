# Keamanan Download Dokumen

File dokumen pembelajaran disimpan di disk `local`, yang pada project ini mengarah ke `storage/app/private`. File tidak ditempatkan di `public/storage`.

## Route Download

Route terproteksi:

```text
/dokumen-pembelajaran/{document}/download
```

Guru mengunduh dengan request approved:

```text
/dokumen-pembelajaran/{document}/download?request_id={request}
```

## Pemeriksaan Backend

Policy `LearningDocumentPolicy` memeriksa:

- user sudah login;
- role user;
- status guru aktif;
- dokumen aktif;
- dokumen level SD;
- file tersimpan tersedia;
- request milik guru yang login;
- status request `approved`;
- akses belum expired;
- akses belum revoked;
- batas download belum terlampaui;
- dokumen approved sesuai request.

Siswa, orang tua, dan siswa SMP tidak diberi akses route download dokumen pembelajaran SD.

## Audit

Setiap download guru mencatat:

- dokumen;
- request;
- user;
- waktu download;
- IP address;
- user agent;
- checksum saat download.

Checksum saat upload dibandingkan dengan checksum file tersimpan, dan command `documents:verify` dapat memeriksa ulang seluruh file.
