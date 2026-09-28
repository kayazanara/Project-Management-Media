@extends('layouts.app')

@section('content')
<style>
@keyframes tdFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes tdModalIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes tdGlowPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 159, 11, 0.4); }
    50%      { box-shadow: 0 0 0 12px rgba(245, 159, 11, 0); }
}
.td-card {
    animation: tdFadeInUp 0.5s ease-out both;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgba(139, 92, 246, 0.10);
}
.td-card:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 12px 28px rgba(78, 115, 223, 0.18) !important;
}
.td-card.rank-1 {
    border: 2px solid #f59f0b;
    animation: tdFadeInUp 0.5s ease-out both, tdGlowPulse 3s ease-in-out infinite;
}
.td-card.rank-2 { border: 2px solid #adb5bd; }
.td-card.rank-3 { border: 2px solid #d9480f; }
.td-banner {
    height: 90px;
    position: relative;
    background-size: cover;
    background-position: center;
}
.td-banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.0) 0%, rgba(0,0,0,0.15) 100%);
}
.td-avatar-wrap {
    margin-top: -45px;
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
}
.td-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid #fff;
    object-fit: cover;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}
.td-avatar-initials {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}
.td-rank-badge {
    position: absolute;
    top: -6px;
    right: calc(50% - 50px);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    color: #fff;
    border: 3px solid #fff;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
    z-index: 3;
}
.td-rank-1 { background: linear-gradient(135deg, #f59f0b, #ffc107); }
.td-rank-2 { background: linear-gradient(135deg, #adb5bd, #ced4da); }
.td-rank-3 { background: linear-gradient(135deg, #d9480f, #f76707); }
.td-rank-n { background: #6c757d; }
.td-expand-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-out, opacity 0.3s ease-out;
    opacity: 0;
}
.td-card.expanded .td-expand-body {
    max-height: 600px;
    opacity: 1;
    padding: 16px 16px 20px;
    border-top: 1px dashed #e3e6f0;
}
.td-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.td-modal-backdrop.show {
    display: flex;
    animation: tdFadeInUp 0.2s ease-out;
}
.td-modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: tdModalIn 0.35s cubic-bezier(0.22, 1, 0.36, 1);
    position: relative;
}
.td-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 16px;
}
.td-modal-close:hover {
    background: #fff;
    transform: rotate(90deg);
}
.td-stat-box {
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border: 1px solid rgba(139, 92, 246, 0.10);
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    transition: all 0.2s;
}
.td-stat-box:hover {
    border-color: rgba(139, 92, 246, 0.30);
    transform: translateY(-2px);
}
.td-load-more {
    transition: all 0.25s;
}
.td-load-more:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(78, 115, 223, 0.25);
}
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-bar mr-2"></i> Monitoring Tim
        </h1>
        <small class="text-muted">Pantau performa, workload, dan kesehatan tim media</small>
    </div>
    <form action="{{ route('monitoring.index') }}" method="GET" class="form-inline">
        <input type="hidden" name="tab" value="{{ request('tab', 'performa') }}">
        <label class="small text-muted mr-2 mb-0">Range:</label>
        <select name="days" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="7"  {{ $filterDays == 7  ? 'selected' : '' }}>7 Hari</option>
            <option value="30" {{ $filterDays == 30 ? 'selected' : '' }}>30 Hari</option>
            <option value="90" {{ $filterDays == 90 ? 'selected' : '' }}>90 Hari</option>
        </select>
    </form>
</div>

<ul class="nav nav-tabs mb-4" id="monitoringTab" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ request('tab', 'performa') === 'performa' ? 'active' : '' }}"
           id="tab-performa" data-toggle="tab" href="#performa" role="tab">
            <i class="fas fa-trophy mr-1"></i> Performa Anggota
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'workload' ? 'active' : '' }}"
           id="tab-workload" data-toggle="tab" href="#workload" role="tab">
            <i class="fas fa-balance-scale mr-1"></i> Workload Balance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'deadline' ? 'active' : '' }}"
           id="tab-deadline" data-toggle="tab" href="#deadline" role="tab">
            <i class="fas fa-clock mr-1"></i> Deadline Adherence
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'alert' ? 'active' : '' }}"
           id="tab-alert" data-toggle="tab" href="#alert" role="tab">
            <i class="fas fa-bell mr-1"></i> Alert Otomatis
            @if(count($alerts) > 0)
                <span class="badge badge-danger ml-1">{{ count($alerts) }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'directory' ? 'active' : '' }}"
           id="tab-directory" data-toggle="tab" href="#directory" role="tab">
            <i class="fas fa-users mr-1"></i> Team Directory
            <span class="badge badge-primary ml-1">{{ count($directory) }}</span>
        </a>
    </li>
