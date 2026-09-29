<?php

namespace App\Console\Commands;

use App\Models\LoginReward;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * ⚡ Alat UJI COBA: bonus login harian hanya bisa didapat 1x sehari,
 * jadi kalau mau melihat animasinya lagi di hari yang sama, hapus dulu bonus hari ini.
 */
class LoginResetReward extends Command
{
    protected $signature = 'login:reset-reward
                            {email : Email akun yang mau direset}
                            {--semua : Hapus SEMUA riwayat bonus (bukan hanya hari ini)}';

    protected $description = 'Untuk uji coba: hapus bonus login hari ini supaya animasi bisa dilihat lagi';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Akun dengan email {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        // ---- mode: hapus semua riwayat ----
        if ($this->option('semua')) {
            $jumlah = LoginReward::where('user_id', $user->id)->delete();

            $user->forceFill(['login_streak' => 0])->save();

            $this->info("✅ Semua riwayat bonus {$user->email} dihapus ({$jumlah} baris). Streak direset ke 0.");
            $this->line('   Silakan logout lalu login lagi untuk melihat animasinya dari awal.');

            return self::SUCCESS;
        }

        // ---- mode default: hapus bonus HARI INI saja ----
        $jumlah = LoginReward::where('user_id', $user->id)
            ->where('reward_date', now()->toDateString())
            ->delete();

        // kembalikan streak ke kondisi sebelum hari ini
        $terakhir = LoginReward::where('user_id', $user->id)->orderByDesc('reward_date')->first();

        $user->forceFill([
            'login_streak'    => $terakhir?->streak ?? 0,
            // diset ke kemarin supaya login berikutnya MENAMBAH streak (animasinya terlihat naik)
            'last_login_date' => now()->subDay()->toDateString(),
        ])->save();

        if ($jumlah > 0) {
            $this->info("✅ Bonus login hari ini untuk {$user->email} sudah dihapus.");
        } else {
            $this->warn("ℹ️  {$user->email} memang belum mendapat bonus hari ini.");
        }

        $this->line('   Streak sekarang    : ' . $user->fresh()->login_streak . ' hari');
        $this->line('   Bonus hari ini     : belum ada (animasi akan muncul)');
        $this->line('   Langkah berikutnya : logout, lalu login lagi dengan akun itu.');

        return self::SUCCESS;
    }
}
