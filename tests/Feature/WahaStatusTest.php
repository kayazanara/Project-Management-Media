<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ B5 (audit): pemetaan status WAHA -> badge System Health Workdesk.
 *
 * Dulu bagian ini TIDAK PERNAH teruji (3 percobaan sebelumnya mentok karena body
 * respons tiruan tidak terbaca). Sekarang diuji lengkap lewat jalur nyata:
 * GET /workdesk -> buildSystemHealth() -> HTTP tiruan /api/sessions.
 */
class WahaStatusTest extends TestCase
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

    private function aktifkanWaha(): void
    {
        config([
            'services.waha.enabled' => true,
            'services.waha.url'     => 'http://waha-uji.test',
            'services.waha.session' => 'sess-uji',
            'services.waha.api_key' => 'kunci-uji',
        ]);
    }

    /**
     * Tembak halaman Workdesk dengan respons WAHA tiruan, ambil waha_status-nya.
     */
    private function wahaStatus(array|string $body, int $kode = 200): ?string
    {
        $isi = is_array($body)
            ? json_encode($body)
            : $body;

        Http::fake([
            'http://waha-uji.test/api/sessions' => Http::response($isi, $kode, ['Content-Type' => 'application/json']),
        ]);

        $res = $this->actingAs($this->pembina())->get('/workdesk');
        $res->assertOk();

        $health = $res->viewData('systemHealth') ?: $res->viewData('system_health');

        return $health['waha_status'] ?? null;
    }

    public function test_sesi_working_dipetakan_connected(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('connected', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'WORKING']]));
    }

    public function test_sesi_scan_qr_dipetakan_need_qr(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('need_qr', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'SCAN_QR_CODE']]));
    }

    public function test_sesi_starting_dipetakan_starting(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('starting', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'STARTING']]));
    }

    public function test_sesi_stopped_dan_failed_dipetakan_stopped(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('stopped', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'STOPPED']]));
        $this->assertSame('stopped', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'FAILED']]));
    }

    public function test_status_tak_dikenal_dipetakan_error(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('error', $this->wahaStatus([['name' => 'sess-uji', 'status' => 'STATUS_ANEH']]));
    }

    public function test_sesi_tidak_cocok_dipetakan_no_session(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('no_session', $this->wahaStatus([['name' => 'sesi-divisi-lain', 'status' => 'WORKING']]));
    }

    public function test_http_401_dipetakan_unauthorized(): void
    {
        $this->aktifkanWaha();

        $this->assertSame('unauthorized', $this->wahaStatus('{"error":"unauthorized"}', 401));
    }

    public function test_waha_mati_dipetakan_offline(): void
    {
        $this->aktifkanWaha();

        Http::fake([
            'http://waha-uji.test/api/sessions' => function () {
                throw new \Exception('koneksi ditolak');
            },
        ]);

        $res = $this->actingAs($this->pembina())->get('/workdesk');
        $res->assertOk();

        $health = $res->viewData('systemHealth') ?: $res->viewData('system_health');

        $this->assertSame('offline', $health['waha_status'] ?? null, 'Service mati harus jadi offline, bukan error 500.');
    }

    public function test_waha_nonaktif_tidak_menembak_http_sama_sekali(): void
    {
        config(['services.waha.enabled' => false]);
        Http::fake();

        $res = $this->actingAs($this->pembina())->get('/workdesk');
        $res->assertOk();

        $health = $res->viewData('systemHealth') ?: $res->viewData('system_health');

        $this->assertSame('disabled', $health['waha_status'] ?? null);
        Http::assertNothingSent();
    }
}
