<?php

use App\Models\User;
use App\Services\LearningDocumentImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:inventory {source?} {--output=docs/inventaris-dokumen-pembelajaran-sd.md}', function (LearningDocumentImportService $service) {
    $source = $this->argument('source') ?: config('learning_documents.source_path');
    $rows = $service->scan($source);
    $output = $service->writeInventoryMarkdown($rows, $this->option('output'));

    $this->info('Inventory selesai.');
    $this->line('Sumber: '.$source);
    $this->line('Total file: '.$rows->count());
    $this->line('Output: '.$output);
})->purpose('Inventarisasi dokumen pembelajaran SD tanpa mengubah file asli');

Artisan::command('documents:import {source?} {--user=}', function (LearningDocumentImportService $service) {
    $source = $this->argument('source') ?: config('learning_documents.source_path');
    $user = $this->option('user')
        ? User::find((int) $this->option('user'))
        : User::query()->where('role', 'kepala_sekolah')->first();

    $summary = $service->import($source, $user);

    $this->info('Import selesai.');
    $this->line('Sumber: '.$source);
    $this->line('Total file: '.$summary['total']);
    $this->line('Dokumen baru: '.$summary['created']);
    $this->line('Sudah ada/diperbarui: '.$summary['updated_or_existing']);
    $this->line('Duplikat dilewati: '.$summary['duplicates_skipped']);
    $this->line('Perlu verifikasi: '.$summary['needs_verification']);
})->purpose('Import dokumen pembelajaran SD ke private storage dengan checksum');

Artisan::command('documents:verify', function (LearningDocumentImportService $service) {
    $summary = $service->verify();

    $this->info('Verifikasi checksum selesai.');
    $this->line('Dicek: '.$summary['checked']);
    $this->line('OK: '.$summary['ok']);
    $this->line('Missing: '.$summary['missing']);
    $this->line('Mismatch: '.$summary['mismatch']);

    foreach ($summary['details'] as [$id, $filename, $status]) {
        $this->warn('#'.$id.' '.$filename.' => '.$status);
    }
})->purpose('Verifikasi checksum dokumen tersimpan terhadap metadata database');

Artisan::command('documents:sync-sql {--path=../lms_alazharsd.sql}', function (LearningDocumentImportService $service) {
    $path = $service->syncSqlDump($this->option('path'));

    $this->info('SQL dump tersinkron.');
    $this->line('Path: '.$path);
})->purpose('Sinkronkan blok SQL modul dokumen pembelajaran ke lms_alazharsd.sql');
