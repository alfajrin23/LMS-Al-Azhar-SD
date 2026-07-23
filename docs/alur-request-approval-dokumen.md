# Alur Request Approval Dokumen

## Alur Utama

1. Kepala Sekolah mengunggah file final ke private storage.
2. Sistem menghitung checksum SHA-256 dan menyimpan metadata.
3. Guru mengajukan permintaan dokumen.
4. Request baru berstatus `pending`, kecuali disimpan sebagai `draft`.
5. Kepala Sekolah memilih dokumen yang sesuai.
6. Kepala Sekolah memilih `Setujui`, `Tolak`, atau `Minta Klarifikasi`.
7. Jika approved, guru dapat mengunduh file selama akses masih berlaku.
8. Setiap download guru dicatat di `learning_document_downloads`.

## Status Request

- `draft`
- `pending`
- `approved`
- `rejected`
- `clarification_requested`
- `revoked`
- `expired`

## Aturan Penting

- File tidak muncul di bagian **Dokumen Siap Diunduh** sebelum status `approved`.
- Request `pending`, `rejected`, `clarification_requested`, `revoked`, dan akses expired ditolak oleh backend.
- Guru hanya dapat mengunduh request miliknya sendiri.
- Kepala Sekolah dapat membatasi tanggal berlaku dan jumlah download.