</ul>

<div class="tab-content" id="monitoringTabContent">

    {{-- TAB 1: PERFORMA ANGGOTA --}}
    <div class="tab-pane fade {{ request('tab', 'performa') === 'performa' ? 'show active' : '' }}" id="performa" role="tabpanel">
        <div class="row mb-4">
            @foreach($divisionSummary as $div)
                <div class="col-md-3 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body py-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                {{ $div['label'] }}
                            </div>
                            <div class="h4 mb-1 font-weight-bold text-gray-800">
                                {{ $div['total_points'] }} poin
                            </div>
                            <small class="text-muted">
                                {{ $div['member_count'] }} anggota •
                                rata-rata {{ $div['avg_points'] }} poin •
                                on-time {{ $div['on_time_rate'] }}%
                            </small>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list-ol mr-1"></i> Leaderboard Anggota ({{ count($members) }})
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">#</th>
                                <th>Nama</th>
                                <th>Divisi</th>
                                <th class="text-center">Poin</th>
                                <th class="text-center">Task Selesai</th>
                                <th class="text-center">On-Time</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">On-Time Rate</th>
                                <th class="text-center">Login Terakhir</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members as $idx => $m)
                                <tr>
                                    <td>
                                        @if($idx < 3)
                                            <span class="badge badge-{{ $idx == 0 ? 'warning' : ($idx == 1 ? 'secondary' : 'danger') }}">
                                                #{{ $idx + 1 }}
                                            </span>
                                        @else
                                            <span class="text-muted">#{{ $idx + 1 }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($m['avatar'])
                                                <img src="{{ asset('storage/avatars/' . $m['avatar']) }}"
                                                     class="rounded-circle mr-2"
                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle bg-primary text-white mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 32px; height: 32px; font-size: 12px; font-weight: 700;">
                                                    {{ strtoupper(substr($m['name'], 0, 2)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="font-weight-bold">{{ $m['name'] }}</div>
                                                <small class="text-muted">
                                                    @if($m['division_role'] === 'ketua_media')
                                                        <span class="badge badge-warning badge-sm">Ketua Media</span>
                                                    @elseif($m['division_role'] === 'kepala_divisi')
                                                        <span class="badge badge-info badge-sm">Kepala Divisi</span>
                                                    @else
                                                        <span class="badge badge-light badge-sm">Anggota</span>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $m['division'] }}</span></td>
                                    <td class="text-center">
                                        <span class="badge badge-success px-3 py-2" style="font-size: 13px;">
                                            <i class="fas fa-star mr-1"></i>{{ $m['points'] }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $m['completed_tasks'] }}/{{ $m['total_tasks'] }}</td>
                                    <td class="text-center text-success">{{ $m['on_time_count'] }}</td>
                                    <td class="text-center text-danger">{{ $m['late_count'] }}</td>
                                    <td class="text-center">
                                        @php
                                            $rateColor = $m['on_time_rate'] >= 80 ? 'success'
                                                : ($m['on_time_rate'] >= 50 ? 'warning' : 'danger');
                                        @endphp
                                        <span class="badge badge-{{ $rateColor }}">{{ $m['on_time_rate'] }}%</span>
                                    </td>
                                    <td class="text-center small text-muted">{{ $m['last_login'] }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('profile.show', $m['id']) }}"
                                           class="btn btn-sm btn-outline-primary" title="Lihat Profil">
                                            <i class="fas fa-user"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">Belum ada data anggota.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-line mr-1"></i> Trend Poin Bulanan (6 Bulan Terakhir)
                </h6>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 400px;">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: WORKLOAD BALANCE --}}
    <div class="tab-pane fade {{ request('tab') === 'workload' ? 'show active' : '' }}" id="workload" role="tabpanel">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-balance-scale mr-1"></i> Workload Balance
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-light border small mb-3">
                    Status dihitung dari <strong>total poin periode ini</strong>
                    ({{ $filterDays }} hari, hanya task yang sudah selesai):
                    <strong>Overload</strong> ≥ {{ $overloadPoints }} poin •
                    <strong>Seimbang</strong> {{ $balancedMin }}–{{ $overloadPoints - 1 }} •
                    <strong>Ringan</strong> &lt; {{ $balancedMin }} • <strong>Idle</strong> tanpa task.<br>
                    <strong>Estimasi Beban</strong> = total bobot task yang dijadwalkan pada periode ini
                    (termasuk yang belum selesai).
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama</th>
                                <th>Divisi</th>
                                <th class="text-center">Poin</th>
                                <th class="text-center">Estimasi Beban</th>
                                <th class="text-center">Task Aktif</th>
                                <th class="text-center">Task Selesai</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Rasio Aktif</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workload as $w)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($w['avatar'])
                                                <img src="{{ asset('storage/avatars/' . $w['avatar']) }}"
                                                     class="rounded-circle mr-2"
                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle bg-secondary text-white mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 32px; height: 32px; font-size: 12px; font-weight: 700;">
                                                    {{ strtoupper(substr($w['name'], 0, 2)) }}
                                                </div>
                                            @endif
                                            <strong>{{ $w['name'] }}</strong>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $w['division'] }}</span></td>
                                    <td class="text-center">
                                        <strong class="{{ $w['points'] >= $overloadPoints ? 'text-danger' : 'text-gray-800' }}">
                                            {{ $w['points'] }}
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light border">{{ $w['planned_load'] }} bobot</span>
                                    </td>
                                    <td class="text-center"><strong>{{ $w['active_tasks'] }}</strong></td>
                                    <td class="text-center text-success">{{ $w['completed_tasks'] }}</td>
                                    <td class="text-center">{{ $w['total_tasks'] }}</td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 15px; min-width: 80px;">
                                            <div class="progress-bar bg-{{ $w['status_color'] }}"
                                                 style="width: {{ $w['active_ratio'] }}%">
                                                {{ $w['active_ratio'] }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $w['status_color'] }}">
                                            <i class="fas {{ $w['status_icon'] }} mr-1"></i>{{ $w['status_label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: DEADLINE ADHERENCE --}}
    <div class="tab-pane fade {{ request('tab') === 'deadline' ? 'show active' : '' }}" id="deadline" role="tabpanel">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock mr-1"></i> Deadline Adherence ({{ $filterDays }} Hari)
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-light border small mb-3">
                    Diurutkan dari yang <strong>paling sering telat</strong>. On-time = task selesai ≤ target tanggal.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama</th>
                                <th>Divisi</th>
                                <th class="text-center">Total Selesai</th>
                                <th class="text-center">On-Time</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">On-Time Rate</th>
                                <th class="text-center">Rata-rata Telat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deadline as $d)
                                @if($d['total_completed'] === 0)
                                    @continue
                                @endif
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($d['avatar'])
                                                <img src="{{ asset('storage/avatars/' . $d['avatar']) }}"
                                                     class="rounded-circle mr-2"
                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle bg-info text-white mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 32px; height: 32px; font-size: 12px; font-weight: 700;">
                                                    {{ strtoupper(substr($d['name'], 0, 2)) }}
                                                </div>
                                            @endif
                                            <strong>{{ $d['name'] }}</strong>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $d['division'] }}</span></td>
                                    <td class="text-center">{{ $d['total_completed'] }}</td>
                                    <td class="text-center text-success">{{ $d['on_time'] }}</td>
                                    <td class="text-center text-danger">{{ $d['late'] }}</td>
                                    <td class="text-center">
                                        @php
                                            $rateColor = $d['on_time_rate'] >= 80 ? 'success'
                                                : ($d['on_time_rate'] >= 50 ? 'warning' : 'danger');
                                        @endphp
                                        <span class="badge badge-{{ $rateColor }} px-3 py-2" style="font-size: 13px;">
                                            {{ $d['on_time_rate'] }}%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($d['avg_late_days'] > 0)
                                            <span class="badge badge-warning">{{ $d['avg_late_days'] }} hari</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data task selesai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 4: ALERT OTOMATIS --}}
    <div class="tab-pane fade {{ request('tab') === 'alert' ? 'show active' : '' }}" id="alert" role="tabpanel">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fas fa-bell mr-1"></i> Alert Otomatis
                </h6>
                <span class="badge badge-danger badge-pill px-3 py-2">{{ count($alerts) }} Alert</span>
            </div>
            <div class="card-body">
                @forelse($alerts as $alert)
                    <div class="d-flex align-items-start p-3 mb-2 rounded border-left border-{{ $alert['color'] }}"
                         style="background-color: #f8f9fa; border-left-width: 4px !important;">
                        <div class="mr-3">
                            <div class="rounded-circle bg-{{ $alert['color'] }} d-flex align-items-center justify-content-center"
                                 style="width: 42px; height: 42px;">
                                <i class="fas {{ $alert['icon'] }} text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold text-dark" style="font-size: 14px;">
                                {{ $alert['title'] }}
                            </div>
                            <div class="text-muted small">{{ $alert['message'] }}</div>
                        </div>
                        <div>
                            <a href="{{ $alert['url'] }}" class="btn btn-{{ $alert['color'] }} btn-sm">
                                {{ $alert['cta'] }} <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5 class="text-muted">Tidak ada alert</h5>
                        <p class="text-muted small mb-0">Semua anggota dan project dalam kondisi baik</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- TAB 5: TEAM DIRECTORY --}}
    <div class="tab-pane fade {{ request('tab') === 'directory' ? 'show active' : '' }}" id="directory" role="tabpanel">

        <div class="card shadow mb-4">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-7 mb-2">
                        <label class="small font-weight-bold text-muted text-uppercase mb-1 d-block">Filter By</label>
                        <div class="d-flex flex-wrap align-items-center">
                            <select id="filterDimension" class="form-control form-control-sm mr-2" style="width: auto;">
                                <option value="">-- Tanpa Filter --</option>
                                <option value="division">📍 Divisi</option>
                                <option value="role">👤 Role</option>
                                <option value="status">⚡ Status</option>
                            </select>
                            <select id="filterValue" class="form-control form-control-sm d-none" style="width: auto;">
                                <option value="">-- Pilih Nilai --</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-5 mb-2 text-md-right">
                        <label class="small font-weight-bold text-muted text-uppercase mb-1 d-block">Sort By</label>
                        <select id="sortBy" class="form-control form-control-sm d-inline-block" style="width: auto;">
                            <option value="points">⭐ Poin (Tertinggi)</option>
                            <option value="name">🔤 Nama (A-Z)</option>
                            <option value="on_time">🎯 On-Time Rate</option>
                            <option value="tasks">✅ Task Selesai</option>
                        </select>
                        <span class="badge badge-light border ml-2" id="visibleCount">0 anggota</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" id="tdGrid"></div>

        <div id="tdEmpty" class="text-center py-5 d-none">
            <i class="fas fa-search fa-4x text-gray-300 mb-3"></i>
            <h5 class="text-muted">Tidak ada anggota yang cocok</h5>
            <p class="text-muted small">Coba ubah filter atau reset</p>
        </div>

        <div class="text-center mt-4 mb-4">
            <button type="button" id="tdLoadMore" class="btn btn-outline-primary td-load-more d-none">
                <i class="fas fa-chevron-down mr-1"></i> Muat Lebih Banyak
            </button>
            <p class="text-muted small mt-2 d-none" id="tdEndMessage">
                <i class="fas fa-check-circle mr-1"></i> Semua anggota sudah ditampilkan
            </p>
        </div>
    </div>
