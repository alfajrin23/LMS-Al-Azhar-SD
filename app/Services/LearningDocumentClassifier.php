<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\LearningDocument;
use App\Models\Mapel;
use ZipArchive;

class LearningDocumentClassifier
{
    public function classify(string $absolutePath, ?string $relativePath = null): array
    {
        $filename = $relativePath ? basename($relativePath) : basename($absolutePath);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $preview = $this->extractPreview($absolutePath, $extension);
        $haystack = $this->normalize(($relativePath ?: $filename).' '.$preview);

        $grade = $this->detectGrade($haystack);
        $subject = $this->detectSubject($haystack);
        $documentType = $this->detectDocumentType($haystack, $extension);
        $semester = $this->detectSemester($haystack);
        $academicYear = $this->detectAcademicYear($haystack);
        $notes = [];

        if (!$grade) {
            $notes[] = 'Kelas tidak ditemukan secara pasti dari nama file, folder, atau metadata yang terbaca.';
        }

        if (!$subject['subject_id']) {
            $notes[] = $subject['note'] ?: 'Mata pelajaran tidak ditemukan secara pasti pada master mapel.';
        } elseif ($subject['note']) {
            $notes[] = $subject['note'];
        }

        if (!$semester) {
            $notes[] = 'Semester tidak tercantum; dibiarkan kosong.';
        }

        if (!$academicYear) {
            $notes[] = 'Tahun ajaran tidak tercantum; dibiarkan kosong.';
        }

        $classIds = $grade ? $this->classIdsForGrade($grade) : [];
        if ($grade && empty($classIds)) {
            $notes[] = 'Master kelas untuk tingkat Kelas '.$grade.' belum tersedia.';
        } elseif ($grade) {
            $notes[] = 'Berlaku untuk seluruh Kelas '.$grade.'; file fisik tidak digandakan per rombel.';
        }

        $classificationStatus = ($grade && $subject['subject_id'])
            ? LearningDocument::STATUS_CLASSIFIED
            : LearningDocument::STATUS_NEEDS_VERIFICATION;

        return [
            'title' => $this->titleFromFilename($filename),
            'description' => null,
            'document_type' => $documentType,
            'subject_id' => $subject['subject_id'],
            'subject_name' => $subject['subject_name'],
            'school_level' => 'SD',
            'grade_level' => $grade ? (string) $grade : null,
            'class_ids' => $classIds,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'classification_status' => $classificationStatus,
            'is_sensitive' => $this->isSensitive($documentType, $haystack),
            'internal_notes' => implode(' ', array_unique(array_filter($notes))),
            'preview' => $preview,
        ];
    }

    public function extractPreview(string $path, string $extension): string
    {
        if (!is_file($path)) {
            return '';
        }

        return match ($extension) {
            'docx' => $this->extractDocxText($path),
            'xlsx', 'xlsm' => $this->extractXlsxText($path),
            'pptx' => $this->extractPptxText($path),
            'pdf' => $this->extractPdfMetadata($path),
            default => '',
        };
    }

    private function extractDocxText(string $path): string
    {
        return $this->readZipText($path, [
            'docProps/core.xml',
            'word/document.xml',
            'word/header1.xml',
            'word/footer1.xml',
        ]);
    }

    private function extractXlsxText(string $path): string
    {
        $entries = ['docProps/core.xml', 'xl/sharedStrings.xml'];
        for ($i = 1; $i <= 5; $i++) {
            $entries[] = 'xl/worksheets/sheet'.$i.'.xml';
        }

        return $this->readZipText($path, $entries);
    }

    private function extractPptxText(string $path): string
    {
        $entries = ['docProps/core.xml'];
        for ($i = 1; $i <= 10; $i++) {
            $entries[] = 'ppt/slides/slide'.$i.'.xml';
        }

        return $this->readZipText($path, $entries);
    }

