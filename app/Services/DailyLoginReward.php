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

        // Akun klaim hari ini -> tidak dobel.
        if ($sudahDapat) {
            return null;
        }

        // ⚡ PERBAIKAN BUG 2 Okt 2026 ("streak stuck di 1"):
        // Streak dihitung dari RIWAYAT KLAIM, bukan dari kolom users.last_login_date.
        // Sebabnya: AuthController memanggil trackDailyLogin() lebih dulu, sehingga
        // last_login_date sudah berisi HARI INI saat pemeriksaan "apakah kemarin?"
        // dilakukan -> pemeriksaan itu selalu gagal dan streak selalu di-reset ke 1.
        // Dengan membaca riwayat, urutan pemanggilan jadi tidak berpengaruh, DAN
        // data lama yang salah ikut terbentuk ulang dengan sendirinya.
        $punyaTanggal = $this->tanggalKlaim($user->id);
        $streak       = $this->hitungMundur($punyaTanggal, Carbon::parse($hariIni)->subDay()) + 1;

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
     * Kumpulan tanggal klaim milik user (format Y-m-d), untuk pengecekan cepat.
     *
     * @return array<string,int>
     */
    private function tanggalKlaim(int $userId): array
    {
        return LoginReward::where('user_id', $userId)
            ->pluck('reward_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip()
            ->all();
    }

    /**
     * Hitung hari BERTURUT-TURUT mundur mulai dari tanggal tertentu.
     * (tanggal mulai tidak punya catatan -> hasil 0)
     */
    private function hitungMundur(array $punyaTanggal, Carbon $mulai): int
    {
        $jalan  = $mulai->copy()->startOfDay();
        $jumlah = 0;

        while (isset($punyaTanggal[$jalan->toDateString()])) {
            $jumlah++;
            $jalan->subDay();
        }

        return $jumlah;
    }

    /**
     * Streak terkini menurut riwayat:
     *  - sudah klaim hari ini  -> hitung mundur dari HARI INI
     *  - belum klaim hari ini  -> hitung mundur dari KEMARIN (streak masih hidup
     *    sampai hari ini berakhir — bukan direset tengah malam)
     *  - 0 = sudah bolong
     */
    public function streakSekarang(User $user, ?Carbon $acuan = null): int
    {
        $hariIni = ($acuan ?? now())->copy()->startOfDay();
        $punya   = $this->tanggalKlaim($user->id);

        $titik = isset($punya[$hariIni->toDateString()])
            ? $hariIni
            : $hariIni->copy()->subDay();

        return $this->hitungMundur($punya, $titik);
    }

    /**
     * Hitung ulang streak dari riwayat lalu simpan ke users.login_streak.
     * Dipakai perintah `login:hitung-ulang-streak` untuk membetulkan data lama.
     */
    public function sinkronStreak(User $user, ?Carbon $acuan = null): int
    {
        $streak = $this->streakSekarang($user, $acuan);

        $user->forceFill(['login_streak' => $streak])->save();

        return $streak;
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
            ->map(function (LoginReward $r) use ($user) {
                // ⚡ Streak ditampilkan dari hasil hitung riwayat, bukan kolom `streak`
                // yang tersimpan — data lama masih berisi 1 akibat bug 2 Okt 2026.
                $punya = $this->tanggalKlaim($user->id);
                $streak = $this->hitungMundur($punya, Carbon::parse($r->reward_date));

                return [
                    'title'     => 'Bonus Login Harian 🎁',
                    'project'   => 'Streak ' . $streak . ' hari',
                    'points'    => (int) $r->points,
                    'task_date' => $r->reward_date->format('d M Y'),
                    'status'    => 'Completed',
                    'is_login'  => true,
                ];
            })
            ->toArray();
    }
}