</div>

<div class="td-modal-backdrop" id="tdModal">
    <div class="td-modal-content" id="tdModalContent"></div>
</div>

{{-- ⚡ FASE 3: modal riwayat poin --}}
<div class="td-modal-backdrop" id="tdHistoryModal">
    <div class="td-modal-content" id="tdHistoryContent"></div>
</div>

<script id="tdData" type="application/json">
@json($directory)
</script>

<script src="{{ asset('sb-admin-2/vendor/chart.js/Chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const monthlyData = @json($monthlyTrend);
    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: monthlyData.datasets.map(d => ({
                    label: d.label,
                    data: d.data,
                    borderColor: d.borderColor,
                    backgroundColor: d.backgroundColor,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: d.borderColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    lineTension: 0.3,
                    fill: false,
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'top',
                    labels: { font: { size: 11, weight: '600' }, padding: 12, usePointStyle: true, pointStyle: 'circle' }
                },
                tooltips: {
                    mode: 'index', intersect: false,
                    backgroundColor: 'rgba(255,255,255,0.98)',
                    titleFontColor: '#1a1a2e', bodyFontColor: '#4a5568',
                    borderColor: '#e5e9f2', borderWidth: 1,
                    callbacks: { label: (item, data) => data.datasets[item.datasetIndex].label + ': ' + item.yLabel + ' poin' }
                },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { font: { size: 10, weight: '600' } } }],
                    yAxes: [{
                        ticks: { beginAtZero: true, font: { size: 10, weight: '600' }, padding: 8 },
                        gridLines: { color: 'rgba(234, 236, 244, 0.5)', drawBorder: false, borderDash: [4, 4] }
                    }]
                }
            }
        });
    }

    const rawData = JSON.parse(document.getElementById('tdData').textContent);
    let allMembers = rawData;
    let filteredMembers = [...allMembers];
    let currentPage = 0;
    const PER_PAGE = 12;

    const grid = document.getElementById('tdGrid');
    const emptyState = document.getElementById('tdEmpty');
    const loadMoreBtn = document.getElementById('tdLoadMore');
    const endMessage = document.getElementById('tdEndMessage');
    const visibleCount = document.getElementById('visibleCount');
    const filterDimension = document.getElementById('filterDimension');
    const filterValue = document.getElementById('filterValue');
    const sortBy = document.getElementById('sortBy');

    const filterOptions = {
        division: [
            { value: '', label: 'Semua Divisi' },
            { value: 'Pembina Media', label: 'Pembina Media' },
            { value: 'Ketua Media', label: 'Ketua Media' },
            { value: 'Lapangan', label: 'Lapangan' },
            { value: 'Editing', label: 'Editing' },
            { value: 'Broadcast', label: 'Broadcast' },
            { value: 'AI Research', label: 'AI Research' },
        ],
        role: [
            { value: '', label: 'Semua Role' },
            { value: 'ketua_media', label: 'Ketua Media' },
            { value: 'kepala_divisi', label: 'Kepala Divisi' },
            { value: 'anggota', label: 'Anggota' },
        ],
        status: [
            { value: '', label: 'Semua Status' },
            { value: 'active', label: 'Aktif (login < 7 hari)' },
            { value: 'inactive', label: 'Tidak Aktif (login ≥ 7 hari)' },
            { value: 'top', label: 'Top 3 Poin' },
        ],
    };

    function applyFilterAndSort() {
        const dimension = filterDimension.value;
        const value = filterValue.value;

        if (!dimension || !value) {
            filteredMembers = [...allMembers];
        } else {
            filteredMembers = allMembers.filter(function (m) {
                if (dimension === 'division') return m.division === value;
                if (dimension === 'role') return m.division_role === value;
                if (dimension === 'status') {
                    if (value === 'active') return m.last_login_days !== null && m.last_login_days < 7;
                    if (value === 'inactive') return m.last_login_days === null || m.last_login_days >= 7;
                    if (value === 'top') return m.rank <= 3;
                }
                return true;
            });
        }

        const sortKey = sortBy.value;
        filteredMembers.sort(function (a, b) {
            if (sortKey === 'points') return b.points - a.points;
            if (sortKey === 'name') return a.name.localeCompare(b.name);
            if (sortKey === 'on_time') return b.on_time_rate - a.on_time_rate;
            if (sortKey === 'tasks') return b.completed_tasks - a.completed_tasks;
            return 0;
        });

        visibleCount.textContent = filteredMembers.length + ' anggota';

        currentPage = 0;
        grid.innerHTML = '';
        renderNextBatch();
    }

    function renderNextBatch() {
        const start = currentPage * PER_PAGE;
        const end = start + PER_PAGE;
        const batch = filteredMembers.slice(start, end);

        if (batch.length === 0 && currentPage === 0) {
            emptyState.classList.remove('d-none');
            loadMoreBtn.classList.add('d-none');
            endMessage.classList.add('d-none');
            return;
        }

        emptyState.classList.add('d-none');

        batch.forEach(function (m, i) {
            const card = createCard(m, start + i);
            grid.appendChild(card);
        });

        currentPage++;

        const totalLoaded = currentPage * PER_PAGE;
        if (totalLoaded >= filteredMembers.length) {
            loadMoreBtn.classList.add('d-none');
            if (filteredMembers.length > PER_PAGE) {
                endMessage.classList.remove('d-none');
            } else {
                endMessage.classList.add('d-none');
            }
        } else {
            loadMoreBtn.classList.remove('d-none');
            endMessage.classList.add('d-none');
        }
    }

    function createCard(m, absoluteIndex) {
        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4 mb-4';

        const rankClass = m.rank === 1 ? 'rank-1' : (m.rank === 2 ? 'rank-2' : (m.rank === 3 ? 'rank-3' : ''));
        const rankBadgeClass = m.rank === 1 ? 'td-rank-1' : (m.rank === 2 ? 'td-rank-2' : (m.rank === 3 ? 'td-rank-3' : 'td-rank-n'));
        const delay = (absoluteIndex % PER_PAGE) * 50;
        const bannerStyle = m.background_image
            ? 'background-image: url(' + m.background_image + ');'
            : 'background: ' + m.theme_gradient + ';';

        const avatarHtml = m.avatar
            ? '<img src="' + m.avatar + '" class="td-avatar" alt="' + m.name + '">'
            : '<div class="td-avatar-initials" style="background: ' + m.theme_gradient + ';">' + m.initials + '</div>';

        col.innerHTML = `
            <div class="card td-card shadow-sm ${rankClass}" data-id="${m.id}" style="animation-delay: ${delay}ms;">
                <div class="td-banner" style="${bannerStyle}">
                    <div class="td-banner-overlay"></div>
                </div>
                <div class="td-avatar-wrap">
                    <div style="position: relative;">
                        ${avatarHtml}
                        <span class="td-rank-badge ${rankBadgeClass}">${m.rank <= 99 ? '#' + m.rank : m.rank}</span>
                    </div>
                </div>

                <div class="text-center px-3 pt-2 pb-3">
                    <h6 class="font-weight-bold mb-1">${m.name}</h6>
                    <div class="small text-muted mb-3">${m.jabatan}</div>

                    <div class="row no-gutters text-center mb-2">
                        <div class="col-4">
                            <div class="font-weight-bold text-primary" style="font-size: 18px;">${m.points}</div>
                            <small class="text-muted" style="font-size: 10px;">Poin</small>
                        </div>
                        <div class="col-4 border-left border-right">
                            <div class="font-weight-bold text-success" style="font-size: 18px;">${m.completed_projects}</div>
                            <small class="text-muted" style="font-size: 10px;">Project</small>
                        </div>
                        <div class="col-4">
                            <div class="font-weight-bold text-info" style="font-size: 18px;">${m.completed_tasks}</div>
                            <small class="text-muted" style="font-size: 10px;">Task</small>
                        </div>
                    </div>

                    <div class="mb-1">
                        ${m.is_online
                            ? '<span class="badge badge-success"><i class="fas fa-circle mr-1" style="font-size:7px;"></i>Online</span>'
                            : '<span class="badge badge-secondary"><i class="fas fa-circle mr-1" style="font-size:7px;"></i>Offline</span>'}
                    </div>
                    <small class="text-muted d-block">
                        <i class="fas fa-clock mr-1" style="font-size: 9px;"></i>Terakhir terlihat: ${m.last_seen}
                    </small>
                </div>

                <div class="td-expand-body">
                    <div class="row mb-3">
                        <div class="col-6 mb-2">
                            <div class="td-stat-box">
                                <div class="font-weight-bold text-primary" style="font-size: 18px;">${m.on_time_rate}%</div>
                                <small class="text-muted" style="font-size: 11px;">On-Time Rate</small>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="td-stat-box">
                                <div class="font-weight-bold text-warning" style="font-size: 18px;">${m.late_count}</div>
                                <small class="text-muted" style="font-size: 11px;">Task Telat</small>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="td-stat-box">
                                <div class="font-weight-bold text-info" style="font-size: 18px;">${m.active_projects}</div>
                                <small class="text-muted" style="font-size: 11px;">Project Aktif</small>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="td-stat-box">
                                <div class="font-weight-bold text-secondary" style="font-size: 18px;">${m.avg_late_days}</div>
                                <small class="text-muted" style="font-size: 11px;">Rata Telat (hari)</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="button" class="btn btn-primary btn-sm btn-open-detail" data-id="${m.id}">
                            <i class="fas fa-expand-alt mr-1"></i> Lihat Detail Lengkap
                        </button>
                        <button type="button" class="btn btn-warning btn-sm btn-point-history ml-1" data-id="${m.id}">
                            <i class="fas fa-star mr-1"></i> Riwayat Poin
                        </button>
                        <a href="${m.profile_url}" class="btn btn-outline-secondary btn-sm ml-1">
                            <i class="fas fa-user mr-1"></i> Buka Profil
                        </a>
                    </div>
                </div>
            </div>
        `;

        const cardEl = col.querySelector('.td-card');
        cardEl.addEventListener('click', function (e) {
            if (e.target.closest('.btn-open-detail') || e.target.closest('a')) return;

            const wasExpanded = cardEl.classList.contains('expanded');
            document.querySelectorAll('.td-card.expanded').forEach(function (c) {
                c.classList.remove('expanded');
            });

            if (!wasExpanded) {
                cardEl.classList.add('expanded');
            }
        });

        col.querySelector('.btn-open-detail').addEventListener('click', function (e) {
            e.stopPropagation();
            openModal(m);
        });

        // ⚡ FASE 3: tombol riwayat poin
        col.querySelector('.btn-point-history').addEventListener('click', function (e) {
            e.stopPropagation();
            openPointHistory(m);
        });

        return col;
    }

    const modal = document.getElementById('tdModal');
    const modalContent = document.getElementById('tdModalContent');

    function openModal(m) {
        const bannerStyle = m.background_image
            ? 'background-image: url(' + m.background_image + ');'
            : 'background: ' + m.theme_gradient + ';';

        const avatarHtml = m.avatar
            ? '<img src="' + m.avatar + '" class="td-avatar" style="width: 100px; height: 100px;" alt="' + m.name + '">'
            : '<div class="td-avatar-initials" style="width: 100px; height: 100px; font-size: 36px; background: ' + m.theme_gradient + ';">' + m.initials + '</div>';

        let tasksHtml = '';
        if (m.recent_tasks && m.recent_tasks.length > 0) {
            tasksHtml = m.recent_tasks.map(function (t) {
                const isCompleted = t.status === 'Completed';
                const icon = isCompleted ? 'fa-check-circle text-success' : 'fa-circle text-warning';
                return `
                    <div class="d-flex align-items-start mb-2 pb-2 border-bottom">
                        <i class="fas ${icon} mt-1 mr-2"></i>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold small">${t.title}</div>
                            <small class="text-muted">${t.project_name} • Target: ${t.task_date}</small>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            tasksHtml = '<p class="text-muted small text-center mb-0">Belum ada task</p>';
        }

        let activitiesHtml = '';
        if (m.recent_activities && m.recent_activities.length > 0) {
            activitiesHtml = m.recent_activities.map(function (a) {
                return `
                    <div class="d-flex align-items-start mb-2">
                        <i class="fas ${a.icon} text-${a.color} mt-1 mr-2"></i>
                        <div class="flex-grow-1">
                            <div class="small">${a.description}</div>
                            <small class="text-muted">${a.context} • ${a.date}</small>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            activitiesHtml = '<p class="text-muted small text-center mb-0">Belum ada aktivitas 30 hari terakhir</p>';
        }

        modalContent.innerHTML = `
            <button type="button" class="td-modal-close" id="tdModalClose">
                <i class="fas fa-times"></i>
            </button>

            <div class="td-banner" style="${bannerStyle} height: 120px; border-radius: 16px 16px 0 0;">
                <div class="td-banner-overlay"></div>
            </div>

            <div class="td-avatar-wrap" style="margin-top: -55px;">
                <div style="position: relative;">
                    ${avatarHtml}
                    <span class="td-rank-badge ${m.rank === 1 ? 'td-rank-1' : (m.rank === 2 ? 'td-rank-2' : (m.rank === 3 ? 'td-rank-3' : 'td-rank-n'))}"
                          style="top: -6px; right: calc(50% - 62px); width: 38px; height: 38px; font-size: 14px;">
                        #${m.rank}
                    </span>
                </div>
            </div>

            <div class="text-center px-4 pt-3 pb-4">
                <h4 class="font-weight-bold mb-1">${m.name}</h4>
                <div class="text-muted mb-3">${m.jabatan}</div>

                <div class="row no-gutters mb-4" style="max-width: 600px; margin: 0 auto;">
                    <div class="col-3">
                        <div class="font-weight-bold text-primary" style="font-size: 24px;">${m.points}</div>
                        <small class="text-muted" style="font-size: 11px;">Poin</small>
                    </div>
                    <div class="col-3 border-left border-right">
                        <div class="font-weight-bold text-success" style="font-size: 24px;">${m.completed_projects}</div>
                        <small class="text-muted" style="font-size: 11px;">Project Selesai</small>
                    </div>
                    <div class="col-3 border-right">
                        <div class="font-weight-bold text-info" style="font-size: 24px;">${m.completed_tasks}</div>
                        <small class="text-muted" style="font-size: 11px;">Task Selesai</small>
                    </div>
                    <div class="col-3">
                        <div class="font-weight-bold text-warning" style="font-size: 24px;">${m.on_time_rate}%</div>
                        <small class="text-muted" style="font-size: 11px;">On-Time</small>
                    </div>
                </div>
            </div>

            <div class="px-4 pb-4">
                <div class="mb-4">
                    <h6 class="font-weight-bold text-primary mb-3">
                        <i class="fas fa-chart-line mr-1"></i> Trend Poin 6 Bulan
                    </h6>
                    <div style="position: relative; height: 220px;">
                        <canvas id="tdModalChart"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="font-weight-bold text-info mb-3">
                            <i class="fas fa-history mr-1"></i> Aktivitas 30 Hari
                        </h6>
                        <div style="max-height: 320px; overflow-y: auto;">
                            ${activitiesHtml}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="font-weight-bold text-warning mb-3">
                            <i class="fas fa-tasks mr-1"></i> Task Terbaru
                        </h6>
                        <div style="max-height: 320px; overflow-y: auto;">
                            ${tasksHtml}
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="${m.profile_url}" class="btn btn-primary">
                        <i class="fas fa-user mr-1"></i> Buka Profil Lengkap
                    </a>
                </div>
            </div>
        `;

        modal.classList.add('show');

        setTimeout(function () {
            const ctx = document.getElementById('tdModalChart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: m.trend_labels,
                    datasets: [{
                        label: 'Poin',
                        data: m.trend_data,
                        borderColor: m.theme_solid,
                        backgroundColor: m.theme_solid + '20',
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: m.theme_solid,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        lineTension: 0.3,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: 'rgba(255,255,255,0.98)',
                        titleFontColor: '#1a1a2e', bodyFontColor: '#4a5568',
                        borderColor: '#e5e9f2', borderWidth: 1,
                        callbacks: { label: (item) => item.yLabel + ' poin' }
                    },
                    scales: {
                        xAxes: [{ gridLines: { display: false }, ticks: { font: { size: 10 } } }],
                        yAxes: [{
                            ticks: { beginAtZero: true, font: { size: 10 } },
                            gridLines: { color: 'rgba(234, 236, 244, 0.5)', drawBorder: false }
                        }]
                    }
                }
            });
        }, 100);

        document.getElementById('tdModalClose').addEventListener('click', closeModal);
    }

    function closeModal() {
        modal.classList.remove('show');
        setTimeout(() => { modalContent.innerHTML = ''; }, 300);
    }

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });

    filterDimension.addEventListener('change', function () {
        const dim = this.value;

        if (!dim) {
            filterValue.classList.add('d-none');
            filterValue.value = '';
            applyFilterAndSort();
            return;
        }

        const options = filterOptions[dim] || [];
        filterValue.innerHTML = options.map(function (opt) {
            return '<option value="' + opt.value + '">' + opt.label + '</option>';
        }).join('');

        filterValue.classList.remove('d-none');
        filterValue.value = '';
        applyFilterAndSort();
    });

    filterValue.addEventListener('change', applyFilterAndSort);
    sortBy.addEventListener('change', applyFilterAndSort);

    loadMoreBtn.addEventListener('click', function () {
        renderNextBatch();
    });

    // ==========================================================
    // ⚡ FASE 3: RIWAYAT POIN (modal)
    // ==========================================================

    const historyModal = document.getElementById('tdHistoryModal');
    const historyContent = document.getElementById('tdHistoryContent');
    const historyDays = {{ (int) $filterDays }};
    const historyUrlBase = @json(route('monitoring.pointHistory', ['id' => '__ID__']));

    function escapeHtml(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openPointHistory(m) {
        historyContent.innerHTML = '<div class="p-4 text-center text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat riwayat poin...</div>';
        historyModal.classList.add('show');

        const url = historyUrlBase.replace('__ID__', m.id) + '?days=' + historyDays;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                let rows = '';

                if (!data.items || data.items.length === 0) {
                    rows = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada task selesai pada periode ini.</td></tr>';
                } else {
                    data.items.forEach(function (it) {
                        rows += '<tr>'
                            + '<td><div class="font-weight-bold" style="font-size:13px;">' + escapeHtml(it.task) + '</div>'
                            + '<small class="text-muted">' + escapeHtml(it.project) + '</small></td>'
                            + '<td class="text-center">' + it.weight + '</td>'
                            + '<td class="text-center">' + it.contributors + ' org</td>'
                            + '<td class="text-center"><strong class="text-primary">+' + it.points + '</strong></td>'
                            + '<td class="text-center"><small>' + escapeHtml(it.completed_at) + '</small><br>'
                            + (it.on_time
                                ? '<span class="badge badge-success">Tepat waktu</span>'
                                : '<span class="badge badge-warning">Telat</span>')
                            + '</td>'
                            + '</tr>';
                    });
                }

                historyContent.innerHTML = `
                    <button type="button" class="td-modal-close" id="tdHistoryClose">&times;</button>
                    <div class="p-4">
                        <h5 class="font-weight-bold mb-1"><i class="fas fa-star text-warning mr-1"></i> Riwayat Poin</h5>
                        <p class="text-muted small mb-3">
                            ${escapeHtml(data.user.name)} &bull; ${escapeHtml(data.user.division)} &bull;
                            periode ${data.period.days} hari (${data.period.start} s/d ${data.period.end})
                        </p>

                        <div class="alert alert-light border small mb-3">
                            Total poin periode ini: <strong class="text-primary">${data.total_points}</strong><br>
                            <small class="text-muted">Poin dibagi rata ke seluruh kontributor task.</small>
                        </div>

                        <div class="table-responsive" style="max-height: 45vh; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Task / Project</th>
                                        <th class="text-center">Bobot</th>
                                        <th class="text-center">Kontributor</th>
                                        <th class="text-center">Poin</th>
                                        <th class="text-center">Selesai</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                `;

                document.getElementById('tdHistoryClose').addEventListener('click', closePointHistory);
            })
            .catch(function () {
                historyContent.innerHTML = '<div class="p-4 text-center text-danger">Gagal memuat riwayat poin.</div>';
            });
    }

    function closePointHistory() {
        historyModal.classList.remove('show');
        setTimeout(function () { historyContent.innerHTML = ''; }, 300);
    }

    historyModal.addEventListener('click', function (e) {
        if (e.target === historyModal) {
            closePointHistory();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePointHistory();
        }
    });

    applyFilterAndSort();
});
</script>
@endsection