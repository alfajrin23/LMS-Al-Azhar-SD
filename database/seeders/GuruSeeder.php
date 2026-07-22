<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Mapel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruSeeder extends Seeder
{
    public function run(): void
    {
        $this->correctKhoirunnisa();

        $gurus = [
            'Sahrial Pulungan, M.Pd' => ['Karya Tulis Ilmiah', 'Karya Tulis'],
            'Riva Riana, S.Pd' => ['Teknologi Digital', 'Ilmu Pengetahuan Alam', 'PJOK'],
            'Adinda Oktavhiani S.Pd' => ['Bahasa Arab', 'Bahasa Indonesia', 'Baca Tulis Quran'],
            'Intan Kusuma Dera, S.Ag' => ['Tahfidzul Qur\'an'],
            'Vika Wati Dzulciha, S.Ag' => ['Project Based Learning'],
            'Irnika Widiyan Dini, S.Li' => ['Bahasa Inggris'],
            'Ajeng Putyri Aryantika, S.Pd' => ['Matematika'],
            'Nurhayati, S.Ag' => ['Aqidah', 'Pendidikan Agama Islam', 'Fiqh', 'Tahfidzul Qur\'an'],
            'Sri Wanti Maulani, S.Pd' => ['Ilmu Pengetahuan Sosial', 'Tahfidzul Qur\'an'],
            'Ai Sunariah, S.Pd' => ['Tahfidzul Qur\'an'],
            'Khoirunnisa, S.Ag' => ['Tahfidzul Qur\'an'],


            'Nu Rahma, S.Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika'],
            'M. Yoga Zulfikar, S. Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'PJOK'],
            'Mardiyani, S.Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Tahfidzul Qur\'an'],
            'Nur Halimah, S.Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Pendidikan Kewarganegaraan', 'Bahasa Arab'],
            'Neng Bilqis' => [],
            'Rafi Annur Jamal, S.Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Pendidikan Kewarganegaraan', 'Bahasa Arab', 'TIK'],
            'Evi Lathifah, S.Pd' => ['Aqidah/Akhlak', 'Fiqh', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Pendidikan Kewarganegaraan', 'Bahasa Arab'],
            'Mia Marisha, S.Pd' => ['Pendidikan Agama Islam', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Pendidikan Kewarganegaraan', 'Bahasa Arab'],
            'Siti Jauriyah, S.Pd' => ['Pendidikan Agama Islam', 'Bahasa Indonesia', 'IPAS', 'Matematika', 'Pendidikan Kewarganegaraan'],
            'Eka Rahmawati, S.Pd' => ['Matematika', 'Bahasa Inggris'],
            'Muhammad Rifone Afrizal, S.Pd' => ['Bahasa Inggris'],


            'Guru Pendamping SD' => [
                'Dhuha Time',
                'Upacara / Pentas Seni',
                'Tahfidzul Qur\'an',
                'Bahasa Indonesia',
                'Matematika',
                'Qailullah',
                'Sholat dan Makan',
                'Pulang / Penjemputan Orang Tua',
                'Fiqh',
                'Kegiatan Pramuka',
                'Transisi / Pindah ke Kelas',
                'Bahasa Inggris',
                'Aqidah/Akhlak',
                'PJOK',
                'Bina Pribadi Islam',
                'Qadhaya Rawa\'i',
                'IPAS',
                'Bahasa Arab',
            ],
        ];

        $nipCounter = 1001;

        foreach ($gurus as $namaGuru => $mapels) {
            $email = $this->emailFor($namaGuru);
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $namaGuru,
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'role' => 'guru',
                ]);
            } else {
                $user->update([
                    'name' => $namaGuru,
                    'role' => 'guru',
                ]);
            }

            $guru = Guru::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nip' => $this->nipFor($namaGuru, $nipCounter),
                    'nama' => $namaGuru,
                    'status' => 'aktif',
                ]
            );

            $nipCounter++;

            $mapelIds = collect($mapels)
                ->map(fn ($namaMapel) => Mapel::where('nama_mapel', $namaMapel)->value('id'))
                ->filter()
                ->values()
                ->all();

            $guru->mapels()->sync($mapelIds);
            if (!$guru->mapel_id && !empty($mapelIds)) {
                $guru->update(['mapel_id' => $mapelIds[0]]);
            }
        }
    }

    private function correctKhoirunnisa(): void
    {
        $oldEmail = 'khairunisa@alazharjayaindonesia.sch.id';
        $newEmail = 'khoirunnisa@alazharjayaindonesia.sch.id';
        $generatedEmail = 'khoirunnisa.sag@alazharjayaindonesia.sch.id';

        $user = User::where('email', $oldEmail)
            ->orWhere('email', $newEmail)
            ->orWhere('email', $generatedEmail)
            ->orWhere('name', 'Khairunisa')
            ->first();

        if ($user) {
            $user->update([
                'name' => 'Khoirunnisa, S.Ag',
                'email' => $newEmail,
                'role' => 'guru',
            ]);

            Guru::where('user_id', $user->id)
                ->orWhere('nama', 'Khairunisa')
                ->update(['nama' => 'Khoirunnisa, S.Ag']);
        }
    }

    private function emailFor(string $name): string
    {
        if ($name === 'Khoirunnisa, S.Ag') {
            return 'khoirunnisa@alazharjayaindonesia.sch.id';
        }

        return Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->squish()
            ->replace(' ', '.')
            ->append('@alazharjayaindonesia.sch.id')
            ->toString();
    }

    private function nipFor(string $name, int $counter): string
    {
        if ($name === 'Guru Demo SDIT') {
            return 'GURU-DEMO-001';
        }

        return (string) $counter;
    }
}
