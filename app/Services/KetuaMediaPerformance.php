<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectRevision;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\WorkdeskAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ⚡ FITUR BARU: PENILAIAN KINERJA KETUA MEDIA (khusus Pembina).
 *
 * Tugas Ketua Media = memastikan semua tugas & project dari TIAP DIVISI berjalan
 * lancar. Karena itu penilaiannya 4 PILAR (bukan "dia selesai berapa task"):
 *
 *   1. Kesehatan Divisi  (40%) — per divisi: task terlambat, project lewat deadline,
 *                                ketepatan waktu penyelesaian
 *   2. Kecepatan Alur    (20%) — berapa lama project NYANGKUT menunggu dia:
 *                                Planning -> ajukan task, dan Review -> dia ACC
 *   3. Keterlibatan      (20%) — rapat yang dia buat, catatan revisi yang dia tulis,
 *                                alert Workdesk yang dia buka, hari aktif login
 *   4. Ketuntasan        (20%) — project selesai tepat deadline, rata-rata progress
 *                                project aktif, panjang siklus project
 *
 * ATURAN PENTING (biar angkanya jujur):
 * - Semua data dimuat SEKALI (dataset) lalu dihitung di PHP → tidak ada N+1, dan
 *   tren 6 bulan memakai dataset yang sama (tidak menembak query baru).
 * - SETIAP PILAR DIHITUNG PER PERIODE (periodik), bukan dari kondisi "sekarang".
 *   Jadi tren bulan lalu tidak berubah-ubah tiap kali halaman dibuka.
 * - Pilar yang datanya BELUM ADA tidak dianggap 0 maupun 100. Pilar itu
 *   DIKELUARKAN dari penilaian, dan bobotnya dibagikan proporsional ke pilar lain.
 *   Jadi skor tidak "kembung" gara-gara data kosong, dan tidak juga dihukum.
 * - Data anomali (mis. completed_at lebih awal dari created_at pada data demo)
 *   DILEWATI, tidak dipaksa jadi angka.
 */
class KetuaMediaPerformance
{
    /** Bobot pilar (harus total 1.0) */
    public const BOBOT = [
        'kesehatan_divisi' => 0.40,
        'kecepatan_alur'   => 0.20,
        'keterlibatan'     => 0.20,
        'ketuntasan'       => 0.20,
    ];

    public const PILAR = [
        'kesehatan_divisi' => ['label' => 'Kesehatan Divisi', 'icon' => 'fa-heartbeat',     'warna' => '#4e73df'],
        'kecepatan_alur'   => ['label' => 'Kecepatan Alur',   'icon' => 'fa-bolt',          'warna' => '#f59f0b'],
        'keterlibatan'     => ['label' => 'Keterlibatan',     'icon' => 'fa-handshake',     'warna' => '#1cc88a'],
        'ketuntasan'       => ['label' => 'Ketuntasan',       'icon' => 'fa-flag-checkered', 'warna' => '#8b5cf6'],
    ];

    /**
     * ⚙️ AMBANG PENALTI — dijadikan konstanta supaya gampang dikalibrasi.
     * Nilai = poin maksimum yang dipotong dari 100 pada tiap jenis masalah.
     */
    public const PENALTI = [
        'task_lewat'    => 40,   // rasio task terlambat di periode
        'project_telat' => 30,   // rasio project lewat deadline
        'ketepatan'     => 30,   // (100% - ketepatan waktu)
    ];

    /**
     * ⚙️ TARGET & BATAS WAJAR — satuan hari/kali. Lewat target = poin dikurangi.
     */
    public const TARGET = [
        'susun_task_hari'       => 3,    // project dibuat -> task diajukan ke Pembina
        'acc_hari'              => 1,    // project masuk Review -> dia ACC
        'planning_hari'         => 3,    // project menunggu disusun tasknya
        'siklus_hari'           => 21,   // panjang siklus project (dibuat -> selesai)
        'rapat_per_bulan'       => 4,
        'revisi_per_bulan'      => 3,
        'hari_aktif_per_bulan'  => 20,
    ];

    /** ⚙️ Besar potongan poin per hari/satuan kelebihan dari target. */
    public const TURUN_PER_HARI = [
        'susun_task' => 10,
        'acc'        => 12,
        'planning'   => 10,
        'siklus'     => 4,
    ];

    // ============================================================
    // 1. DATASET — dimuat sekali, dipakai semua perhitungan
    // ============================================================

