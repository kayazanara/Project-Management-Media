<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Hash;

class TestAccountSeeder extends Seeder
{
    /**
     * Buat akun test untuk semua role dalam workflow.
     * Password semua akun: "password"
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $accounts = [
            // ─── MANAGER ───
            [
                'name'          => 'Test Pembina Media',
                'email'         => 'pembina@test.com',
                'role'          => 'Ustadz',
                'division'      => 'Pembina Media',
                'division_role' => 'pembina',
                'phone'         => '628111111001',
                'jabatan'       => 'Pembina Divisi Media',
            ],
            [
                'name'          => 'Test Ketua Media',
                'email'         => 'ketua@test.com',
                'role'          => 'Ustadz',
                'division'      => 'Ketua Media',
                'division_role' => 'ketua_media',
                'phone'         => '628111111002',
                'jabatan'       => 'Ketua Divisi Media',
            ],

            // ─── KEPALA DIVISI (1 per divisi) ───
            [
                'name'          => 'Kepala Lapangan',
                'email'         => 'kepala.lapangan@test.com',
                'role'          => 'Santri',
                'division'      => 'Lapangan',
                'division_role' => 'kepala_divisi',
                'phone'         => '628111111101',
                'jabatan'       => 'Kepala Tim Lapangan',
            ],
            [
                'name'          => 'Kepala Editing',
                'email'         => 'kepala.editing@test.com',
                'role'          => 'Santri',
                'division'      => 'Editing',
                'division_role' => 'kepala_divisi',
                'phone'         => '628111111102',
                'jabatan'       => 'Kepala Tim Editing',
            ],
            [
                'name'          => 'Kepala Broadcast',
                'email'         => 'kepala.broadcast@test.com',
                'role'          => 'Santri',
                'division'      => 'Broadcast',
                'division_role' => 'kepala_divisi',
                'phone'         => '628111111103',
                'jabatan'       => 'Kepala Tim Broadcast',
            ],
            [
                'name'          => 'Kepala AI Research',
                'email'         => 'kepala.ai@test.com',
                'role'          => 'Santri',
                'division'      => 'AI Research',
                'division_role' => 'kepala_divisi',
                'phone'         => '628111111104',
                'jabatan'       => 'Kepala Tim AI Research',
            ],

            // ─── ANGGOTA (2 per divisi) ───
            [
                'name'          => 'Anggota Lapangan 1',
                'email'         => 'anggota.lapangan1@test.com',
                'role'          => 'Santri',
                'division'      => 'Lapangan',
                'division_role' => 'anggota',
                'phone'         => '628111111201',
            ],
            [
                'name'          => 'Anggota Lapangan 2',
                'email'         => 'anggota.lapangan2@test.com',
                'role'          => 'Santri',
                'division'      => 'Lapangan',
                'division_role' => 'anggota',
                'phone'         => '628111111202',
            ],
            [
                'name'          => 'Anggota Editing 1',
                'email'         => 'anggota.editing1@test.com',
                'role'          => 'Santri',
                'division'      => 'Editing',
                'division_role' => 'anggota',
                'phone'         => '628111111203',
            ],
            [
                'name'          => 'Anggota Editing 2',
                'email'         => 'anggota.editing2@test.com',
                'role'          => 'Santri',
                'division'      => 'Editing',
                'division_role' => 'anggota',
                'phone'         => '628111111204',
            ],
            [
                'name'          => 'Anggota Broadcast 1',
                'email'         => 'anggota.broadcast1@test.com',
                'role'          => 'Santri',
                'division'      => 'Broadcast',
                'division_role' => 'anggota',
                'phone'         => '628111111205',
            ],
            [
                'name'          => 'Anggota AI 1',
                'email'         => 'anggota.ai1@test.com',
                'role'          => 'Santri',
                'division'      => 'AI Research',
                'division_role' => 'anggota',
                'phone'         => '628111111206',
            ],
        ];

        foreach ($accounts as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'          => $data['name'],
                    'password'      => $password,
                    'role'          => $data['role'],
                    'division'      => $data['division'],
                    'division_role' => $data['division_role'],
                    'phone'         => PhoneHelper::normalize($data['phone']),
                    'status'        => 'Aktif',
                    'jabatan'       => $data['jabatan'] ?? null,
                ]
            );

            $this->command->info("✅ {$user->name} ({$user->email}) — {$user->division} / {$user->division_role}");
        }

        $this->command->newLine();
        $this->command->info("================================================");
        $this->command->info("🔑 SEMUA AKUN PASSWORD: password");
        $this->command->info("================================================");
        $this->command->newLine();
        $this->command->info("📋 LOGIN URL: " . url('/login'));
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Pembina Media', 'pembina@test.com', 'password'],
                ['Ketua Media', 'ketua@test.com', 'password'],
                ['Kepala Lapangan', 'kepala.lapangan@test.com', 'password'],
                ['Kepala Editing', 'kepala.editing@test.com', 'password'],
                ['Kepala Broadcast', 'kepala.broadcast@test.com', 'password'],
                ['Kepala AI Research', 'kepala.ai@test.com', 'password'],
                ['Anggota Lapangan 1', 'anggota.lapangan1@test.com', 'password'],
                ['Anggota Editing 1', 'anggota.editing1@test.com', 'password'],
                ['Anggota Broadcast 1', 'anggota.broadcast1@test.com', 'password'],
                ['Anggota AI 1', 'anggota.ai1@test.com', 'password'],
            ]
        );
    }
}