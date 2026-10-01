<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyLoginReward;
use Illuminate\Console\Command;

/**
 * ⚡ Perintah untuk membetulkan streak login yang salah.
 *
 * Latar belakang (bug 2 Okt 2026): streak di-reset ke 1 setiap hari karena
 * AuthController menulis users.last_login_date = HARI INI sebelum pemeriksaan
 * "apakah kemarin?". Akibatnya kolom users.login_streak berisi 1 untuk semua orang.
 *
 * Perintah ini menghitung ulang streak dari RIWAYAT BONUS LOGIN (login_rewards)
 * yang tidak terpengaruh bug tersebut, lalu menyimpannya kembali.
 */
class HitungUlangStreakLogin extends Command
{
    protected $signature = 'login:hitung-ulang-streak
                            {--user= : Email atau ID satu pengguna saja (opsional)}';

    protected $description = 'Hitung ulang streak login dari riwayat bonus login (membetulkan data lama akibat bug 2 Okt 2026)';

    public function handle(DailyLoginReward $reward): int
    {
        $query = User::query();

        if ($sasaran = $this->option('user')) {
            $query->where(function ($q) use ($sasaran) {
                $q->where('email', $sasaran);

                if (is_numeric($sasaran)) {
                    $q->orWhere('id', (int) $sasaran);
                }
            });
        }

        $daftar = $query->orderBy('name')->get();

        if ($daftar->isEmpty()) {
            $this->warn('Tidak ada pengguna yang cocok.');

            return self::SUCCESS;
        }

        $berubah = 0;
        $rincian = [];

        foreach ($daftar as $user) {
            $sebelum = (int) ($user->login_streak ?? 0);
            $sesudah = $reward->sinkronStreak($user);

            if ($sebelum !== $sesudah) {
                $berubah++;
                $rincian[] = [$user->name, $user->email, $sebelum, $sesudah];
            }
        }

        $this->info('Diperiksa: ' . $daftar->count() . ' pengguna | streak diperbaiki: ' . $berubah);

        if ($rincian !== []) {
            $this->table(['Nama', 'Email', 'Streak Lama', 'Streak Baru'], $rincian);
        } else {
            $this->line('Semua streak sudah sesuai riwayat.');
        }

        return self::SUCCESS;
    }
}