    /**
     * Muat semua data pendukung dalam beberapa query saja.
     * Dipakai ulang oleh evaluate() dan trend() supaya halaman tetap ringan.
     */
    public function dataset(): array
    {
        // ── Divisi operasional + anggota ──
        // Divisi TIDAK di-hardcode: ditarik dari user (kepala_divisi / anggota),
        // jadi kalau nanti ada divisi baru, halaman ini ikut otomatis.
        $users = User::query()
            ->select('id', 'name', 'division', 'division_role', 'status')
            ->get();

        $divisions = [];
        $petaDivisiUser = [];

        foreach ($users as $u) {
            if ($u->status !== 'Aktif') {
                continue;
            }
            if (! in_array($u->division_role, ['kepala_divisi', 'anggota'], true)) {
                continue;
            }

            $namaDivisi = trim((string) $u->division);
            if ($namaDivisi === '' || strtolower($namaDivisi) === 'ketua media') {
                continue;
            }

            $divisions[$namaDivisi] ??= [
                'name'         => $namaDivisi,
                'member_ids'   => [],
                'member_names' => [],
            ];

            $divisions[$namaDivisi]['member_ids'][]   = (int) $u->id;
            $divisions[$namaDivisi]['member_names'][] = $u->name;
            $petaDivisiUser[(int) $u->id] = $namaDivisi;
        }

        ksort($divisions);

        // ── Project + anggotanya (untuk memetakan project -> divisi) ──
        $projects = Project::query()
            ->select('id', 'name', 'status', 'deadline', 'start_date', 'created_at', 'completed_at',
                     'scored_at', 'released_at', 'approved_by_pembina_at', 'approved_by_ketua_at',
                     'is_overdue', 'task_submitted_at', 'task_submitted_by', 'review_requested_at')
            ->with('users:id,name,division')
            ->get();

        $anggotaProject = [];   // project_id => [user_id, ...]
        $divisiProject  = [];   // project_id => [nama_divisi, ...]
        $projectDivisi  = [];   // nama_divisi => [project_id, ...]

        foreach ($projects as $p) {
            $anggotaProject[$p->id] = [];
            $divisiProject[$p->id]  = [];

            foreach ($p->users as $u) {
                $anggotaProject[$p->id][] = (int) $u->id;
                if (isset($petaDivisiUser[(int) $u->id])) {
                    $divisiProject[$p->id][] = $petaDivisiUser[(int) $u->id];
                }
            }

            $divisiProject[$p->id] = array_values(array_unique($divisiProject[$p->id]));

            foreach ($divisiProject[$p->id] as $namaDiv) {
                $projectDivisi[$namaDiv][] = $p->id;
            }
        }

        // ── Task ──
        $tasks = ProjectTask::query()
            ->select('id', 'project_id', 'title', 'status', 'task_date', 'completed_at')
            ->get();

        $taskPerProject = [];
        foreach ($tasks as $t) {
            $taskPerProject[$t->project_id][] = $t;
        }

        return [
            'divisions'        => $divisions,
            'peta_divisi_user' => $petaDivisiUser,
            'projects'         => $projects,
            'project_map'      => $projects->keyBy('id'),
            'anggota_project'  => $anggotaProject,
            'divisi_project'   => $divisiProject,
            'project_divisi'   => $projectDivisi,
            'tasks'            => $tasks,
            'task_per_project' => $taskPerProject,
            'revisions'        => ProjectRevision::query()
                ->select('id', 'project_id', 'user_id', 'type', 'note', 'created_at', 'resolved_at')
                ->get(),
            'meetings'         => Meeting::query()
                ->select('id', 'title', 'created_by', 'created_at')
                ->get(),
            'alerts'           => WorkdeskAlert::query()
                ->select('id', 'user_id', 'type', 'title', 'is_read', 'created_at')
                ->get(),
            'login_rewards'    => \DB::table('login_rewards')
                ->select('user_id', 'reward_date')->get(),
            'today'            => now()->copy()->endOfDay(),
        ];
    }

    // ============================================================
    // 2. EVALUASI UTAMA
    // ============================================================

    /**
     * Hitung penilaian kinerja untuk 1 Ketua Media pada 1 periode.
     */
    public function evaluate(User $ketua, Carbon $start, Carbon $end, ?array $data = null): array
    {
        $data ??= $this->dataset();

        // ── Kalau periode ini SAMA SEKALI tidak ada jejak data (mis. bulan sebelum
        //    sistem dipakai), jangan mengarang skor: kembalikan kosong apa adanya ──
        if (! $this->adaJejakData($data, $start, $end)) {
            $kosong = $this->hasilKosong($data);

            // Papan divisi & daftar "menunggu tindakan" tetap ditampilkan — dua bagian
            // ini memang menggambarkan KONDISI SEKARANG, bukan angka periode. Skornya
            // kosong ("—") karena memang belum ada data periode untuk dinilai.
            $kosong['divisi']     = $this->pilarKesehatanDivisi($data, $start, $end)['detail']['divisi'] ?? [];
            $kosong['bottleneck'] = $this->bottleneck($data);

            return $kosong;
        }

        // Sub-metrik yang sifatnya "kondisi SEKARANG" (mis. sedang menunggu tindakan)
        // hanya boleh ikut dinilai kalau periode ini memang mencakup hari ini —
        // kalau tidak, bulan lampau akan terus berubah setiap halaman dibuka.
        $sertakanSekarang = $this->periodeTermasukHariIni($end);

        $pilar = [
            'kesehatan_divisi' => $this->pilarKesehatanDivisi($data, $start, $end),
            'kecepatan_alur'   => $this->pilarKecepatanAlur($data, $ketua, $start, $end, $sertakanSekarang),
            'keterlibatan'     => $this->pilarKeterlibatan($data, $ketua, $start, $end),
            'ketuntasan'       => $this->pilarKetuntasan($data, $start, $end, $sertakanSekarang),
        ];

        // ── Skor total: hanya pilar yang ada datanya, bobot dibagikan proporsional ──
        $bobotTersedia = 0.0;
        $jumlah        = 0.0;
        $pilarAdaData  = [];

        foreach ($pilar as $kunci => $p) {
            if ($p['tersedia'] && $p['skor'] !== null) {
                $bobotTersedia += self::BOBOT[$kunci];
                $jumlah        += $p['skor'] * self::BOBOT[$kunci];
                $pilarAdaData[] = self::PILAR[$kunci]['label'];
            }
        }

        $skorTotal = $bobotTersedia > 0 ? (int) round($jumlah / $bobotTersedia) : null;
        $badge     = $this->labelSkor($skorTotal);

        return [
            'skor'          => $skorTotal,
            'label'         => $badge['label'],
            'warna'         => $badge['warna'],
            'pilar'         => $pilar,
            'pilar_dinilai' => $pilarAdaData,
            'divisi'        => $pilar['kesehatan_divisi']['detail']['divisi'] ?? [],
            'bottleneck'    => $this->bottleneck($data),
            'timeline'      => $this->timeline($data, $ketua, $start, $end),
            'ringkasan'     => $this->ringkasan($data, $start, $end),
        ];
    }

