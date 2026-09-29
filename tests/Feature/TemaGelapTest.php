<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚡ FITUR BARU: Mode Gelap (dark mode) + switch di samping profil.
 *
 * Yang dikunci tes ini:
 *  - pilihan tema tersimpan per AKUN (ikut ke perangkat lain)
 *  - nilai tema tidak dikenal ditolak
 *  - halaman memakai data-theme sesuai preferensi akun
 *  - ada skrip anti-kedip (localStorage) & tombol switch
 *  - CSS mode gelap ikut dimuat di semua halaman, termasuk halaman login
 *  - halaman Cetak tetap dipaksa terang
 */
class TemaGelapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestAccountSeeder::class);
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    // ==========================================================
    // SIMPAN PILIHAN
    // ==========================================================

    public function test_pilihan_tema_tersimpan_di_akun(): void
    {
        $user = $this->pembina();

        $res = $this->actingAs($user)
            ->patchJson('/preferensi/tema', ['theme' => 'dark']);

        $res->assertOk()->assertJson(['success' => true, 'theme' => 'dark']);
        $this->assertSame('dark', $user->fresh()->theme_mode);
    }

    public function test_bisa_kembali_ke_terang(): void
    {
        $user = $this->pembina();
        $user->update(['theme_mode' => 'dark']);

        $this->actingAs($user)->patchJson('/preferensi/tema', ['theme' => 'light'])->assertOk();

        $this->assertSame('light', $user->fresh()->theme_mode);
    }

    public function test_tema_tidak_dikenal_ditolak(): void
    {
        $user = $this->pembina();

        $this->actingAs($user)
            ->patchJson('/preferensi/tema', ['theme' => 'neon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');

        $this->assertSame('light', $user->fresh()->theme_mode, 'Tema tidak sah tidak boleh mengubah data');
    }

    public function test_tamu_tidak_bisa_mengubah_tema(): void
    {
        $this->patchJson('/preferensi/tema', ['theme' => 'dark'])->assertStatus(401);
    }

    public function test_user_lama_otomatis_terang(): void
    {
        $this->assertSame('light', $this->pembina()->theme_mode, 'Default harus terang (tampilan lama tidak berubah)');
    }

    // ==========================================================
    // TAMPILAN
    // ==========================================================

    public function test_halaman_memakai_tema_sesuai_akun(): void
    {
        $user = $this->pembina();

        // Sebelum diubah → terang
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="light"', false);

        $user->update(['theme_mode' => 'dark']);

        // Sesudah diubah → gelap
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="dark"', false);
    }

    public function test_ada_skrip_anti_kedip_dan_tombol_switch(): void
    {
        $res = $this->actingAs($this->pembina())->get('/dashboard');

        $res->assertOk();
        $res->assertSee('pm-tema', false);          // kunci localStorage
        $res->assertSee('tombolTema', false);       // tombol switch
        $res->assertSee('pmGantiTema', false);      // fungsi pengubah tema
        $res->assertSee('ikonTema', false);         // ikon bulan/matahari
        $res->assertSee('preferensi/tema', false);  // tujuan simpan ke akun
    }

    public function test_css_mode_gelap_dimuat(): void
    {
        $this->actingAs($this->pembina())->get('/dashboard')
            ->assertOk()
            ->assertSee('css/tema-gelap.css', false);
    }

    public function test_halaman_login_juga_ikut_tema(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('css/tema-gelap.css', false)
            ->assertSee('pm-tema', false);
    }

    public function test_tombol_switch_ada_di_samping_profil(): void
    {
        $res = $this->actingAs($this->pembina())->get('/dashboard');

        $isi = $res->getContent();

        $posisiTombol = strpos($isi, 'tombolTema');
        $posisiProfil = strpos($isi, 'userDropdown');

        $this->assertNotFalse($posisiTombol);
        $this->assertNotFalse($posisiProfil);
        $this->assertLessThan(
            $posisiProfil,
            $posisiTombol,
            'Switch tema harus berada sebelum (di samping) menu profil'
        );
    }

    // ==========================================================
    // CETAK TETAP TERANG
    // ==========================================================

    public function test_halaman_cetak_dipaksa_terang(): void
    {
        $css = file_get_contents(public_path('css/tema-gelap.css'));

        $this->assertStringContainsString('@media print', $css, 'Harus ada aturan khusus cetak');

        // Ambil blok @media print lalu pastikan memang memaksa latar putih
        $posisiCetak = strpos($css, '@media print');
        $blokCetak   = substr($css, $posisiCetak);

        $this->assertStringContainsString('background: #fff !important', $blokCetak);
        $this->assertStringContainsString('color: #000 !important', $blokCetak);
    }

    public function test_laporan_cetak_punya_tampilan_sendiri_yang_terang(): void
    {
        // Halaman cetak laporan tidak memakai layout utama (CSS dark tidak menyentuhnya)
        $blade = file_get_contents(resource_path('views/reports/print.blade.php'));

        $this->assertStringContainsString('background: #ffffff', $blade);
        $this->assertStringContainsString('color: #000000', $blade);
    }

    // ==========================================================
    // JAMINAN: MODE TERANG TIDAK TERSENTUH
    // ==========================================================

    /**
     * Setiap aturan di CSS mode gelap WAJIB dikunci `html[data-theme="dark"]`.
     * Kalau ada satu saja yang lolos, tampilan mode terang bisa berubah — itu
     * yang tidak boleh terjadi.
     */
    public function test_semua_aturan_css_dikunci_ke_mode_gelap(): void
    {
        $css = preg_replace('!/\*.*?\*/!s', '', file_get_contents(public_path('css/tema-gelap.css')));

        preg_match_all('/([^{}]+)\{/', $css, $cocok);

        $diperiksa = 0;
        foreach ($cocok[1] as $selector) {
            $selector = trim($selector);
            if ($selector === '' || str_starts_with($selector, '@') || str_starts_with($selector, 'from') || str_starts_with($selector, 'to') || str_contains($selector, '%')) {
                continue;
            }
            // Tombol switch tema memang perlu tampil di kedua mode
            if (str_contains($selector, '#tombolTema')) {
                continue;
            }

            $diperiksa++;

            $this->assertStringContainsString(
                'html[data-theme="dark"]',
                $selector,
                "Aturan CSS ini tidak dikunci mode gelap, jadi bisa mengubah mode terang: {$selector}"
            );
        }

        $this->assertGreaterThan(20, $diperiksa, 'Jumlah aturan yang diperiksa terlalu sedikit - pastikan CSS terbaca');
    }

    public function test_kotak_terang_di_file_tampilan_ikut_digelapkan(): void
    {
        $css = file_get_contents(public_path('css/tema-gelap.css'));

        // Warna terang yang ditulis langsung (inline style) tidak bisa ditimpa kelas biasa
        $this->assertStringContainsString('[style*="background-color: #f8f9fa"]', $css);
        $this->assertStringContainsString('[style*="background: #fff;"]', $css);
    }

    public function test_submenu_sidebar_terbaca_di_mode_gelap(): void
    {
        $css = file_get_contents(public_path('css/tema-gelap.css'));

        // SB Admin memakai selector panjang untuk .collapse-item, jadi kita harus
        // memakai selector lebih kuat + !important, kalau tidak tulisannya gelap di atas gelap
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="dark"\][^{]*\.collapse-item[^{]*\{[^}]*color:\s*#cbd5e1\s*!important/s',
            $css,
            'Sub-menu sidebar harus diterangkan di mode gelap'
        );
    }
}
