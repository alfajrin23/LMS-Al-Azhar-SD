<?php

return [
    'source_path' => env('LEARNING_DOCUMENT_SOURCE_PATH', 'D:/LMS Al Azhar/MATERI SD AL AZHAR'),

    'storage_directory' => env('LEARNING_DOCUMENT_STORAGE_DIR', 'dokumen-pembelajaran'),

    'max_upload_kb' => (int) env('LEARNING_DOCUMENT_MAX_UPLOAD_KB', 102400),

    'allowed_extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'xlsm',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'zip',
        'rar',
    ],

    'allowed_mime_prefixes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.ms-',
        'application/vnd.openxmlformats-officedocument',
        'application/zip',
        'application/x-rar',
        'application/vnd.rar',
        'image/jpeg',
        'image/png',
    ],

    'document_types' => [
        'materi_pelajaran' => ['label' => 'Materi Pelajaran', 'sensitive' => false],
        'modul_ajar' => ['label' => 'Modul Ajar', 'sensitive' => false],
        'bahan_ajar' => ['label' => 'Bahan Ajar', 'sensitive' => false],
        'soal_latihan' => ['label' => 'Soal Latihan', 'sensitive' => false],
        'soal_harian' => ['label' => 'Soal Harian', 'sensitive' => true],
        'soal_sts' => ['label' => 'Soal STS', 'sensitive' => true],
        'soal_pts' => ['label' => 'Soal PTS', 'sensitive' => true],
        'soal_sas' => ['label' => 'Soal SAS', 'sensitive' => true],
        'soal_pas' => ['label' => 'Soal PAS', 'sensitive' => true],
        'soal_psat' => ['label' => 'Soal PSAT', 'sensitive' => true],
        'soal_asesmen' => ['label' => 'Soal Asesmen', 'sensitive' => true],
        'kisi_kisi' => ['label' => 'Kisi-Kisi', 'sensitive' => true],
        'kunci_jawaban' => ['label' => 'Kunci Jawaban', 'sensitive' => true],
        'lembar_kerja' => ['label' => 'Tugas dan Lembar Kerja', 'sensitive' => false],
        'tugas' => ['label' => 'Tugas', 'sensitive' => false],
        'presentasi' => ['label' => 'Presentasi', 'sensitive' => false],
        'panduan_guru' => ['label' => 'Panduan Guru', 'sensitive' => false],
        'referensi' => ['label' => 'Referensi', 'sensitive' => false],
        'dokumen_lainnya' => ['label' => 'Dokumen Lainnya', 'sensitive' => false],
    ],
];