    /**
     * Tren skor 6 bulan terakhir (pakai dataset yang sama → tanpa query baru).
     */
    public function trend(User $ketua, int $bulan = 6, ?array $data = null): array
    {
        $data ??= $this->dataset();

        $label = [];
        $seri  = [];

        for ($i = $bulan - 1; $i >= 0; $i--) {
            $awal  = now()->subMonths($i)->startOfMonth();
            $akhir = now()->subMonths($i)->endOfMonth();

            $label[] = $awal->translatedFormat('M Y');
            $seri[]  = $this->evaluate($ketua, $awal, $akhir, $data)['skor'];
        }

        return ['label' => $label, 'data' => $seri];
    }

    // ============================================================
    // PILAR 1 — KESEHATAN DIVISI (40%)  • periodik
    // ============================================================

    private function pilarKesehatanDivisi(array $data, Carbon $start, Carbon $end): array
    {
        $baris     = [];
        $semuaSkor = [];
        $today     = $data['today'];

        foreach ($data['divisions'] as $nama => $div) {
            $projectIds = $data['project_divisi'][$nama] ?? [];

            // ── (A) METRIK PERIODE: task yang JADWALNYA di periode ini ──
            $jadwal = 0;
            $lewat  = 0;
            $selesai = 0;
            $tepat   = 0;

            foreach ($projectIds as $pid) {
                foreach ($data['task_per_project'][$pid] ?? [] as $t) {
                    if (! $t->task_date || ! $t->task_date->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                        continue;
                    }

                    $jadwal++;

                    $tglRencana = $t->task_date->copy()->startOfDay();

                    if ($t->status === 'Completed' && $t->completed_at) {
                        $selesai++;
                        if ($t->completed_at->toDateString() <= $t->task_date->toDateString()) {
                            $tepat++;
                        } else {
                            $lewat++;
                        }
                        continue;
                    }

                    // belum selesai: telat kalau tanggal rencananya sudah lewat
                    // ⚡ PENTING: bandingkan dengan AKHIR hari, bukan awal hari —
                    // kalau tidak, task yang jadwalnya HARI INI terhitung telat.
                    if ($tglRencana->copy()->endOfDay()->isPast()) {
                        $lewat++;
                    }
                }
            }

            // ── (B) METRIK PERIODE: project yang DEADLINE-nya di periode ini ──
            $projectJatuhTempo = 0;
            $projectTelat      = 0;

            foreach ($projectIds as $pid) {
                $p = $data['project_map'][$pid] ?? null;
                if (! $p || ! $p->deadline) {
                    continue;
                }
                if (! $p->deadline->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                    continue;
                }

                $projectJatuhTempo++;

                $selesaiTepat = $p->status === Project::STATUS_COMPLETED && $p->completed_at
                    && $p->completed_at->toDateString() <= $p->deadline->toDateString();

                if (! $selesaiTepat) {
                    $projectTelat++;
                }
            }

            // ── (C) INFO OPERASIONAL SEKARANG (tidak dinilai, hanya ditampilkan) ──
            $aktifSekarang = 0;
            $telatSekarang = 0;
            $taskTerbukaSekarang = 0;
            $taskLewatSekarang   = 0;

            foreach ($projectIds as $pid) {
                $p = $data['project_map'][$pid] ?? null;
                if (! $p) {
                    continue;
                }

                foreach ($data['task_per_project'][$pid] ?? [] as $t) {
                    if ($t->status === 'Completed') {
                        continue;
                    }
                    $taskTerbukaSekarang++;
                    if ($t->task_date && $t->task_date->copy()->endOfDay()->isPast()) {
                        $taskLewatSekarang++;
                    }
                }

                if ($p->status === Project::STATUS_COMPLETED) {
                    continue;
                }

                $aktifSekarang++;
                if ($p->is_overdue || ($p->deadline && $p->deadline->copy()->endOfDay()->isPast())) {
                    $telatSekarang++;
                }
            }

            // ── Penalti (hanya dari metrik periode yang memang ada datanya) ──
            $penalti = [];
            $punyaData = false;

            if ($jadwal > 0) {
                $punyaData = true;
                $penalti[] = ($lewat / $jadwal) * self::PENALTI['task_lewat'];
            }

            if ($projectJatuhTempo > 0) {
                $punyaData = true;
                $penalti[] = ($projectTelat / $projectJatuhTempo) * self::PENALTI['project_telat'];
            }

            $ketepatan = $selesai > 0 ? ($tepat / $selesai) * 100 : null;
            if ($ketepatan !== null) {
                $punyaData = true;
                $penalti[] = ((100 - $ketepatan) / 100) * self::PENALTI['ketepatan'];
            }

            $skor  = $punyaData ? (int) round(max(0, min(100, 100 - array_sum($penalti)))) : null;
            $lampu = $skor === null ? 'abu' : ($skor >= 85 ? 'hijau' : ($skor >= 70 ? 'kuning' : 'merah'));

            // Masalah utama divisi di periode ini
            $masalah = [];
            if ($jadwal > 0 && $lewat > 0) {
                $masalah[] = $lewat . ' dari ' . $jadwal . ' task terlambat';
            }
            if ($projectTelat > 0) {
                $masalah[] = $projectTelat . ' dari ' . $projectJatuhTempo . ' project lewat deadline';
            }
            if ($ketepatan !== null && $ketepatan < 70) {
                $masalah[] = 'ketepatan waktu ' . (int) round($ketepatan) . '%';
            }
            if (count($masalah) === 0 && $punyaData) {
                $masalah[] = 'tidak ada masalah berarti';
            }

            $baris[] = [
                'nama'              => $nama,
                'anggota'           => count($div['member_ids']),
                'project_aktif'     => $aktifSekarang,
                'project_telat'     => $telatSekarang,
                'task_terbuka'      => $taskTerbukaSekarang,
                'task_lewat'        => $taskLewatSekarang,
                'jadwal'            => $jadwal,
                'lewat'             => $lewat,
                'selesai'           => $selesai,
                'project_jatuh_tempo' => $projectJatuhTempo,
                'project_telat_periode' => $projectTelat,
                'ketepatan'         => $ketepatan !== null ? (int) round($ketepatan) : null,
                'skor'              => $skor,
                'lampu'             => $lampu,
                'masalah'           => $masalah,
                'project_ids'       => array_slice($projectIds, 0, 60),
            ];

            if ($skor !== null) {
                $semuaSkor[] = $skor;
            }
        }

        // Urutkan dari yang paling bermasalah (skor kecil dulu, yang belum ada data paling belakang)
        usort($baris, function ($a, $b) {
            return ($a['skor'] ?? 101) <=> ($b['skor'] ?? 101);
        });

        $skorPilar = count($semuaSkor) > 0 ? (int) round(array_sum($semuaSkor) / count($semuaSkor)) : null;

        return [
            'tersedia' => $skorPilar !== null,
            'skor'     => $skorPilar,
            'catatan'  => count($semuaSkor) > 0
                ? 'Rata-rata ' . count($semuaSkor) . ' divisi (data periode ini)'
                : 'Belum ada task/project terjadwal di periode ini',
            'detail'   => ['divisi' => $baris],
        ];
    }

