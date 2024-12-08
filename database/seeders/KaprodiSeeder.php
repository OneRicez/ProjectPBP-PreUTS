<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Kaprodi;
use Illuminate\Database\Seeder;

class KaprodiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kaprodi::create([
        //     'nidn' => '123456789012',
        //     'kode_prodi' => 'IFS1',
        //     'awal_jabatan' => '2024-08-10',
        //     'akhir_jabatan' => '2026-08-10',
        //     'created_at' => now(),
        // ]);
        Kaprodi::create([
            'nidn' => '123456789100',
            'kode_prodi' => 'KIMS1',
            'awal_jabatan' => '2024-08-10',
            'akhir_jabatan' => '2026-08-10',
            'created_at' => now(),
        ]);
    }
}
