<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat Akun Admin
        User::create([
            'name' => 'Admin Media',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'division' => 'Administration',
            'status' => 'Aktif'
        ]);

        // Membuat Akun PJ Media (Project Manager)
        User::create([
            'name' => 'Ketua PJ Media',
            'email' => 'pj@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pj_media',
            'division' => 'Content Planning',
            'status' => 'Aktif'
        ]);

        // Membuat Akun Staff (Tim Editing)
        User::create([
            'name' => 'Staff Editing Zev',
            'email' => 'staff@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'division' => 'Editing',
            'status' => 'Aktif'
        ]);
    }
}