    // ============================================================
    // PILAR 2 — KECEPATAN ALUR (20%)
    // ============================================================

    private function pilarKecepatanAlur(array $data, User $ketua, Carbon $start, Carbon $end, bool $sertakanSekarang = true): array
    {
        // ── (a) Project yang MASIH nyangkut di Planning (task belum disusun/diajukan) ──
        $nyangkut = [];
        foreach ($data['projects'] as $p) {
            if ($p->status !== Project::STATUS_PLANNING) {
                continue;
            }
            $nyangkut[] = [
                'id'   => $p->id,
                'nama' => $p->name,
                'hari' => round((float) $p->created_at->diffInDays(now()), 1),
                'task' => count($data['task_per_project'][$p->id] ?? []),
            ];
        }

        $rataNyangkut = count($nyangkut) > 0
            ? array_sum(array_column($nyangkut, 'hari')) / count($nyangkut)
            : null;

        // ── (b) Lama penyusunan task (project dibuat -> dia ajukan ke Pembina) ──
        $lamaSusun = [];
        foreach ($data['projects'] as $p) {
            if (! $p->task_submitted_at || ! $p->task_submitted_at->between($start, $end)) {
                continue;
            }
            $lamaSusun[] = (float) $p->created_at->diffInHours($p->task_submitted_at) / 24;
        }

        $rataSusun = count($lamaSusun) > 0 ? array_sum($lamaSusun) / count($lamaSusun) : null;

        // ── (c) Lama tunggu ACC dia (project masuk Review -> dia ACC) ──
        $lamaAcc = [];
        $menungguAcc = 0;

        foreach ($data['projects'] as $p) {
            if (! $p->review_requested_at) {
                continue;
            }

            if ($p->approved_by_ketua_at) {
                if ($p->approved_by_ketua_at->between($start, $end)) {
                    $lamaAcc[] = (float) $p->review_requested_at->diffInHours($p->approved_by_ketua_at) / 24;
                }
                continue;
            }

            // masih menunggu ACC (project masih di tahap Review)
            if ($p->status === Project::STATUS_REVIEW) {
                $menungguAcc++;
                $lamaAcc[] = (float) $p->review_requested_at->diffInHours(now()) / 24;
            }
        }

        $rataAcc = count($lamaAcc) > 0 ? array_sum($lamaAcc) / count($lamaAcc) : null;

        // ── Sub-metrik ──
        $sub = [];

        if ($rataSusun !== null) {
            $sub['penyusunan_task'] = [
                'label'  => 'Rata-rata lama menyusun task',
                'nilai'  => round($rataSusun, 1),
                'satuan' => 'hari',
                'target' => 'maks ' . self::TARGET['susun_task_hari'] . ' hari',
                'skor'   => $this->skorTurunkan($rataSusun, self::TARGET['susun_task_hari'], self::TURUN_PER_HARI['susun_task']),
            ];
        }

        if ($rataAcc !== null) {
            $sub['tunggu_acc'] = [
                'label'  => 'Rata-rata tunggu ACC Ketua Media',
                'nilai'  => round($rataAcc, 1),
                'satuan' => 'hari',
                'target' => 'maks ' . self::TARGET['acc_hari'] . ' hari',
                'skor'   => $this->skorTurunkan($rataAcc, self::TARGET['acc_hari'], self::TURUN_PER_HARI['acc']),
            ];
        }

        // Selalu ada datanya: kondisi "menunggu tindakan dia" saat ini.
        // Hanya dinilai kalau periode yang dilihat memang mencakup hari ini.
        if ($sertakanSekarang) {
            $menunggu = count($nyangkut) + $menungguAcc;
            $sub['menunggu_tindakan'] = [
                'label'  => 'Sedang menunggu tindakan dia',
                'nilai'  => $menunggu,
                'satuan' => 'project',
                'target' => '0 project',
                'skor'   => $menunggu === 0 ? 100 : max(0, 100 - ($menunggu * 15)),
            ];
        }

        if ($rataNyangkut !== null && $sertakanSekarang) {
            $sub['nyangkut_planning'] = [
                'label'  => 'Rata-rata lama nyangkut di Planning',
                'nilai'  => round($rataNyangkut, 1),
                'satuan' => 'hari',
                'target' => 'maks ' . self::TARGET['planning_hari'] . ' hari',
                'skor'   => $this->skorTurunkan($rataNyangkut, self::TARGET['planning_hari'], self::TURUN_PER_HARI['planning']),
            ];
        }

        $skor = $this->rataSkor($sub);

        return [
            'tersedia' => $skor !== null,
            'skor'     => $skor,
            'catatan'  => count($sub) . ' indikator kecepatan',
            'detail'   => [
                'sub'          => $sub,
                'nyangkut'     => $nyangkut,
                'menunggu_acc' => $menungguAcc,
            ],
        ];
    }

