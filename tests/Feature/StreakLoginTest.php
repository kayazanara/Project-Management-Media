<?php

namespace Tests\Feature;

use App\Models\LoginReward;
use App\Models\User;
use App\Services\DailyLoginReward;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 🐞 REGRESI BUG STREAK LOGIN (2 Okt 2026)
 *
 * Dilaporkan Zev: "streak login stuck di 1 terus".
 *
 * Penyebab: di AuthController::login() urutannya
 *     1. trackDailyLogin()   -> menulis users.last_login_date = HARI INI
 *     2. DailyLoginReward::claim() -> memeriksa "apakah last_login_date = KEMARIN?"
 * Karena tanggalnya sudah ditimpa jadi hari ini, pemeriksaan "kemarin" SELALU gagal,
 * jadi streak di-reset ke 1 setiap hari (poin 2/hari tetap masuk, jadi tidak kelihatan).
 *
 * Semua tes di sini memakai JALUR LOGIN ASLI (POST /login), bukan memanggil service
 * langsung — itu sebabnya bug ini lolos dari tes lama.
 */
class StreakLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestAccountSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function akunUji(): User
    {
        return User::where('email', 'anggota.lapangan1@test.com')->firstOrFail();
    }

    /** Login seperti pengguna asli pada tanggal tertentu. */
    private function loginDiTanggal(string $tanggal): void
    {
        Carbon::setTestNow(Carbon::parse($tanggal . ' 08:00:00'));

        $this->post('/login', [
            'email'    => 'anggota.lapangan1@test.com',
            'password' => 'password',
        ])->assertRedirect();

        // keluar lagi supaya bisa login ulang di hari berikutnya
        $this->post('/logout');
    }

    // ==========================================================
    // INTI BUG: 5 HARI BERTURUT-TURUT HARUS JADI STREAK 5
    // ==========================================================

    public function test_login_lima_hari_berturut_turut_menaikkan_streak(): void
    {
        $harapan = [
            '2026-09-25' => 1,
            '2026-09-26' => 2,
            '2026-09-27' => 3,
            '2026-09-28' => 4,
            '2026-09-29' => 5,
        ];

        foreach ($harapan as $tanggal => $streakSeharusnya) {
            $this->loginDiTanggal($tanggal);

            $this->assertSame(
                $streakSeharusnya,
                (int) $this->akunUji()->fresh()->login_streak,
                "Login berturut-turut sampai {$tanggal} seharusnya streak {$streakSeharusnya}"
            );
        }

        $this->assertSame(5, LoginReward::where('user_id', $this->akunUji()->id)->count(), 'Harus ada 5 catatan bonus login');
    }

    public function test_streak_naik_walau_last_login_date_sudah_diisi_hari_ini(): void
    {
        // Persis urutan di AuthController — inti bug-nya
        $user = $this->akunUji();

        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00'));
        app(DailyLoginReward::class)->claim($user);

        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00'));
        $user->fresh()->trackDailyLogin();          // last_login_date = HARI INI
        $hasil = app(DailyLoginReward::class)->claim($user->fresh());

        $this->assertNotNull($hasil, 'Klaim hari berikutnya harus berhasil');
        $this->assertSame(2, $hasil['streak'], 'Streak harus lanjut jadi 2, bukan reset ke 1');
    }

    // ==========================================================
    // BOLONG -> RESET
    // ==========================================================

    public function test_streak_kembali_ke_satu_kalau_ada_hari_yang_bolong(): void
    {
        $this->loginDiTanggal('2026-09-25');
        $this->loginDiTanggal('2026-09-26');
        $this->assertSame(2, (int) $this->akunUji()->fresh()->login_streak);

        // 27 Sep tidak login
        $this->loginDiTanggal('2026-09-28');

        $this->assertSame(1, (int) $this->akunUji()->fresh()->login_streak, 'Bolong sehari harus mulai dari 1 lagi');
    }

    // ==========================================================
    // DATA LAMA YANG SUDAH SALAH (semua bernilai 1) DIHITUNG ULANG
    // ==========================================================

    public function test_streak_data_lama_dihitung_ulang_dari_riwayat(): void
    {
        $user = $this->akunUji();

        // Riwayat nyata: 3 hari berturut-turut sampai kemarin, TAPI kolom streak-nya salah (=1)
        foreach (['2026-09-26', '2026-09-27', '2026-09-28'] as $tgl) {
            LoginReward::create([
                'user_id'     => $user->id,
                'reward_date' => $tgl,
                'points'      => 2,
                'streak'      => 1, // nilai salah warisan bug
            ]);
        }
        $user->forceFill(['login_streak' => 1, 'last_login_date' => '2026-09-28'])->save();

        $this->loginDiTanggal('2026-09-29');

        $this->assertSame(
            4,
            (int) $user->fresh()->login_streak,
            'Riwayat 3 hari berturut + hari ini harus dihitung jadi streak 4 (self-healing)'
        );
    }

    public function test_sudah_klaim_hari_ini_tidak_dapat_dobel(): void
    {
        $user = $this->akunUji();

        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00'));
        $pertama = app(DailyLoginReward::class)->claim($user);
        $kedua   = app(DailyLoginReward::class)->claim($user->fresh());

        $this->assertNotNull($pertama);
        $this->assertNull($kedua, 'Klaim kedua di hari yang sama harus ditolak');
        $this->assertSame(1, LoginReward::where('user_id', $user->id)->count());
    }

    // ==========================================================
    // PERINTAH HITUNG ULANG UNTUK SEMUA ORANG
    // ==========================================================

    public function test_perintah_artisan_hitung_ulang_streak_semua_user(): void
    {
        $user = $this->akunUji();

        // 3 hari berturut sampai KEMARIN dari tanggal "hari ini"
        Carbon::setTestNow(Carbon::parse('2026-10-02 08:00:00'));

        foreach (['2026-09-30', '2026-10-01'] as $tgl) {
            LoginReward::create([
                'user_id'     => $user->id,
                'reward_date' => $tgl,
                'points'      => 2,
                'streak'      => 1,
            ]);
        }
        $user->forceFill(['login_streak' => 1])->save();

        $kode = Artisan::call('login:hitung-ulang-streak');

        $this->assertSame(0, $kode, 'Perintah harus selesai tanpa error');
        $this->assertSame(
            2,
            (int) $user->fresh()->login_streak,
            'Perintah harus mengubah streak jadi 2 (riwayat 30 Sep + 1 Okt, hari ini belum login)'
        );
    }

    public function test_perintah_hitung_ulang_mengosongkan_streak_yang_sudah_bolong(): void
    {
        $user = $this->akunUji();
        Carbon::setTestNow(Carbon::parse('2026-10-02 08:00:00'));

        // Riwayat terakhir 27 Sep (sudah lama bolong), streak di kolom masih 5 (salah)
        LoginReward::create(['user_id' => $user->id, 'reward_date' => '2026-09-27', 'points' => 2, 'streak' => 5]);
        $user->forceFill(['login_streak' => 5])->save();

        Artisan::call('login:hitung-ulang-streak');

        $this->assertSame(0, (int) $user->fresh()->login_streak, 'Streak yang sudah bolong harus jadi 0');
    }
}
