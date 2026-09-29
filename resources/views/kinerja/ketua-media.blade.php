@extends('layouts.app')

@section('content')
@php
    $pilar        = $hasil['pilar'] ?? [];
    $ringkas      = $hasil['ringkasan'] ?? [];
    $bottleneck   = $hasil['bottleneck'] ?? [];
    $divisi       = $hasil['divisi'] ?? [];
    $timeline     = $hasil['timeline'] ?? [];
    $skorTotal    = $hasil['skor'] ?? null;
    $warnaSkor    = $hasil['warna'] ?? 'secondary';
    $totalBottleneck = count($bottleneck);

    $warnaLampu = [
        'hijau' => '#1cc88a',
        'kuning' => '#f59f0b',
        'merah' => '#e74a3b',
        'abu'   => '#858796',
    ];
@endphp

<style>
/* ============================================================
   HALAMAN KINERJA KETUA MEDIA
   ============================================================ */
.kinerja-hero {
    background: linear-gradient(135deg, #4e73df 0%, #8b5cf6 100%);
    border-radius: 14px;
    color: #fff;
    padding: 26px 28px;
    box-shadow: 0 10px 26px rgba(78, 115, 223, .25);
}
.kinerja-skor-bulat {
    width: 132px; height: 132px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: rgba(255,255,255,.16);
    border: 4px solid rgba(255,255,255,.55);
    font-weight: 800; line-height: 1;
    box-shadow: 0 0 0 10px rgba(255,255,255,.07) inset;
}
.kinerja-skor-bulat .angka { font-size: 2.9rem; }
.kinerja-skor-bulat .dari  { font-size: .72rem; letter-spacing: 2px; opacity: .85; }

.kartu-pilar {
    border-radius: 12px; background: #fff; height: 100%;
    border: 1px solid rgba(78, 115, 223, .12);
    box-shadow: 0 4px 14px rgba(78, 115, 223, .06);
    transition: transform .2s ease, box-shadow .2s ease;
}
.kartu-pilar:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(78,115,223,.14); }
.kartu-pilar .ikon-pilar {
    width: 42px; height: 42px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.05rem;
}
.kartu-pilar .nilai-pilar { font-size: 1.95rem; font-weight: 800; line-height: 1; }

