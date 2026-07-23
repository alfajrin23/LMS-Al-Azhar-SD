# Database Sync Dokumen SD

Perubahan database modul dokumen pembelajaran dibuat dalam migration:

```text
database/migrations/2026_07_23_080000_create_learning_document_center_tables.php
```

Seeder terkait:

```text
database/seeders/LearningDocumentCategorySeeder.php
database/seeders/LearningDocumentDemoSeeder.php
```

## Tabel

- `learning_document_categories`
- `learning_documents`
- `learning_document_classes`
- `learning_document_requests`
- `learning_document_downloads`
- `learning_document_approval_histories`

## Sinkronisasi

SQLite lokal diperbarui melalui:

```bash
php artisan migrate:fresh --seed
php artisan documents:import
```

File dump MySQL-style `../lms_alazharsd.sql` diperbarui dengan:

```bash
php artisan documents:sync-sql
```

Command tersebut menyisipkan blok bertanda:

```sql
-- BEGIN LEARNING DOCUMENT CENTER SYNC
-- END LEARNING DOCUMENT CENTER SYNC
```

Blok ini memuat DDL tabel dokumen, index, foreign key, kategori dokumen, metadata dokumen terimpor, relasi kelas, request, download log, dan riwayat approval. Binary file tidak dimasukkan ke SQL; SQL hanya memuat metadata dan storage path.
