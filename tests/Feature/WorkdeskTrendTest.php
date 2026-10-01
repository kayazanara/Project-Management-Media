<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 📊 REGRESI TREN WORKDESK (2 Okt 2026)
 *
 * Dilaporkan Zev: "di bagian workdesk trend itu perhitungannya bisa diperbaiki".
 *
 * Sebelumnya grafik "Completion Rate Harian" menghitung
 *      (task selesai SAMPAI tanggal itu) / (total task selesai periode) x 100
 * jadi KUMULATIF: garisnya selalu naik 0 -> 100%, hari terakhir selalu 100%,
 * dan tidak pernah turun walaupun hari itu tidak ada task selesai.
 *
 * Yang diminta Zev: JUMLAH task selesai PER HARI (batang per hari), supaya
 * kelihatan naik-turunnya (Senin ramai, Selasa kosong, dst).
 *
 * Bonus: versi lama menjalankan 1 query COUNT per divisi per hari
 * (4 divisi x 30 hari = 120 query) setiap kali halaman Workdesk dibuka.
 */
class WorkdeskTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestAccountSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-02 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function anggota(string $divisi, string $suffix = ''): User
    {
        $kunci = strtolower(str_replace(' ', '', $divisi)) . $suffix;

        return User::updateOrCreate(
            ['email' => 'trend.' . $kunci . '@test.com'],
            [
                'name'          => 'Anggota ' . $divisi . ($suffix ? ' ' . $suffix : ''),
                'password'      => 'password',
                'role'          => 'Santri',
                'division'      => 'Tim ' . $divisi,
                'division_role' => 'anggota',
                'status'        => 'Aktif',
            ]
        );
    }

    /** Buat 1 task selesai pada tanggal tertentu untuk seorang anggota. */
    private function taskSelesai(User $anggota, string $tanggal): ProjectTask
    {
        $project = Project::firstOrCreate(
            ['name' => 'Project Tren'],
            [
                'status'     => Project::STATUS_ON_PROGRESS,
                'start_date' => '2026-09-01',
                'deadline'   => '2026-12-31',
            ]
        );

        $task = ProjectTask::create([
            'project_id'   => $project->id,
            'assigned_to'  => $anggota->id,
            'title'        => 'Task ' . $tanggal . ' - ' . $anggota->id,
            'task_date'    => $tanggal,
            'status'       => 'Completed',
            'completed_at' => $tanggal . ' 10:00:00',
            'weight'       => 10,
            'is_approved'  => true,
            'scored_at'    => $tanggal . ' 11:00:00',
        ]);

        $task->contributors()->attach($anggota->id);

        return $task;
    }

    /** Ambil deret angka satu divisi dari data tren. */
    private function deretDivisi(array $tren, string $label): array
    {
        foreach ($tren['datasets'] as $set) {
            if ($set['label'] === $label) {
                return $set['data'];
            }
        }

        $this->fail("Divisi {$label} tidak ada di data tren");
    }

    // ==========================================================
    // INTI: PER HARI, BUKAN KUMULATIF
    // ==========================================================

    public function test_tren_menghitung_jumlah_task_selesai_per_hari(): void
    {
        $anggota = $this->anggota('Lapangan');

        // 5 task 3 hari lalu, 0 task 2 hari lalu, 1 task kemarin, 3 task hari ini
        foreach (range(1, 5) as $i) { $this->taskSelesai($anggota, '2026-09-29'); }
        foreach (range(1, 1) as $i) { $this->taskSelesai($anggota, '2026-10-01'); }
        foreach (range(1, 3) as $i) { $this->taskSelesai($anggota, '2026-10-02'); }

        $tren = $this->actingAs($this->pembina())
            ->get('/workdesk?days=7')
            ->assertOk()
            ->viewData('dailyTrend');

        $deret = $this->deretDivisi($tren, 'Tim Lapangan');

        // 7 hari: 26,27,28,29,30 Sep + 1,2 Okt -> selesai di 29 Sep=5, 30 Sep=0, 1 Okt=1, 2 Okt=3
        $this->assertSame([0, 0, 0, 5, 0, 1, 3], array_map('intval', $deret),
            'Tren harus berisi JUMLAH task selesai per hari (bukan persentase kumulatif)');
    }

    public function test_tren_bisa_turun_tidak_selalu_naik(): void
    {
        $anggota = $this->anggota('Lapangan');

        foreach (range(1, 4) as $i) { $this->taskSelesai($anggota, '2026-09-30'); }
        $this->taskSelesai($anggota, '2026-10-01');

        $tren = $this->actingAs($this->pembina())->get('/workdesk?days=7')->viewData('dailyTrend');
        $deret = array_map('intval', $this->deretDivisi($tren, 'Tim Lapangan'));

        $this->assertGreaterThan(end($deret), max($deret), 'Harus ada hari yang lebih tinggi dari hari terakhir (tren bisa turun)');
        $this->assertNotSame(
            range(min($deret), max($deret)),
            $deret,
            'Deret tidak boleh selalu menaik - itu tanda masih kumulatif'
        );
    }

    public function test_tren_tidak_berakhir_selalu_100(): void
    {
        $anggota = $this->anggota('Lapangan');
        $this->taskSelesai($anggota, '2026-09-28'); // tidak ada aktivitas di hari-hari terakhir

        $tren = $this->actingAs($this->pembina())->get('/workdesk?days=7')->viewData('dailyTrend');

        $deret = array_map('intval', $this->deretDivisi($tren, 'Tim Lapangan'));

        $this->assertSame(0, (int) end($deret),
            'Kalau hari ini tidak ada task selesai, nilainya harus 0 - bukan dipaksa 100%');
    }

    // ==========================================================
    // TETAP PATUH ATURAN LAMA
    // ==========================================================

    public function test_tren_abaikan_anggota_tidak_aktif(): void
    {
        $aktif    = $this->anggota('Lapangan', 'aktif');
        $nonAktif = $this->anggota('Lapangan', 'nonaktif');
        $nonAktif->forceFill(['status' => 'Tidak Aktif'])->save();

        $this->taskSelesai($aktif, '2026-10-02');
        $this->taskSelesai($nonAktif->fresh(), '2026-10-02');

        $tren = $this->actingAs($this->pembina())->get('/workdesk?days=7')->viewData('dailyTrend');
        $deret = array_map('intval', $this->deretDivisi($tren, 'Tim Lapangan'));

        $this->assertSame(1, (int) end($deret), 'Task milik anggota non-aktif tidak boleh ikut dihitung');
    }

    public function test_tren_dipisah_per_divisi(): void
    {
        $lapangan = $this->anggota('Lapangan');
        $editing  = $this->anggota('Editing');

        foreach (range(1, 3) as $i) { $this->taskSelesai($lapangan, '2026-10-02'); }
        $this->taskSelesai($editing, '2026-10-02');

        $tren = $this->actingAs($this->pembina())->get('/workdesk?days=7')->viewData('dailyTrend');

        $lapangan = array_map('intval', $this->deretDivisi($tren, 'Tim Lapangan'));
        $editing  = array_map('intval', $this->deretDivisi($tren, 'Tim Editing'));

        $this->assertSame(3, (int) end($lapangan));
        $this->assertSame(1, (int) end($editing));
    }

    public function test_tren_ikuti_filter_hari(): void
    {
        $anggota = $this->anggota('Lapangan');
        $this->taskSelesai($anggota, '2026-10-02');

        $tujuh = $this->actingAs($this->pembina())->get('/workdesk?days=7')->viewData('dailyTrend');
        $tigaPuluh = $this->actingAs($this->pembina())->get('/workdesk?days=30')->viewData('dailyTrend');

        $this->assertCount(7, $this->deretDivisi($tujuh, 'Tim Lapangan'));
        $this->assertCount(30, $this->deretDivisi($tigaPuluh, 'Tim Lapangan'));
    }

    // ==========================================================
    // EFISIENSI: JANGAN 1 QUERY PER DIVISI PER HARI
    // ==========================================================

    public function test_tren_tidak_membanjiri_database_dengan_query(): void
    {
        $this->anggota('Lapangan');
        $this->anggota('Editing');
        $this->anggota('Broadcast');
        $this->anggota('AI Research');

        $jumlah = 0;
        DB::listen(function () use (&$jumlah) { $jumlah++; });

        $this->actingAs($this->pembina())->get('/workdesk?days=90')->assertOk();

        // Versi lama: 4 divisi x 90 hari = 360 query hanya untuk grafik tren.
        $this->assertLessThan(160, $jumlah, "Terlalu banyak query untuk halaman Workdesk: {$jumlah}");
    }

    // ==========================================================
    // TAMPILAN
    // ==========================================================

    public function test_grafik_tren_digambar_sebagai_batang_harian(): void
    {
        $res = $this->actingAs($this->pembina())->get('/workdesk?days=7');

        $res->assertOk();
        $res->assertSee('type: \'bar\'', false);
        $res->assertSee('Task Selesai per Hari', false);
    }
}