    // ============================================================
    // PILAR 3 — KETERLIBATAN (20%)
    // ============================================================

    private function pilarKeterlibatan(array $data, User $ketua, Carbon $start, Carbon $end): array
    {
        // Target proporsional dengan panjang periode (basis 30 hari)
        $hariPeriode = max(1, (int) $start->diffInDays($end) + 1);
        $faktor      = $hariPeriode / 30;

        // ── Rapat yang dia buat ──
        $rapat = $data['meetings']->filter(fn ($m) =>
            (int) $m->created_by === (int) $ketua->id && $m->created_at->between($start, $end)
        );
        $targetRapat = max(1, (int) ceil(self::TARGET['rapat_per_bulan'] * $faktor));

        // ── Catatan revisi yang dia tulis ──
        $revisi = $data['revisions']->filter(fn ($r) =>
            (int) $r->user_id === (int) $ketua->id && $r->created_at->between($start, $end)
        );
        $targetRevisi = max(1, (int) ceil(self::TARGET['revisi_per_bulan'] * $faktor));

        // ── Alert Workdesk yang dia BUKA ──
        $alertDia    = $data['alerts']->filter(fn ($a) => (int) $a->user_id === (int) $ketua->id);
        $alertDibaca = $alertDia->where('is_read', true)->count();

        // ── Hari aktif (dari riwayat bonus login harian) ──
        $hariAktif = $data['login_rewards']->filter(function ($r) use ($ketua, $start, $end) {
            if ((int) $r->user_id !== (int) $ketua->id) {
                return false;
            }
            return Carbon::parse($r->reward_date)->between($start->copy()->startOfDay(), $end->copy()->endOfDay());
        })->count();
        $targetHariAktif = max(1, (int) ceil(self::TARGET['hari_aktif_per_bulan'] * $faktor));

        $sub = [
            'rapat' => [
                'label'  => 'Rapat/notulen yang dia buat',
                'nilai'  => $rapat->count(),
                'satuan' => 'rapat',
                'target' => '≥ ' . $targetRapat,
                'skor'   => $this->skorNaikkan($rapat->count(), $targetRapat),
            ],
            'catatan_revisi' => [
                'label'  => 'Catatan revisi yang dia tulis',
                'nilai'  => $revisi->count(),
                'satuan' => 'catatan',
                'target' => '≥ ' . $targetRevisi,
                'skor'   => $this->skorNaikkan($revisi->count(), $targetRevisi),
            ],
            'hari_aktif' => [
                'label'  => 'Hari aktif (login)',
                'nilai'  => $hariAktif,
                'satuan' => 'hari',
                'target' => '≥ ' . $targetHariAktif,
                'skor'   => $this->skorNaikkan($hariAktif, $targetHariAktif),
            ],
        ];

        // Alert hanya dinilai kalau memang ada alert untuknya
        if ($alertDia->count() > 0) {
            $sub['alert_dibaca'] = [
                'label'  => 'Alert Workdesk yang dibuka',
                'nilai'  => $alertDibaca . '/' . $alertDia->count(),
                'satuan' => 'alert',
                'target' => 'semua',
                'skor'   => (int) round(($alertDibaca / $alertDia->count()) * 100),
            ];
        }

        $skor = $this->rataSkor($sub);

        return [
            'tersedia' => $skor !== null,
            'skor'     => $skor,
            'catatan'  => count($sub) . ' indikator keterlibatan',
            'detail'   => ['sub' => $sub],
        ];
    }

