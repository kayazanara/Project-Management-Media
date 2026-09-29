<?php

namespace Tests\Feature;

use App\Models\LoginReward;
use App\Models\User;
use App\Services\DailyLoginReward;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ FITUR BARU: Daily Login Reward — 2 poin untuk login pertama tiap hari (1x sehari).
 */
class DailyLoginRewardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->seed(TestAccountSeeder::class);
    }

    private function anggota(): User
    {
        return User::where('division_role', 'anggota')->where('status', 'Aktif')->firstOrFail();
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    /** Login lewat route asli pakai password yang kita set sendiri. */
    private function loginViaRoute(User $user)
    {
        $user->forceFill(['password' => Hash::make('rahasia123')])->save();

        return $this->post('/login', [
            'email'    => $user->email,
            'password' => 'rahasia123',
        ]);
    }

    // ============================================================
    // 1. DAPAT POIN SAAT LOGIN PERTAMA
    // ============================================================

    public function test_login_pertama_hari_itu_dapat_2_poin_dan_memicu_animasi(): void
    {
        $user = $this->anggota();

        $res = $this->loginViaRoute($user);

        $res->assertRedirect('/dashboard');
        $res->assertSessionHas('show_welcome', true);

        $reward = session('login_reward');
        $this->assertSame(2, $reward['points'], 'Bonus = 2 poin.');
        $this->assertSame(1, $reward['streak'], 'Streak pertama mulai dari 1.');

        $this->assertDatabaseHas('login_rewards', [
            'user_id' => $user->id,
            'points'  => 2,
            'streak'  => 1,
        ]);

        $this->assertSame(1, (int) $user->fresh()->login_streak);
    }

    public function test_overlay_animasi_menampilkan_streak_dan_poin(): void
    {
        $user = $this->anggota();

        $this->loginViaRoute($user);

        $res = $this->actingAs($user)->get('/dashboard');
        $res->assertOk();

        $res->assertSee('Streak Login', false);
        $res->assertSee('streakCounter', false);   // angka yang bergulir
        $res->assertSee('+2', false);              // poin hari ini

        // ⚡ animasi 4 babak: bergulir ke bawah -> melambat -> MELEDAK -> poin muncul
        $res->assertSee('rollDown', false);        // angka masuk dari atas (efek scroll ke bawah)
        $res->assertSee('burst-ring', false);      // cincin ledakan
        $res->assertSee('confetti', false);        // confetti meriah
        $res->assertSee('explode', false);         // pop angka final
        $res->assertSee('poinPop', false);         // poin muncul setelah ledakan

        // ⚡ PENTING: pemudar otomatis overlay bawaan harus DIMATIKAN, kalau tidak
        // overlay hilang di 2,4 detik dan animasi bonus tidak pernah kelihatan.
        $res->assertSee('#welcomeOverlay { animation: none !important; opacity: 1 !important; }', false);

        // ⚡ TRANSISI KELUAR: setelah poin muncul, overlay MEMUDAR (bukan hilang mendadak)
        // dan konten dashboard masuk halus.
        $res->assertSee('memudar', false);         // class pemudar overlay
        $res->assertSee('kontenMasuk', false);     // animasi konten dashboard
        $res->assertSee('tutupOverlay', false);    // fungsi penutup dengan transisi
    }

    // ============================================================
    // 2. HANYA 1x SEHARI
    // ============================================================

    public function test_login_kedua_di_hari_yang_sama_tidak_dapat_poin_dan_tanpa_overlay(): void
    {
        $user = $this->anggota();

        app(DailyLoginReward::class)->claim($user);   // sudah dapat hari ini

        $res = $this->loginViaRoute($user->fresh());

        $res->assertSessionHas('show_welcome', false);
        $this->assertNull(session('login_reward'));
        $this->assertSame(1, LoginReward::where('user_id', $user->id)->count());
    }

    public function test_database_menolak_bonus_ganda_di_tanggal_yang_sama(): void
    {
        $user = $this->anggota();

        app(DailyLoginReward::class)->claim($user);

        $this->expectException(\Illuminate\Database\QueryException::class);

        LoginReward::create([
            'user_id'     => $user->id,
            'reward_date' => now()->toDateString(),
            'points'      => 2,
            'streak'      => 1,
        ]);
    }

    // ============================================================
    // 3. STREAK
    // ============================================================

    public function test_streak_naik_kalau_kemarin_login(): void
    {
        $user = $this->anggota();
        $user->forceFill([
            'login_streak'    => 4,
            'last_login_date' => now()->subDay()->toDateString(),
        ])->save();

        $reward = app(DailyLoginReward::class)->claim($user->fresh());

        $this->assertSame(5, $reward['streak'], 'Login kemarin -> streak lanjut (4 -> 5).');
        $this->assertSame(5, (int) $user->fresh()->login_streak);
    }

    public function test_streak_reset_kalau_bolong_sehari(): void
    {
        $user = $this->anggota();
        $user->forceFill([
            'login_streak'    => 9,
            'last_login_date' => now()->subDays(3)->toDateString(),
        ])->save();

        $reward = app(DailyLoginReward::class)->claim($user->fresh());

        $this->assertSame(1, $reward['streak'], 'Bolong > 1 hari -> streak mulai dari 1 lagi.');
    }

    // ============================================================
    // 4. POIN BENAR-BENAR MASUK KE TOTAL & RIWAYAT
    // ============================================================

    public function test_poin_login_masuk_total_poin_di_monitoring(): void
    {
        $user = $this->anggota();

        app(DailyLoginReward::class)->claim($user);

        $res = $this->actingAs($this->pembina())->get('/monitoring');
        $res->assertOk();

        $baris = collect($res->viewData('workload'))->firstWhere('id', $user->id);

        $this->assertNotNull($baris, 'Anggota harus muncul di Workload Balance.');
        $this->assertSame(2, (int) $baris['points'], 'Total poin harus memuat bonus login (2).');
    }

    public function test_poin_login_muncul_di_riwayat_poin(): void
    {
        $user = $this->anggota();

        app(DailyLoginReward::class)->claim($user);

        $res = $this->actingAs($this->pembina())->getJson("/monitoring/point-history/{$user->id}?days=7");
        $res->assertOk();

        $items = collect($res->json('items'));

        $this->assertTrue(
            $items->contains(fn ($i) => str_contains($i['title'], 'Bonus Login')),
            'Riwayat Poin harus menjelaskan bonus login (biar selisih angka bisa dipertanggungjawabkan).'
        );

        $this->assertSame(2, (int) $res->json('total_points'));
    }

    public function test_poin_login_masuk_poin_anggota_di_dashboard(): void
    {
        $user = $this->anggota();

        app(DailyLoginReward::class)->claim($user);

        // anggota ini tidak punya task -> muncul di daftar karena bonus login
        $this->assertSame(
            2,
            app(DailyLoginReward::class)->pointsPerUser(now()->subDays(6), now())[$user->id] ?? 0
        );

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
