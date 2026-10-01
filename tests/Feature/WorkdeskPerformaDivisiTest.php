<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 📊 REGRESI PERFORMA DIVISI WORKDESK (2 Okt 2026)
 *
 * Dilaporkan Zev: "terutama di bagian performa devisi".
 *
 * Temuan pada kode lama (buildDivisionPerformance):
 *  1. Periode SELALU minggu ini (startOfWeek..endOfWeek) -> filter 7/30/90 hari
 *     di halaman tidak berpengaruh ke tabel ini.
 *  2. Pembanding tren tidak apple-to-apple: pembilang memakai gabungan
 *     (task_date di minggu ini ATAU selesai minggu ini) sedangkan pembanding
 *     hanya "selesai minggu lalu".
 *  3. Minggu lalu 0 task -> trend = 0%, padahal divisi baru naik dari nol.
 *  4. Divisi tanpa task -> semua 0% (kelihatan buruk) padahal artinya
 *     "tidak ada data" -> harus "—" sesuai prinsip kejujuran data.
 *  5. Indikator "bobot" (weighted_rate) ikut menekan skor walaupun bobot task
 *     belum pernah diisi oleh Pembina (weight = 0).
 */
class WorkdeskPerformaDivisiTest extends TestCase
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
            ['email' => 'perf.' . $kunci . '@test.com'],
            [
                'name'          => 'Anggota ' . $divisi . $suffix,
                'password'      => 'password',
                'role'          => 'Santri',
                'division'      => 'Tim ' . $divisi,
                'division_role' => 'anggota',
                'status'        => 'Aktif',
            ]
        );
    }

    private function project(): Project
    {
        return Project::firstOrCreate(
            ['name' => 'Project Performa'],
            [
                'status'     => Project::STATUS_ON_PROGRESS,
                'start_date' => '2026-08-01',
                'deadline'   => '2026-12-31',
            ]
        );
    }

    /**
     * @param string      $rencana   tanggal task seharusnya selesai (task_date)
     * @param string|null $selesai   kapan benar-benar selesai (null = belum selesai)
     */
    private function task(User $anggota, string $rencana, ?string $selesai, int $bobot = 10): ProjectTask
    {
        static $no = 0;
        $no++;

        $task = ProjectTask::create([
            'project_id'   => $this->project()->id,
            'assigned_to'  => $anggota->id,
            'title'        => 'Task #' . $no . ' ' . $rencana,
            'task_date'    => $rencana,
            'status'       => $selesai ? 'Completed' : 'Pending',
            'completed_at' => $selesai ? $selesai . ' 10:00:00' : null,
            'weight'       => $bobot,
            'is_approved'  => (bool) $selesai,
            'scored_at'    => $selesai ? $selesai . ' 11:00:00' : null,
        ]);

        $task->contributors()->attach($anggota->id);

        return $task;
    }

    private function barisDivisi(array $performance, string $label): array
    {
        foreach ($performance as $div) {
            if ($div['label'] === $label) {
                return $div;
            }
        }

        $this->fail("Divisi {$label} tidak ada di data performa");
    }

    private function performa(int $days = 7): array
    {
        return $this->actingAs($this->pembina())
            ->get("/workdesk?days={$days}")
            ->assertOk()
            ->viewData('performance');
    }

    // ==========================================================
    // 1. IKUT FILTER HARI
    // ==========================================================

    public function test_performa_divisi_ikuti_filter_hari(): void
    {
        $anggota = $this->anggota('Lapangan');

        // selesai 20 hari lalu: masuk kalau periode 30/90 hari, TIDAK masuk kalau 7 hari
        $this->task($anggota, '2026-09-12', '2026-09-12');

        $tujuhHari    = $this->barisDivisi($this->performa(7), 'Tim Lapangan');
        $tigaPuluhHari = $this->barisDivisi($this->performa(30), 'Tim Lapangan');

        $this->assertSame(0, $tujuhHari['total_tasks'], 'Periode 7 hari tidak boleh memuat task 20 hari lalu');
        $this->assertSame(1, $tigaPuluhHari['total_tasks'], 'Periode 30 hari harus memuat task 20 hari lalu');
    }

    public function test_performa_divisi_menolak_status_tidak_aktif(): void
    {
        $nonAktif = $this->anggota('Lapangan', 'nonaktif');
        $nonAktif->forceFill(['status' => 'Tidak Aktif'])->save();
        $this->task($nonAktif->fresh(), '2026-10-01', '2026-10-01');

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(0, $baris['total_tasks'], 'Task anggota non-aktif tidak dihitung');
    }

    // ==========================================================
    // 2. TREN APPLE-TO-APPLE (hanya yang benar-benar SELESAI)
    // ==========================================================

    public function test_tren_membandingkan_jumlah_selesai_periode_sebelumnya(): void
    {
        $anggota = $this->anggota('Lapangan');

        // Periode 7 hari (26 Sep - 2 Okt): 4 task selesai
        foreach (['2026-09-27', '2026-09-29', '2026-10-01', '2026-10-02'] as $tgl) {
            $this->task($anggota, $tgl, $tgl);
        }
        // Periode sebelumnya (19 - 25 Sep): 2 task selesai
        foreach (['2026-09-20', '2026-09-22'] as $tgl) {
            $this->task($anggota, $tgl, $tgl);
        }

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(4, $baris['completed_tasks']);
        $this->assertSame(2, $baris['completed_prev']);
        $this->assertSame(100, $baris['trend'], 'Dari 2 ke 4 task = naik 100%');
    }

    public function test_tren_tidak_terpengaruh_task_belum_selesai(): void
    {
        $anggota = $this->anggota('Lapangan');

        foreach (['2026-09-27', '2026-10-01'] as $tgl) {
            $this->task($anggota, $tgl, $tgl);
        }
        foreach (['2026-09-20', '2026-09-22'] as $tgl) {
            $this->task($anggota, $tgl, $tgl);
        }

        // 6 task dijadwalkan periode ini tapi BELUM selesai (jangan mengubah tren)
        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02', '2026-10-02'] as $tgl) {
            $this->task($anggota, $tgl, null);
        }

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(2, $baris['completed_tasks'], 'Pembilang tren = yang SELESAI saja (2 task), bukan 8');
        $this->assertSame(2, $baris['completed_prev'], 'Periode sebelumnya juga 2 task selesai');
        $this->assertSame(0, $baris['trend'], '2 -> 2 task = 0%, task yang belum selesai tidak boleh mengubah tren');
    }

    public function test_task_selesai_setelah_periode_tidak_dihitung_selesai(): void
    {
        $anggota = $this->anggota('Lapangan');

        // Dijadwalkan di periode ini (30 Sep) tapi baru selesai 5 Okt (di luar periode)
        $this->task($anggota, '2026-09-30', '2026-10-05');

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(1, $baris['total_tasks'], 'Dijadwalkan periode ini -> masuk daftar');
        $this->assertSame(0, $baris['completed_tasks'], 'Selesainya di luar periode -> belum dihitung selesai');
    }

    // ==========================================================
    // 3. NAIK DARI NOL JANGAN DITULIS 0%
    // ==========================================================

    public function test_naik_dari_nol_ditandai_baru_bukan_nol_persen(): void
    {
        $anggota = $this->anggota('Lapangan');

        // Periode ini 3 task selesai, periode sebelumnya 0
        foreach (['2026-09-27', '2026-10-01', '2026-10-02'] as $tgl) {
            $this->task($anggota, $tgl, $tgl);
        }

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(0, $baris['completed_prev']);
        $this->assertNull($baris['trend'], 'Tidak ada pembanding -> tren harus null, bukan 0');
        $this->assertSame('baru', $baris['trend_teks'], 'Harus ditulis "baru", bukan 0%');
    }

    // ==========================================================
    // 4. TANPA DATA -> "—" (BUKAN 0%)
    // ==========================================================

    public function test_divisi_tanpa_task_tidak_dinilai_nol(): void
    {
        $this->anggota('Lapangan'); // divisi ini tidak punya task sama sekali

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(0, $baris['total_tasks']);
        $this->assertNull($baris['completion_rate'], 'Tanpa task -> completion_rate null (ditampilkan —)');
        $this->assertNull($baris['on_time_rate'], 'Tanpa task -> on_time_rate null');
        $this->assertNull($baris['performance_score'], 'Tanpa task -> skor null, bukan 0');
        $this->assertSame('—', $baris['skor_teks']);
    }

    public function test_tampilan_tidak_menulis_nol_persen_untuk_divisi_kosong(): void
    {
        $this->anggota('AI Research');

        $res = $this->actingAs($this->pembina())->get('/workdesk?days=7');

        $res->assertOk();
        $res->assertDontSee('>0%<', false);
    }

    // ==========================================================
    // 5. BOBOT KOSONG JANGAN MENEKAN SKOR
    // ==========================================================

    public function test_bobot_belum_diisi_tidak_menekan_skor(): void
    {
        $anggota = $this->anggota('Lapangan');

        // 2 task selesai TEPAT WAKTU, tapi bobot belum diisi (weight = 0)
        $this->task($anggota, '2026-09-28', '2026-09-28', 0);
        $this->task($anggota, '2026-10-01', '2026-10-01', 0);

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(100, $baris['completion_rate']);
        $this->assertSame(100, $baris['on_time_rate']);
        $this->assertNull($baris['weighted_rate'], 'Bobot belum diisi -> indikator ini dilewati');
        $this->assertSame(100, $baris['performance_score'], 'Skor harus 100, bukan ditekan oleh bobot kosong');
    }

    public function test_bobot_terisi_ikut_menghitung_skor(): void
    {
        $anggota = $this->anggota('Lapangan');

        // 1 dari 2 task punya bobot selesai -> 60 dari (60+40) = 60%
        $selesai = $this->task($anggota, '2026-10-01', '2026-10-01', 60);
        $this->task($anggota, '2026-10-02', null, 40);

        $baris = $this->barisDivisi($this->performa(7), 'Tim Lapangan');

        $this->assertSame(60, $baris['weighted_rate'], 'Bobot: 60 dari 100 = 60%');
        $this->assertNotNull($baris['performance_score']);
        $this->assertLessThan(100, $baris['performance_score'], 'Skor harus turun karena bobot belum tuntas');
    }
}