    // ============================================================
    // PILAR 4 — KETUNTASAN (20%)
    // ============================================================

    private function pilarKetuntasan(array $data, Carbon $start, Carbon $end, bool $sertakanSekarang = true): array
    {
        $sub = [];

        // ── (a) Project yang deadline-nya di periode ini, selesai tepat waktu? ──
        $jatuhTempo   = 0;
        $selesaiTepat = 0;

        foreach ($data['projects'] as $p) {
            if (! $p->deadline || ! $p->deadline->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                continue;
            }
            $jatuhTempo++;

            if ($p->status === Project::STATUS_COMPLETED && $p->completed_at
                && $p->completed_at->toDateString() <= $p->deadline->toDateString()) {
                $selesaiTepat++;
            }
        }

        if ($jatuhTempo > 0) {
            $sub['tepat_deadline'] = [
                'label'  => 'Project selesai tepat deadline',
                'nilai'  => $selesaiTepat . '/' . $jatuhTempo,
                'satuan' => 'project',
                'target' => '100%',
                'skor'   => (int) round(($selesaiTepat / $jatuhTempo) * 100),
            ];
        }

        // ── (b) Rata-rata progress project yang masih aktif ──
        // Sifatnya kondisi "sekarang", jadi hanya dinilai kalau periode mencakup hari ini.
        $aktif = $data['projects']->filter(fn ($p) => $p->status !== Project::STATUS_COMPLETED);

        if ($aktif->count() > 0 && $sertakanSekarang) {
            $total = 0;
            foreach ($aktif as $p) {
                $tasks   = $data['task_per_project'][$p->id] ?? [];
                $selesai = count(array_filter($tasks, fn ($t) => $t->status === 'Completed'));
                $total  += count($tasks) > 0 ? ($selesai / count($tasks)) * 100 : 0;
            }
            $rataProgress = $total / $aktif->count();

            $sub['progress_aktif'] = [
                'label'  => 'Rata-rata progress project aktif',
                'nilai'  => (int) round($rataProgress) . '%',
                'satuan' => '',
                'target' => '≥ 75%',
                'skor'   => (int) round(min(100, $rataProgress)),
            ];
        }

        // ── (c) Panjang siklus project (dibuat -> selesai) ──
        // CATATAN: data yang completed_at-nya LEBIH AWAL dari created_at (anomali data
        // demo/seeder) DILEWATI — bukan dipaksa dihitung, supaya angkanya tidak palsu.
        $siklus    = 0;
        $jumlahSiklus = 0;

        foreach ($data['projects'] as $p) {
            if ($p->status !== Project::STATUS_COMPLETED || ! $p->completed_at) {
                continue;
            }
            if (! $p->completed_at->between($start, $end)) {
                continue;
            }
            if ($p->completed_at->lessThan($p->created_at)) {
                continue;
            }

            $siklus += (float) $p->created_at->diffInHours($p->completed_at) / 24;
            $jumlahSiklus++;
        }

        if ($jumlahSiklus > 0) {
            $rataSiklus = $siklus / $jumlahSiklus;
            $sub['siklus_project'] = [
                'label'  => 'Rata-rata siklus penyelesaian project',
                'nilai'  => round($rataSiklus, 1),
                'satuan' => 'hari',
                'target' => 'maks ' . self::TARGET['siklus_hari'] . ' hari',
                'skor'   => $this->skorTurunkan($rataSiklus, self::TARGET['siklus_hari'], self::TURUN_PER_HARI['siklus']),
            ];
        }

        $skor = $this->rataSkor($sub);

        return [
            'tersedia' => $skor !== null,
            'skor'     => $skor,
            'catatan'  => count($sub) . ' indikator ketuntasan',
            'detail'   => ['sub' => $sub],
        ];
    }

    // ============================================================
    // BOTTLENECK — "menunggu tindakan Ketua Media"
    // ============================================================

