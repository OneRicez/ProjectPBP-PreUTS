<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserStarter extends Seeder
{
    public function run(): void
    {
        // User::create([
        //     'email' => 'admin@example.com',
        //     'password' => '12345', // Admin password
        //     'role' => 'admin', // Assign the admin role
        // ]);
        
        // User::create([
        //     'email' => 'bambang@lecturers.undip.ac.id',
        //     'password' => '12345', // Admin password
        //     'role' => 'dosen', // Assign the admin role
        // ]);

        // User::create([
        //     'email' => 'siti@lecturers.undip.ac.id',
        //     'password' => '12345', // Admin password
        //     'role' => 'dosen', // Assign the admin role
        // ]);

        User::create([
            'email' => 'bayu@lecturers.undip.ac.id',
            'password' => '12345', // Admin password
            'role' => 'dosen', // Assign the admin role
        ]);

        // User::create([
        //     'email' => 'gibran@students.undip.ac.id',
        //     'password' => '12345', // Admin password
        //     'role' => 'mahasiswa', // Assign the admin role
        // ]);

        // User::create([
        //     'email' => 'faisalrizki@students.undip.ac.id',
        //     'password' => '12345', // Admin password
        //     'role' => 'mahasiswa', // Assign the admin role
        // ]);
        
        // User::create([
        //     'email' => 'akademik@example.com',
        //     'password' => '12345', // Admin password
        //     'role' => 'akademik', // Assign the admin role
        // ]);
    }
}