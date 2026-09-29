<?php

namespace App\Services;

use App\Models\LoginReward;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * ⚡ FITUR: Daily Login Reward (2 poin, 1x sehari).
 *
 * Semua halaman (Dashboard, Workdesk, Monitoring, Riwayat Poin) memakai service ini
 * supaya angka poin login di mana pun berasal dari SATU sumber yang sama.
 */
class DailyLoginReward
{
    public const POINTS_PER_DAY = 2;

    /**
     * Klaim bonus login hari ini. Return NULL kalau hari ini sudah pernah dapat.
     *
     * @return array{points:int,streak:int}|null
     */
    public function claim(User $user, ?Carbon $acuan = null): ?array
    {
        $hariIni = ($acuan ?? now())->toDateString();

        // ⚡ pengaman lapis 1: cek riwayat
        $sudahDapat = LoginReward::where('user_id', $user->id)
            ->where('reward_date', $hariIni)
            ->exists();

        if ($sudahDapat) {
            return null;
        }

        // Streak: lanjut kalau login terakhir KEMARIN, kalau tidak -> mulai dari 1.
        $loginTerakhir = $user->last_login_date;
        $streakLama    = (int) ($user->login_streak ?? 0);

        $lanjut = $loginTerakhir
            && Carbon::parse($loginTerakhir)->toDateString() === Carbon::parse($hariIni)->subDay()->toDateString();

        $streak = $lanjut ? $streakLama + 1 : 1;

        try {
            LoginReward::create([
                'user_id'     => $user->id,
                'reward_date' => $hariIni,
                'points'      => self::POINTS_PER_DAY,
                'streak'      => $streak,
            ]);
        } catch (QueryException $e) {
            // ⚡ pengaman lapis 2: UNIQUE(user_id, reward_date) — kalau ada request balapan,
            // yang kalah tidak dapat apa-apa (tidak dobel).
            return null;
        }

        $user->forceFill(['login_streak' => $streak])->save();

        return ['points' => self::POINTS_PER_DAY, 'streak' => $streak];
    }

    /**
     * Peta poin login per anggota pada satu periode: [user_id => total poin].
     * Dipakai untuk MENGGABUNGKAN ke total poin di Dashboard/Workdesk/Monitoring.
     */
    public function pointsPerUser(Carbon $start, Carbon $end): array
    {
        return LoginReward::whereBetween('reward_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('user_id, SUM(points) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /**
     * Peta poin login per anggota PER BULAN: [user_id][Y-m => total poin].
     * Dipakai untuk grafik tren bulanan.
     */
    public function pointsPerUserPerMonth(Carbon $mulai, Carbon $selesai): array
    {
        $hasil = [];

        $baris = LoginReward::whereBetween('reward_date', [$mulai->toDateString(), $selesai->toDateString()])
            ->selectRaw("user_id, substr(reward_date, 1, 7) as bulan, SUM(points) as total")
            ->groupBy('user_id', 'bulan')
            ->get();

        foreach ($baris as $b) {
            $hasil[(int) $b->user_id][$b->bulan] = (int) $b->total;
        }

        return $hasil;
    }

    /**
     * Riwayat bonus login untuk modal "Riwayat Poin".
     */
    public function history(User $user, Carbon $start, Carbon $end): array
    {
        return LoginReward::where('user_id', $user->id)
            ->whereBetween('reward_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('reward_date')
            ->get()
            ->map(fn (LoginReward $r) => [
                'title'     => 'Bonus Login Harian 🎁',
                'project'   => 'Streak ' . (int) $r->streak . ' hari',
                'points'    => (int) $r->points,
                'task_date' => $r->reward_date->format('d M Y'),
                'status'    => 'Completed',
                'is_login'  => true,
            ])
            ->toArray();
    }
}
