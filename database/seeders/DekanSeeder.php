<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dekan;
use Illuminate\Support\Facades\Hash;

class DekanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Dekan::create([
        //     'nidn' => '123456789011',
        //     'kode_fakultas' => 'FSM',
        //     'awal_jabatan' => '2024-08-10',
        //     'akhir_jabatan' => '2026-08-10',
        //     'created_at' => now(),
        // ]);

        Dekan::create([
            'nidn' => '123456789100',
            'kode_fakultas' => 'FSM',
            'awal_jabatan' => '2024-08-10',
            'akhir_jabatan' => '2026-08-10',
            'created_at' => now(),
        ]);
    }
}