    private function readZipText(string $path, array $entries): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $text = '';
        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml !== false) {
                $text .= ' '.$this->xmlToText($xml);
            }
        }
        $zip->close();

        return str($text)->squish()->limit(4000, '')->toString();
    }

    private function extractPdfMetadata(string $path): string
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return '';
        }

        $sample = fread($handle, 131072) ?: '';
        fclose($handle);

        $text = '';
        if (preg_match_all('/\/Title\s*\((.*?)\)/s', $sample, $matches)) {
            $text .= ' '.implode(' ', $matches[1]);
        }
        if (preg_match_all('/\/Subject\s*\((.*?)\)/s', $sample, $matches)) {
            $text .= ' '.implode(' ', $matches[1]);
        }

        $text .= ' '.preg_replace('/[^A-Za-z0-9\s_\-\/&().,:;\'"?]+/', ' ', $sample);

        return str($text)->squish()->limit(2000, '')->toString();
    }

    private function xmlToText(string $xml): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $xml) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return str($text)->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private function detectGrade(string $haystack): ?int
    {
        $patterns = [
            '/\bkelas\s*([1-6])\b/u',
            '/\bkelas([1-6])\b/u',
            '/\b([1-6])\s*sd\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack, $match)) {
                return (int) $match[1];
            }
        }

        return null;
    }

    private function detectSubject(string $haystack): array
    {
        $rules = [
            ['/matematika|\bmtk\b/u', 'Matematika', null],
            ['/bahasa\s*indonesia|\bb\.?\s*indo\b|\bb\s*indo\b|\bb\.ind\b/u', 'Bahasa Indonesia', null],
            ['/bahasa\s*inggris|\bb\.?\s*inggris\b|\binggris\b/u', 'Bahasa Inggris', null],
            ['/bahasa\s*arab/u', 'Bahasa Arab', null],
            ['/\bipas\b/u', 'IPAS', null],
            ['/pkn|pancasila|kewarganegaraan/u', 'Pendidikan Kewarganegaraan', 'Dinormalisasi dari PKN/Pendidikan Pancasila ke master Pendidikan Kewarganegaraan.'],
            ['/fiqih|fikih|fiqh/u', 'Fiqh', 'Dinormalisasi dari Fiqih/Fikih ke master Fiqh.'],
            ['/aqidah|akidah|akhlak/u', 'Aqidah/Akhlak', 'Dinormalisasi dari Aqidah/Akidah Akhlak ke master Aqidah/Akhlak.'],
            ['/al\s*qur|qur.?an|hadits|hadist/u', 'Baca Tulis Quran', 'Dinormalisasi dari Al Quran Hadits ke master Baca Tulis Quran; perlu konfirmasi bila sekolah memakai mapel Quran Hadits terpisah.'],
            ['/\bpjok\b|pendidikan\s*jasmani/u', 'PJOK', null],
            ['/informatika|\btik\b|teknologi\s*digital/u', 'Teknologi Digital', null],
            ['/seni\s*rupa|\bseni\b/u', 'Seni Rupa', 'Mapel Seni Rupa/Seni belum ditemukan pada master mapel; perlu verifikasi sebelum membuat master baru.'],
        ];

        foreach ($rules as [$pattern, $mapelName, $note]) {
            if (preg_match($pattern, $haystack)) {
                $mapel = Mapel::query()
                    ->whereRaw('LOWER(nama_mapel) = ?', [mb_strtolower($mapelName)])
                    ->first();

                return [
                    'subject_id' => $mapel?->id,
                    'subject_name' => $mapel?->nama_mapel ?: $mapelName,
                    'note' => $mapel ? $note : ($note ?: 'Mapel '.$mapelName.' belum ditemukan pada master mapel.'),
                ];
            }
        }

        return ['subject_id' => null, 'subject_name' => null, 'note' => null];
    }

    private function detectDocumentType(string $haystack, string $extension): string
    {
        if (preg_match('/kunci\s*jawaban|\bkunci\b/u', $haystack)) {
            return 'kunci_jawaban';
        }
        if (preg_match('/kisi\s*-?\s*kisi/u', $haystack)) {
            return 'kisi_kisi';
        }
        if (preg_match('/\bpsat\b/u', $haystack)) {
            return 'soal_psat';
        }
        if (preg_match('/\bpas\b/u', $haystack)) {
            return 'soal_pas';
        }
        if (preg_match('/\bpts\b/u', $haystack)) {
            return 'soal_pts';
        }
        if (preg_match('/\bsts\b|\basts\b/u', $haystack)) {
            return 'soal_sts';
        }
        if (preg_match('/\bsas\b|sumatif\s*akhir/u', $haystack)) {
            return 'soal_sas';
        }
        if (preg_match('/latihan\s*soal|soal\s*latihan/u', $haystack)) {
            return 'soal_latihan';
        }
        if (preg_match('/\bsoal\b|ujian|asesmen|assessment|\btka\b/u', $haystack)) {
            return 'soal_asesmen';
        }
        if (preg_match('/\blkpd\b|lembar\s*kerja|worksheet/u', $haystack)) {
            return 'lembar_kerja';
        }
        if (preg_match('/\btugas\b/u', $haystack)) {
            return 'tugas';
        }
        if (in_array($extension, ['ppt', 'pptx'], true) || preg_match('/presentasi|slide/u', $haystack)) {
            return 'presentasi';
        }
        if (preg_match('/\brppm\b|\brpp\b|modul\s*ajar|modul\s*pembelajaran|\brpm\b/u', $haystack)) {
            return 'modul_ajar';
        }
        if (preg_match('/capaian\s*pembelajaran|\bcp\b|program\s*semester|progam\s*semester/u', $haystack)) {
            return 'panduan_guru';
        }
        if (preg_match('/referensi|review/u', $haystack)) {
            return 'referensi';
        }
        if (preg_match('/\bbab\s*[0-9]/u', $haystack)) {
            return 'materi_pelajaran';
        }

        return 'materi_pelajaran';
    }

    private function detectSemester(string $haystack): ?string
    {
        if (preg_match('/semester\s*1|\bsem\s*1\b|ganjil/u', $haystack)) {
            return 'Ganjil';
        }
        if (preg_match('/semester\s*2|\bsem\s*2\b|genap/u', $haystack)) {
            return 'Genap';
        }

        return null;
    }

    private function detectAcademicYear(string $haystack): ?string
    {
        if (preg_match('/(20\d{2})\s*[\/\-]\s*(20\d{2})/u', $haystack, $match)) {
            return $match[1].'/'.$match[2];
        }

        return null;
    }

    private function classIdsForGrade(int $grade): array
    {
        return Kelas::query()
            ->where('jenjang', 'SD')
            ->where(function ($query) use ($grade) {
                $query->where('kode_kelas', 'like', $grade.'%')
                    ->orWhere('nama_kelas', 'like', 'Kelas '.$grade.'%');
            })
            ->pluck('id')
            ->all();
    }

    private function isSensitive(string $documentType, string $haystack): bool
    {
        $configured = (bool) (config('learning_documents.document_types.'.$documentType.'.sensitive') ?? false);

        return $configured || preg_match('/rahasia|ujian|kunci\s*jawaban|kisi\s*-?\s*kisi/u', $haystack) === 1;
    }

    private function titleFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return str($base)
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->title()
            ->toString();
    }

    private function normalize(string $value): string
    {
        return str($value)
            ->lower()
            ->replace(["\r", "\n", "\t", '_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }
}