.papan-divisi { border-radius: 12px; border: 1px solid rgba(0,0,0,.06); background: #fff; }
.lampu-divisi { width: 14px; height: 14px; border-radius: 50%; display: inline-block; }
.baris-divisi:hover { background: rgba(78, 115, 223, .04); }

.jejak-item { position: relative; padding-left: 42px; padding-bottom: 18px; }
.jejak-item::before {
    content: ''; position: absolute; left: 15px; top: 30px; bottom: 0; width: 2px;
    background: rgba(78, 115, 223, .16);
}
.jejak-item:last-child::before { display: none; }
.jejak-ikon {
    position: absolute; left: 0; top: 0;
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .78rem;
}

.catatan-item { border-left: 3px solid #8b5cf6; background: #faf9ff; border-radius: 8px; }

@media print {
    .no-print, .sidebar, .topbar, .navbar, footer, #accordionSidebar { display: none !important; }
    #content-wrapper { margin-left: 0 !important; }
    body { background: #fff !important; }
    .kinerja-hero { box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card, .kartu-pilar, .papan-divisi { box-shadow: none !important; break-inside: avoid; }
}
</style>

<!-- ============================ HERO / SKOR TOTAL ============================ -->
<div class="kinerja-hero mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="text-uppercase mb-1" style="letter-spacing:2px;font-size:.72rem;opacity:.85">
                <i class="fas fa-user-tie mr-1"></i> Pemantauan Kinerja
            </div>
            <h3 class="mb-1 font-weight-bold text-white">
                {{ $ketua->name ?? 'Belum ada Ketua Media' }}
            </h3>
            <div style="opacity:.9;font-size:.9rem">
                Periode {{ $startDate->translatedFormat('d M Y') }} – {{ $endDate->translatedFormat('d M Y') }}
                ({{ $filterDays }} hari)
                @if(!empty($hasil['pilar_dinilai']))
                    · Dinilai dari: {{ implode(', ', $hasil['pilar_dinilai']) }}
                @endif
            </div>
            <div class="mt-2" style="font-size:.86rem;opacity:.92">
                Tugas Ketua Media: <strong>memastikan semua tugas &amp; project tiap divisi berjalan lancar</strong>.
                Penilaian dihitung dari 4 pilar — bukan dari berapa task yang dia kerjakan sendiri.
            </div>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="d-flex align-items-center justify-content-lg-end">
                <div class="kinerja-skor-bulat mr-3">
                    <div class="angka">{{ $skorTotal !== null ? $skorTotal : '—' }}</div>
                    <div class="dari">DARI 100</div>
                </div>
                <div>
                    <div class="badge badge-{{ $warnaSkor }} px-3 py-2 mb-2" style="font-size:.8rem">
                        {{ $hasil['label'] ?? 'Data belum cukup' }}
                    </div>
                    @if($ringkas)
                    <div style="font-size:.82rem;opacity:.92">
                        {{ $ringkas['divisi'] ?? 0 }} divisi · {{ $ringkas['project_aktif'] ?? 0 }} project aktif<br>
                        {{ $ringkas['task_terbuka'] ?? 0 }} task terbuka · {{ $ringkas['task_lewat'] ?? 0 }} lewat tanggal
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ FILTER & AKSI ============================ -->
<div class="card shadow-sm mb-4 no-print" style="border-radius:12px">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex flex-wrap align-items-center">
                <span class="text-muted mr-2 mb-2" style="font-size:.85rem">Periode:</span>
                @foreach([7 => '7 Hari', 30 => '30 Hari', 90 => '90 Hari'] as $nilai => $teks)
                    <a href="{{ route('kinerja.ketuaMedia', ['days' => $nilai, 'ketua' => $ketua->id ?? null]) }}"
                       class="btn btn-sm {{ $filterDays == $nilai ? 'btn-primary' : 'btn-outline-primary' }} mr-2 mb-2">
                        {{ $teks }}
                    </a>
                @endforeach

                @if($daftarKetua->count() > 1)
                    <span class="text-muted mx-2 mb-2" style="font-size:.85rem">|</span>
                    <span class="text-muted mr-2 mb-2" style="font-size:.85rem">Ketua Media:</span>
                    @foreach($daftarKetua as $k)
                        <a href="{{ route('kinerja.ketuaMedia', ['days' => $filterDays, 'ketua' => $k->id]) }}"
                           class="btn btn-sm {{ ($ketua->id ?? null) == $k->id ? 'btn-dark' : 'btn-outline-dark' }} mr-2 mb-2">
                            {{ $k->name }}
                        </a>
                    @endforeach
                @endif
            </div>

            <div class="mb-2">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-print mr-1"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>
    </div>
</div>

@if(!$ketua)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        Belum ada user dengan peran <strong>Ketua Media</strong>. Tambahkan dulu di
        <a href="{{ route('team.index') }}">Team Management</a> supaya halaman ini bisa menilai.
    </div>
@else

<!-- ============================ 4 KARTU PILAR ============================ -->
<div class="row mb-4">
    @foreach($pilarInfo as $kunci => $info)
        @php
            $p      = $pilar[$kunci] ?? ['skor' => null, 'tersedia' => false, 'catatan' => '-', 'detail' => []];
            $skorP  = $p['skor'] ?? null;
            $warnaP = $skorP === null ? '#adb5bd' : ($skorP >= 85 ? '#1cc88a' : ($skorP >= 70 ? '#f59f0b' : '#e74a3b'));
        @endphp
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="kartu-pilar">
                <div class="p-3">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <div class="ikon-pilar mr-2" style="background: {{ $info['warna'] }}">
                                <i class="fas {{ $info['icon'] }}"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold" style="font-size:.9rem">{{ $info['label'] }}</div>
                                <div class="text-muted" style="font-size:.72rem">
                                    bobot {{ (int) round($bobot[$kunci] * 100) }}% · {{ $p['catatan'] ?? '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="nilai-pilar" style="color: {{ $warnaP }}">
                            {{ $skorP !== null ? $skorP : '—' }}
                        </div>
                    </div>

                    <div class="progress mb-2" style="height:7px;border-radius:6px">
                        <div class="progress-bar" role="progressbar"
                             style="width: {{ $skorP ?? 0 }}%; background: {{ $warnaP }}"></div>
                    </div>

                    @if(!empty($p['detail']['sub']))
                        <table class="table table-sm mb-0" style="font-size:.76rem">
                            @foreach($p['detail']['sub'] as $sub)
                                <tr>
                                    <td class="pl-0 py-1 text-muted" style="border-top:1px dashed rgba(0,0,0,.07)">
                                        {{ $sub['label'] }}
                                        <div class="text-dark">
                                            <strong>{{ $sub['nilai'] }}</strong> {{ $sub['satuan'] }}
                                            <span class="text-muted">/ {{ $sub['target'] }}</span>
                                        </div>
                                    </td>
                                    <td class="pr-0 py-1 text-right align-middle" style="border-top:1px dashed rgba(0,0,0,.07);width:52px">
                                        <span class="badge badge-{{ $sub['skor'] >= 85 ? 'success' : ($sub['skor'] >= 70 ? 'warning' : 'danger') }}">
                                            {{ $sub['skor'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <div class="text-muted" style="font-size:.78rem">
                            {{ $p['catatan'] ?? 'Belum ada data' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- ============================ RINGKASAN ANGKA ============================ -->
<div class="row mb-4">
    @php
        $kartuRingkas = [
            ['label' => 'Divisi dipantau',    'nilai' => $ringkas['divisi'] ?? 0,          'icon' => 'fa-sitemap',      'warna' => 'primary'],
            ['label' => 'Project aktif',      'nilai' => $ringkas['project_aktif'] ?? 0,   'icon' => 'fa-project-diagram', 'warna' => 'info'],
            ['label' => 'Project telat',      'nilai' => $ringkas['project_telat'] ?? 0,   'icon' => 'fa-clock',        'warna' => 'danger'],
            ['label' => 'Task terbuka',       'nilai' => $ringkas['task_terbuka'] ?? 0,    'icon' => 'fa-tasks',        'warna' => 'warning'],
            ['label' => 'Task lewat tanggal', 'nilai' => $ringkas['task_lewat'] ?? 0,     'icon' => 'fa-exclamation-triangle', 'warna' => 'danger'],
            ['label' => 'Ketepatan waktu',    'nilai' => ($ringkas['ketepatan_waktu'] ?? null) !== null ? $ringkas['ketepatan_waktu'] . '%' : '—', 'icon' => 'fa-check-double', 'warna' => 'success'],
        ];

        // Warna border kartu (eksplisit, tidak bergantung CSS variable Bootstrap)
        $warnaBorder = [
            'primary' => '#4e73df',
            'info'    => '#36b9cc',
            'danger'  => '#e74a3b',
            'warning' => '#f59f0b',
            'success' => '#1cc88a',
        ];
    @endphp

    @foreach($kartuRingkas as $k)
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm h-100" style="border-radius:11px;border-left:4px solid {{ $warnaBorder[$k['warna']] ?? '#4e73df' }}">
                <div class="card-body py-3">
                    <div class="text-muted text-uppercase" style="font-size:.63rem;letter-spacing:1px">{{ $k['label'] }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $k['nilai'] }}</div>
                        <i class="fas {{ $k['icon'] }} text-{{ $k['warna'] }}" style="opacity:.55"></i>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row mb-4">
    <!-- ============================ BOTTLENECK ============================ -->
    <div class="col-lg-7 mb-3">
        <div class="card shadow-sm h-100" style="border-radius:12px">
            <div class="card-header py-3 d-flex align-items-center justify-content-between bg-white">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-hourglass-half mr-1"></i> Menunggu Tindakan Ketua Media
                </h6>
                <span class="badge badge-{{ $totalBottleneck > 0 ? 'danger' : 'success' }}">
                    {{ $totalBottleneck }} item
                </span>
            </div>
            <div class="card-body p-0">
                @if($totalBottleneck === 0)
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <div class="text-muted" style="font-size:.88rem">
                            Tidak ada yang menunggu tindakannya. Semua project berjalan.
                        </div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:.83rem">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-3">Project</th>
                                    <th>Yang menunggu</th>
                                    <th class="text-center">Lama</th>
                                    <th class="pr-3 text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bottleneck as $b)
                                    <tr>
                                        <td class="pl-3">
                                            <a href="{{ route('projects.show', $b['project_id']) }}" class="font-weight-bold">
                                                {{ \Illuminate\Support\Str::limit($b['project'], 32) }}
                                            </a>
                                            <div>
                                                <span class="badge badge-{{ $b['urgensi'] === 'tinggi' ? 'danger' : 'warning' }}">
                                                    {{ $b['urgensi'] === 'tinggi' ? 'Mendesak' : 'Perlu dicek' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-muted">{{ $b['label'] }}</td>
                                        <td class="text-center">
                                            @if($b['hari'] === null)
                                                <span class="text-muted">—</span>
                                            @else
                                                <strong>{{ $b['hari'] }}</strong> hari
                                            @endif
                                        </td>
                                        <td class="pr-3 text-right text-muted" style="font-size:.78rem">{{ $b['aksi'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================ GRAFIK ============================ -->
    <div class="col-lg-5 mb-3">
        <div class="card shadow-sm mb-3" style="border-radius:12px">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie mr-1"></i> Profil 4 Pilar</h6>
            </div>
            <div class="card-body">
                <canvas id="radarPilar" height="220"></canvas>
            </div>
        </div>

        <div class="card shadow-sm" style="border-radius:12px">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-line mr-1"></i> Tren Skor 6 Bulan</h6>
            </div>
            <div class="card-body">
                <canvas id="trenSkor" height="150"></canvas>
                <div class="text-muted mt-2" style="font-size:.72rem">
                    Bulan tanpa data ditampilkan kosong (garis terputus) — bukan dinilai 0.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ PAPAN DIVISI ============================ -->
<div class="card shadow-sm mb-4" style="border-radius:12px">
    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-th-large mr-1"></i> Papan 4 Divisi</h6>
        <span class="text-muted" style="font-size:.75rem">
            Skor dari data periode ini · kolom "sekarang" = kondisi hari ini
        </span>
    </div>
    <div class="card-body p-0">
        @if(count($divisi) === 0)
            <div class="text-muted text-center py-4" style="font-size:.88rem">
                Belum ada divisi operasional (user dengan peran Kepala Divisi / Anggota).
            </div>
        @else
            <div class="table-responsive">
                <table class="table mb-0" style="font-size:.84rem">
                    <thead class="bg-light">
                        <tr>
                            <th class="pl-3">Divisi</th>
                            <th class="text-center">Anggota</th>
                            <th class="text-center">Project aktif</th>
                            <th class="text-center">Task terbuka</th>
                            <th class="text-center">Task lewat tgl</th>
                            <th class="text-center">Skor periode</th>
                            <th class="pr-3">Masalah utama</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($divisi as $d)
                            <tr class="baris-divisi">
                                <td class="pl-3">
                                    <span class="lampu-divisi mr-2" style="background: {{ $warnaLampu[$d['lampu']] ?? '#adb5bd' }}"></span>
                                    <strong>{{ $d['nama'] }}</strong>
                                </td>
                                <td class="text-center">{{ $d['anggota'] }}</td>
                                <td class="text-center">
                                    {{ $d['project_aktif'] }}
                                    @if($d['project_telat'] > 0)
                                        <span class="badge badge-danger ml-1" title="project telat deadline">
                                            {{ $d['project_telat'] }} telat
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $d['task_terbuka'] }}</td>
                                <td class="text-center">
                                    @if($d['task_lewat'] > 0)
                                        <span class="badge badge-danger">{{ $d['task_lewat'] }}</span>
                                    @else
                                        <span class="badge badge-success">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $d['skor'] === null ? 'secondary' : ($d['skor'] >= 85 ? 'success' : ($d['skor'] >= 70 ? 'warning' : 'danger')) }} px-2 py-1">
                                        {{ $d['skor'] !== null ? $d['skor'] : '—' }}
                                    </span>
                                    @if($d['ketepatan'] !== null)
                                        <div class="text-muted" style="font-size:.68rem">tepat {{ $d['ketepatan'] }}%</div>
                                    @endif
                                </td>
                                <td class="pr-3 text-muted" style="font-size:.78rem">
                                    {{ implode(' · ', $d['masalah']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="row mb-4">
    <!-- ============================ TIMELINE ============================ -->
    <div class="col-lg-6 mb-3">
        <div class="card shadow-sm h-100" style="border-radius:12px">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-stream mr-1"></i> Jejak Kepemimpinan (periode ini)
                </h6>
            </div>
            <div class="card-body" style="max-height:430px;overflow-y:auto">
                @if(count($timeline) === 0)
                    <div class="text-muted text-center py-3" style="font-size:.86rem">
                        Belum ada jejak kegiatan (ACC, ajukan task, revisi, rapat) di periode ini.
                    </div>
                @else
                    @foreach($timeline as $j)
                        <div class="jejak-item">
                            <div class="jejak-ikon" style="background: {{ $j['warna'] }}">
                                <i class="fas {{ $j['icon'] }}"></i>
                            </div>
                            <div style="font-size:.84rem">
                                <strong>{{ $j['judul'] }}</strong>
                                @if($j['link'])
                                    · <a href="{{ route('projects.show', $j['link']) }}">{{ \Illuminate\Support\Str::limit($j['isi'], 38) }}</a>
                                @else
                                    · {{ \Illuminate\Support\Str::limit($j['isi'], 38) }}
                                @endif
                            </div>
                            <div class="text-muted" style="font-size:.72rem">
                                <i class="far fa-clock mr-1"></i>{{ $j['waktu']->translatedFormat('d M Y, H:i') }}
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- ============================ CATATAN EVALUASI ============================ -->
    <div class="col-lg-6 mb-3">
        <div class="card shadow-sm h-100" style="border-radius:12px">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-pen-nib mr-1"></i> Catatan Evaluasi Pembina
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('kinerja.evaluasi.store') }}" method="POST" class="mb-4 no-print">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $ketua->id }}">
                    <input type="hidden" name="days" value="{{ $filterDays }}">

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label style="font-size:.78rem" class="mb-1">Periode mulai</label>
                            <input type="date" name="period_start" class="form-control form-control-sm"
                                   value="{{ $startDate->toDateString() }}">
                        </div>
                        <div class="form-group col-6">
                            <label style="font-size:.78rem" class="mb-1">Periode akhir</label>
                            <input type="date" name="period_end" class="form-control form-control-sm"
                                   value="{{ $endDate->toDateString() }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:.78rem" class="mb-1">
                            Catatan untuk {{ $ketua->name }}
                        </label>
                        <textarea name="note" rows="3" class="form-control form-control-sm @error('note') is-invalid @enderror"
                                  placeholder="Contoh: Broadcast masih ada 3 task lewat tanggal, tolong dikejar minggu ini.">{{ old('note') }}</textarea>
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted" style="font-size:.72rem">
                            Skor pada periode ini otomatis ikut tercatat sebagai snapshot
                            ({{ $skorTotal !== null ? $skorTotal : '—' }} · {{ $hasil['label'] ?? '-' }}).
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i> Simpan Catatan
                    </button>
                </form>

                <hr>

                <div style="max-height:300px;overflow-y:auto">
                    @if($catatan->count() === 0)
                        <div class="text-muted text-center py-3" style="font-size:.84rem">
                            Belum ada catatan evaluasi.
                        </div>
                    @else
                        @foreach($catatan as $c)
                            <div class="catatan-item p-3 mb-2">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div style="font-size:.72rem" class="text-muted">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        {{ $c->period_start ? $c->period_start->translatedFormat('d M Y') : '—' }}
                                        @if($c->period_end) – {{ $c->period_end->translatedFormat('d M Y') }} @endif
                                        @if($c->score !== null)
                                            · <span class="badge badge-light border">skor {{ $c->score }}</span>
                                        @endif
                                        @if($c->rating)
                                            · {{ $c->rating }}
                                        @endif
                                        @if($c->evaluator)
                                            · oleh {{ $c->evaluator->name }}
                                        @endif
                                    </div>
                                    <form action="{{ route('kinerja.evaluasi.destroy', $c->id) }}" method="POST"
                                          class="no-print" onsubmit="return confirm('Hapus catatan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="mt-1" style="font-size:.85rem;white-space:pre-line">{{ $c->note }}</div>
                                <div class="text-muted mt-1" style="font-size:.68rem">
                                    ditulis {{ $c->created_at->translatedFormat('d M Y, H:i') }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ CARA HITUNG (transparansi) ============================ -->
<div class="card shadow-sm mb-4" style="border-radius:12px">
    <div class="card-header py-3 bg-white" data-toggle="collapse" data-target="#caraHitung" style="cursor:pointer">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-calculator mr-1"></i> Bagaimana angka ini dihitung? (klik untuk buka)
        </h6>
    </div>
    <div class="collapse" id="caraHitung">
        <div class="card-body" style="font-size:.84rem">
            <div class="row">
                <div class="col-md-6">
                    <strong>Bobot pilar:</strong>
                    <ul class="pl-3 mt-2 mb-3">
                        @foreach($pilarInfo as $kunci => $info)
                            <li>{{ $info['label'] }} — <strong>{{ (int) round($bobot[$kunci] * 100) }}%</strong></li>
                        @endforeach
                    </ul>

                    <div class="alert alert-info py-2 mb-0" style="font-size:.8rem">
                        <i class="fas fa-info-circle mr-1"></i>
                        Kalau sebuah pilar <strong>belum ada datanya</strong>, pilar itu dikeluarkan dari
                        penilaian dan bobotnya dibagikan ke pilar lain — jadi skor tidak "kembung"
                        gara-gara data kosong, dan tidak juga dihukum.
                    </div>
                </div>
                <div class="col-md-6">
                    <strong>Ambang yang dipakai:</strong>
                    <table class="table table-sm mt-2" style="font-size:.8rem">
                        <tbody>
                            <tr><td>Lama menyusun task</td><td>maks {{ $target['susun_task_hari'] }} hari</td></tr>
                            <tr><td>Tunggu ACC Ketua Media</td><td>maks {{ $target['acc_hari'] }} hari</td></tr>
                            <tr><td>Nyangkut di Planning</td><td>maks {{ $target['planning_hari'] }} hari</td></tr>
                            <tr><td>Siklus project</td><td>maks {{ $target['siklus_hari'] }} hari</td></tr>
                            <tr><td>Rapat per bulan</td><td>≥ {{ $target['rapat_per_bulan'] }}</td></tr>
                            <tr><td>Catatan revisi per bulan</td><td>≥ {{ $target['revisi_per_bulan'] }}</td></tr>
                            <tr><td>Hari aktif per bulan</td><td>≥ {{ $target['hari_aktif_per_bulan'] }}</td></tr>
                        </tbody>
                    </table>
                    <div class="text-muted" style="font-size:.78rem">
                        Potongan maksimum per jenis masalah:
                        task terlambat {{ $penalti['task_lewat'] }} poin ·
                        project telat {{ $penalti['project_telat'] }} poin ·
                        ketepatan waktu {{ $penalti['ketepatan'] }} poin.
                        Semua ambang ini bisa disetel di
                        <code>app/Services/KetuaMediaPerformance.php</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endif
@endsection

@push('scripts')
@if($ketua)
<script src="{{ asset('sb-admin-2/vendor/chart.js/Chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---------- Radar 4 pilar ----------
    var radarEl = document.getElementById('radarPilar');
    if (radarEl && typeof Chart !== 'undefined') {
        new Chart(radarEl, {
            type: 'radar',
            data: {
                labels: {!! json_encode(array_values(array_map(fn ($i) => $i['label'], $pilarInfo))) !!},
                datasets: [{
                    label: 'Skor',
                    data: {!! json_encode(array_map(fn ($k) => $pilar[$k]['skor'] ?? 0, array_keys($pilarInfo))) !!},
                    backgroundColor: 'rgba(78, 115, 223, 0.22)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                legend: { display: false },
                scale: {
                    ticks: { beginAtZero: true, max: 100, stepSize: 25, fontSize: 10 },
                    pointLabels: { fontSize: 11 }
                }
            }
        });
    }

    // ---------- Tren 6 bulan (null = garis terputus) ----------
    var trenEl = document.getElementById('trenSkor');
    if (trenEl && typeof Chart !== 'undefined') {
        new Chart(trenEl, {
            type: 'line',
            data: {
                labels: {!! json_encode($tren['label']) !!},
                datasets: [{
                    label: 'Skor Kinerja',
                    data: {!! json_encode($tren['data']) !!},
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    spanGaps: false,
                    pointRadius: 4,
                    pointBackgroundColor: '#8b5cf6'
                }]
            },
            options: {
                responsive: true,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function (item) {
                            return item.yLabel === null ? 'belum ada data' : 'Skor: ' + item.yLabel;
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true, max: 100, stepSize: 25, fontSize: 10 }
                    }],
                    xAxes: [{ ticks: { fontSize: 10 } }]
                }
            }
        });
    }
});
</script>
@endif
@endpush