    /**
     * Daftar hal yang MENUNGGU SENTUHAN dia. Ini bagian paling praktis buat Pembina:
     * bukan skor, tapi daftar kerjaan yang mandek.
     */
    public function bottleneck(array $data): array
    {
        $daftar = [];

        foreach ($data['projects'] as $p) {
            $taskCount = count($data['task_per_project'][$p->id] ?? []);

            // 1. Planning: task belum disusun / belum diajukan
            if ($p->status === Project::STATUS_PLANNING) {
                $hari = (int) $p->created_at->diffInDays(now());
                $daftar[] = [
                    'jenis'      => 'planning',
                    'label'      => $taskCount === 0 ? 'Task belum disusun' : 'Task siap, belum diajukan ke Pembina',
                    'project'    => $p->name,
                    'project_id' => $p->id,
                    'hari'       => $hari,
                    'urgensi'    => $hari >= 2 ? 'tinggi' : 'sedang',
                    'aksi'       => $taskCount === 0 ? 'Susun task' : 'Ajukan ke Pembina',
                ];
            }

            // 2. Review: belum dia ACC
            if ($p->status === Project::STATUS_REVIEW && ! $p->approved_by_ketua_at) {
                $hari = $p->review_requested_at ? (int) $p->review_requested_at->diffInDays(now()) : null;
                $daftar[] = [
                    'jenis'      => 'acc',
                    'label'      => 'Menunggu ACC Ketua Media',
                    'project'    => $p->name,
                    'project_id' => $p->id,
                    'hari'       => $hari,
                    'urgensi'    => ($hari === null || $hari >= 2) ? 'tinggi' : 'sedang',
                    'aksi'       => 'Beri ACC / minta revisi',
                ];
            }
        }

        // 3. Revisi yang masih menganggur (belum di-resolve)
        foreach ($data['revisions'] as $r) {
            if ($r->resolved_at) {
                continue;
            }
            $project = $data['project_map'][$r->project_id] ?? null;
            if (! $project) {
                continue;
            }

            $hari = (int) $r->created_at->diffInDays(now());
            $daftar[] = [
                'jenis'      => 'revisi',
                'label'      => 'Revisi belum tuntas',
                'project'    => $project->name,
                'project_id' => $project->id,
                'hari'       => $hari,
                'urgensi'    => $hari >= 3 ? 'tinggi' : 'sedang',
                'aksi'       => 'Dorong divisi menyelesaikan',
            ];
        }

        usort($daftar, function ($a, $b) {
            $u = ['tinggi' => 0, 'sedang' => 1];
            return ($u[$a['urgensi']] ?? 2) <=> ($u[$b['urgensi']] ?? 2)
                ?: (($b['hari'] ?? 0) <=> ($a['hari'] ?? 0));
        });

        return $daftar;
    }

    // ============================================================
    // TIMELINE "JEJAK KEPEMIMPINAN"
    // ============================================================

    public function timeline(array $data, User $ketua, Carbon $start, Carbon $end): array
    {
        $jejak       = [];
        $namaProject = $data['projects']->pluck('name', 'id');

        foreach ($data['projects'] as $p) {
            // Ajukan task ke Pembina
            if ((int) $p->task_submitted_by === (int) $ketua->id && $p->task_submitted_at
                && $p->task_submitted_at->between($start, $end)) {
                $jejak[] = [
                    'waktu' => $p->task_submitted_at,
                    'icon'  => 'fa-paper-plane',
                    'warna' => '#4e73df',
                    'judul' => 'Mengajukan task ke Pembina',
                    'isi'   => $p->name,
                    'link'  => $p->id,
                ];
            }

            // ACC dia
            if ($p->approved_by_ketua_at && $p->approved_by_ketua_at->between($start, $end)) {
                $jejak[] = [
                    'waktu' => $p->approved_by_ketua_at,
                    'icon'  => 'fa-check-circle',
                    'warna' => '#1cc88a',
                    'judul' => 'Memberi ACC project',
                    'isi'   => $p->name,
                    'link'  => $p->id,
                ];
            }
        }

        foreach ($data['revisions'] as $r) {
            if ((int) $r->user_id !== (int) $ketua->id || ! $r->created_at->between($start, $end)) {
                continue;
            }
            $jejak[] = [
                'waktu' => $r->created_at,
                'icon'  => 'fa-redo',
                'warna' => '#f59f0b',
                'judul' => 'Menulis catatan revisi',
                'isi'   => $namaProject[$r->project_id] ?? '-',
                'link'  => $r->project_id,
            ];
        }

        foreach ($data['meetings'] as $m) {
            if ((int) $m->created_by !== (int) $ketua->id || ! $m->created_at->between($start, $end)) {
                continue;
            }
            $jejak[] = [
                'waktu' => $m->created_at,
                'icon'  => 'fa-users',
                'warna' => '#8b5cf6',
                'judul' => 'Membuat rapat/notulen',
                'isi'   => $m->title,
                'link'  => null,
            ];
        }

        usort($jejak, fn ($a, $b) => $b['waktu'] <=> $a['waktu']);

        return array_slice($jejak, 0, 40);
    }

    // ============================================================
    // RINGKASAN ANGKA CEPAT (kartu atas)
    // ============================================================

    public function ringkasan(array $data, Carbon $start, Carbon $end): array
    {
        $aktif = $data['projects']->filter(fn ($p) => $p->status !== Project::STATUS_COMPLETED);

        $telat = $aktif->filter(fn ($p) =>
            $p->is_overdue || ($p->deadline && $p->deadline->copy()->endOfDay()->isPast())
        );

        $taskTerbuka = 0;
        $taskLewat   = 0;
        $selesai     = 0;
        $tepat       = 0;

        foreach ($data['task_per_project'] as $tasks) {
            foreach ($tasks as $t) {
                if ($t->status === 'Completed') {
                    if ($t->completed_at && $t->completed_at->between($start, $end)) {
                        $selesai++;
                        if ($t->task_date && $t->completed_at->toDateString() <= $t->task_date->toDateString()) {
                            $tepat++;
                        }
                    }
                    continue;
                }

                $taskTerbuka++;
                if ($t->task_date && $t->task_date->copy()->endOfDay()->isPast()) {
                    $taskLewat++;
                }
            }
        }

        $selesaiPeriode = $data['projects']->filter(fn ($p) =>
            $p->status === Project::STATUS_COMPLETED && $p->completed_at && $p->completed_at->between($start, $end)
        );

        return [
            'divisi'          => count($data['divisions']),
            'project_aktif'   => $aktif->count(),
            'project_telat'   => $telat->count(),
            'project_selesai' => $selesaiPeriode->count(),
            'task_terbuka'    => $taskTerbuka,
            'task_lewat'      => $taskLewat,
            'task_selesai'    => $selesai,
            'ketepatan_waktu' => $selesai > 0 ? (int) round(($tepat / $selesai) * 100) : null,
        ];
    }

    // ============================================================
    // HELPER PERIODE
    // ============================================================

    /**
     * Apakah periode yang dilihat mencakup HARI INI?
     * Dipakai untuk memutuskan sub-metrik "kondisi sekarang" boleh ikut dinilai.
     */
    private function periodeTermasukHariIni(Carbon $end): bool
    {
        return $end->greaterThanOrEqualTo(now()->copy()->startOfDay());
    }

    /**
     * Ada jejak data apa pun di periode ini? Kalau tidak ada, penilaian
     * dikembalikan KOSONG — lebih jujur daripada memberi skor dari ketiadaan data.
     */
    private function adaJejakData(array $data, Carbon $start, Carbon $end): bool
    {
        $s = $start->copy()->startOfDay();
        $e = $end->copy()->endOfDay();

        $ada = fn ($waktu) => $waktu && $waktu->between($s, $e);

        // Task apa pun yang berjadwal / selesai di periode ini
        foreach ($data['tasks'] as $t) {
            if ($ada($t->completed_at) || ($t->task_date && $t->task_date->between($s, $e))) {
                return true;
            }
        }

        // Project yang dibuat / selesai / berdeadline di periode ini
        foreach ($data['projects'] as $p) {
            if ($ada($p->created_at) || $ada($p->completed_at)
                || ($p->deadline && $p->deadline->between($s, $e))) {
                return true;
            }
        }

        // Jejak kegiatan lain
        foreach ($data['revisions'] as $r) {
            if ($ada($r->created_at)) {
                return true;
            }
        }
        foreach ($data['meetings'] as $m) {
            if ($ada($m->created_at)) {
                return true;
            }
        }
        foreach ($data['login_rewards'] as $r) {
            if (Carbon::parse($r->reward_date)->between($s, $e)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hasil evaluasi KOSONG (tidak ada data di periode) — bentuknya sama dengan
     * hasil normal supaya view tidak perlu penanganan khusus.
     */
    private function hasilKosong(array $data = []): array
    {
        $pilar = [];

        foreach (self::PILAR as $kunci => $info) {
            $pilar[$kunci] = [
                'tersedia' => false,
                'skor'     => null,
                'catatan'  => 'Belum ada data di periode ini',
                'detail'   => [],
            ];
        }

        return [
            'skor'          => null,
            'label'         => 'Data belum cukup',
            'warna'         => 'secondary',
            'pilar'         => $pilar,
            'pilar_dinilai' => [],
            'divisi'        => [],
            'bottleneck'    => [],
            'timeline'      => [],
            'ringkasan'     => [
                'divisi'          => count($data['divisions'] ?? []),
                'project_aktif'   => 0,
                'project_telat'   => 0,
                'project_selesai' => 0,
                'task_terbuka'    => 0,
                'task_lewat'      => 0,
                'task_selesai'    => 0,
                'ketepatan_waktu' => null,
            ],
        ];
    }

    // ============================================================
    // HELPER SKOR
    // ============================================================

    /**
     * Skor untuk metrik "makin kecil makin baik".
     * nilai <= target → 100, lewat target → dikurangi per satuan kelebihan.
     */
    private function skorTurunkan(float $nilai, float $target, float $penaltiPerUnit): int
    {
        if ($nilai <= $target) {
            return 100;
        }
        return (int) round(max(0, min(100, 100 - (($nilai - $target) * $penaltiPerUnit))));
    }

    /**
     * Skor untuk metrik "makin besar makin baik" (capaian / target), maksimal 100.
     */
    private function skorNaikkan(float $capai, float $target): int
    {
        if ($target <= 0) {
            return 100;
        }
        return (int) round(max(0, min(100, ($capai / $target) * 100)));
    }

    /**
     * Rata-rata skor sub-metrik. Return null kalau TIDAK ADA sub-metrik
     * (supaya pilar tanpa data tidak dianggap 0 / 100).
     */
    private function rataSkor(array $sub): ?int
    {
        if (count($sub) === 0) {
            return null;
        }

        $skor = array_column($sub, 'skor');
        return (int) round(array_sum($skor) / count($skor));
    }

    /**
     * Label + warna badge dari skor total.
     */
    public function labelSkor(?int $skor): array
    {
        if ($skor === null) {
            return ['label' => 'Data belum cukup', 'warna' => 'secondary'];
        }
        if ($skor >= 85) {
            return ['label' => 'Sangat Baik', 'warna' => 'success'];
        }
        if ($skor >= 70) {
            return ['label' => 'Baik', 'warna' => 'info'];
        }
        if ($skor >= 55) {
            return ['label' => 'Cukup — perlu perhatian', 'warna' => 'warning'];
        }
        return ['label' => 'Perlu Perhatian Serius', 'warna' => 'danger'];
    }
}
