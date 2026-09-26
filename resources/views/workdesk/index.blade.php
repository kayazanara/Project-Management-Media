@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $isPembina = $user->isPembina();
    @endphp

    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-briefcase mr-2"></i> Workdesk
            </h1>
            <small class="text-muted">
                Selamat datang, <strong>{{ $user->name }}</strong> &mdash;
                {{ now()->translatedFormat('l, d F Y') }}
            </small>
        </div>
        <div>
            <span class="badge badge-success badge-pill px-3 py-2" id="lastSync">
                <i class="fas fa-sync-alt mr-1"></i>
                <span id="syncTime">Tersinkron</span>
            </span>
        </div>
    </div>

    <!-- ROW 1: AKSI SAYA + ALERTS -->
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-gradient-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-bolt mr-1"></i> Aksi Saya
                        <span class="badge badge-light text-primary ml-2">{{ count($actions) }}</span>
                    </h6>
                </div>
                <div class="card-body" id="actionsContainer" style="max-height: 420px; overflow-y: auto; scrollbar-width: thin;">
                    @forelse($actions as $action)
                        <div class="d-flex align-items-start p-3 mb-2 rounded border-left border-{{ $action['color'] }}"
                             style="background-color: #f8f9fa; border-left-width: 4px !important;">
                            <div class="mr-3">
                                <div class="rounded-circle bg-{{ $action['color'] }} d-flex align-items-center justify-content-center"
                                     style="width: 42px; height: 42px;">
                                    <i class="fas {{ $action['icon'] }} text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark" style="font-size: 14px;">
                                    {{ $action['title'] }}
                                </div>
                                <div class="text-muted small">{{ $action['subtitle'] }}</div>
                            </div>
                            <div>
                                <a href="{{ $action['url'] }}" class="btn btn-{{ $action['color'] }} btn-sm">
                                    {{ $action['cta'] }} <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h5 class="text-muted">Tidak ada aksi yang perlu dilakukan</h5>
                            <p class="text-muted small mb-0">Semua project aman terkendali</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-gradient-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-bell mr-1"></i> Perlu Perhatian
                        <span class="badge badge-light text-danger ml-2">{{ count($alerts) }}</span>
                    </h6>
                </div>
                <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                    @forelse($alerts as $alert)
                        <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                            <div class="mr-2 text-{{ $alert['color'] }}">
                                <i class="fas {{ $alert['icon'] }} fa-lg"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="font-weight-bold small text-dark">{{ $alert['title'] }}</div>
                                <div class="text-muted" style="font-size: 11.5px;">{{ $alert['message'] }}</div>
                                <a href="{{ $alert['url'] }}" class="small text-primary">
                                    {{ $alert['cta'] }} &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="fas fa-check fa-3x text-success mb-2"></i>
                            <p class="text-muted small mb-0">Tidak ada alert</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: OVERVIEW CEPAT -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Project Aktif / Total</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['active_projects'] }} / {{ $stats['total_projects'] }}
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-check-circle text-success mr-1"></i>{{ $stats['completed_projects'] }} selesai |
                                <i class="fas fa-exclamation-triangle text-danger mr-1"></i>{{ $stats['overdue_projects'] }} overdue
                            </small>
                        </div>
                        <div class="col-auto"><i class="fas fa-project-diagram fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Task Selesai</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['completed_tasks'] }} / {{ $stats['total_tasks'] }}
                            </div>
                            <small class="text-muted">
                                @if($stats['total_tasks'] > 0)
                                    {{ round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) }}% completion
                                @else
                                    Belum ada task
                                @endif
                            </small>
                        </div>
                        <div class="col-auto"><i class="fas fa-tasks fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Anggota Aktif</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['active_members'] }} / {{ $stats['total_members'] }}
                            </div>
                            <small class="text-muted">Total anggota tim</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Stok Konten Ready</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['ready_contents'] }}</div>
                            <small class="text-muted">Siap upload</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: PERFORMA DIVISI -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line mr-1"></i> Performa Divisi
                    </h6>
                    <div class="d-flex align-items-center">
                        <label class="small text-muted mb-0 mr-2">Range:</label>
                        <select id="rangeFilter" class="form-control form-control-sm" style="width: auto;">
                            <option value="7"  {{ $filterDays == 7  ? 'selected' : '' }}>7 Hari</option>
                            <option value="30" {{ $filterDays == 30 ? 'selected' : '' }}>30 Hari</option>
                            <option value="90" {{ $filterDays == 90 ? 'selected' : '' }}>90 Hari</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">

                    <div class="row">
                        <div class="col-lg-5 mb-4">
                            <h6 class="text-center text-muted small mb-3">
                                <i class="fas fa-medal mr-1"></i> Performance Score (Pekan Ini)
                            </h6>
                            <div style="position: relative; height: 280px;">
                                <canvas id="horizontalBarChart"></canvas>
                            </div>
                        </div>

                        <div class="col-lg-7 mb-4">
                            <h6 class="text-center text-muted small mb-3">
                                <i class="fas fa-chart-line mr-1"></i> Completion Rate Harian ({{ $filterDays }} Hari)
                            </h6>
                            <div style="position: relative; height: 280px;">
                                <canvas id="dailyTrendChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-3">
                        @foreach($performance as $div)
                            <span class="badge mr-2 mb-1" style="background-color: {{ $div['hex'] }}; color: #fff; font-size: 11px; padding: 5px 10px;">
                                <i class="fas fa-circle mr-1"></i>{{ $div['label'] }}
                            </span>
                        @endforeach
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Divisi</th>
                                    <th class="text-center">Task</th>
                                    <th class="text-center">Completion</th>
                                    <th class="text-center">On-Time</th>
                                    <th class="text-center">Performance</th>
                                    <th class="text-center">Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($performance as $div)
                                    <tr>
                                        <td>
                                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background-color: {{ $div['hex'] }}; margin-right: 6px;"></span>
                                            <strong>{{ $div['label'] }}</strong>
                                        </td>
                                        <td class="text-center">{{ $div['completed_tasks'] }}/{{ $div['total_tasks'] }}</td>
                                        <td class="text-center">{{ $div['completion_rate'] }}%</td>
                                        <td class="text-center">{{ $div['on_time_rate'] }}%</td>
                                        <td class="text-center">
                                            <span class="badge" style="background-color: {{ $div['hex'] }}; color: #fff;">
                                                {{ $div['performance_score'] }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($div['trend'] > 0)
                                                <span class="text-success small"><i class="fas fa-arrow-up mr-1"></i>+{{ $div['trend'] }}%</span>
                                            @elseif($div['trend'] < 0)
                                                <span class="text-danger small"><i class="fas fa-arrow-down mr-1"></i>{{ $div['trend'] }}%</span>
                                            @else
                                                <span class="text-muted small"><i class="fas fa-minus mr-1"></i>0%</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: PROJECT HEALTH + TOP/BOTTOM MEMBERS -->
    <div class="row mb-4">
        <div class="col-xl-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-heartbeat mr-1"></i> Project Health
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-success">
                                <i class="fas fa-check-circle mr-1"></i> On Track
                                <span class="badge badge-success ml-1">{{ $projectHealth['counts']['on_track'] }}</span>
                            </h6>
                        </div>
                        @forelse($projectHealth['on_track'] as $p)
                            <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded bg-light border-left border-success"
                                 style="border-left-width: 3px !important;">
                                <div>
                                    <a href="{{ route('projects.show', $p['id']) }}" class="font-weight-bold text-dark">
                                        {{ $p['name'] }}
                                    </a>
                                    <div class="small text-muted">
                                        {{ $p['status'] }} &bull; Deadline: {{ $p['deadline'] }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="progress" style="width: 100px; height: 8px;">
                                        <div class="progress-bar bg-success" style="width: {{ $p['progress'] }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $p['progress'] }}%</small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-2">Tidak ada project on track</p>
                        @endforelse
                    </div>

                    @if($projectHealth['counts']['at_risk'] > 0)
                        <div class="mb-3">
                            <h6 class="mb-2 text-warning">
                                <i class="fas fa-exclamation-circle mr-1"></i> At Risk
                                <span class="badge badge-warning ml-1">{{ $projectHealth['counts']['at_risk'] }}</span>
                            </h6>
                            @foreach($projectHealth['at_risk'] as $p)
                                <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded bg-light border-left border-warning"
                                     style="border-left-width: 3px !important;">
                                    <div>
                                        <a href="{{ route('projects.show', $p['id']) }}" class="font-weight-bold text-dark">
                                            {{ $p['name'] }}
                                        </a>
                                        <div class="small text-muted">
                                            {{ $p['status'] }} &bull; Deadline: {{ $p['deadline'] }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="progress" style="width: 100px; height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: {{ $p['progress'] }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $p['progress'] }}%</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($projectHealth['counts']['overdue'] > 0)
                        <div class="mb-2">
                            <h6 class="mb-2 text-danger">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                <span class="badge badge-danger ml-1">{{ $projectHealth['counts']['overdue'] }}</span>
                            </h6>
                            @foreach($projectHealth['overdue'] as $p)
                                <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded bg-light border-left border-danger"
                                     style="border-left-width: 3px !important;">
                                    <div>
                                        <a href="{{ route('projects.show', $p['id']) }}" class="font-weight-bold text-dark">
                                            {{ $p['name'] }}
                                        </a>
                                        <div class="small text-muted">
                                            {{ $p['status'] }} &bull; Deadline: {{ $p['deadline'] }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="progress" style="width: 100px; height: 8px;">
                                            <div class="progress-bar bg-danger" style="width: {{ $p['progress'] }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $p['progress'] }}%</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- TOP & BOTTOM MEMBERS -->
        <div class="col-xl-4 mb-4">
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-gradient-success text-white">
                    <h6 class="m-0 font-weight-bold small">
                        <i class="fas fa-trophy mr-1"></i> Top 5 Anggota (Poin Pekan Ini)
                    </h6>
                </div>
                <div class="card-body p-2">
                    @forelse($topMembers as $idx => $m)
                        <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded {{ $idx < 3 ? 'bg-light' : '' }}">
                            <div class="d-flex align-items-center" style="min-width: 0;">
                                <span class="badge badge-{{ $idx == 0 ? 'warning' : ($idx == 1 ? 'secondary' : ($idx == 2 ? 'danger' : 'light')) }} mr-2">
                                    #{{ $idx + 1 }}
                                </span>
                                @if($m['avatar'])
                                    <img src="{{ asset('storage/avatars/' . $m['avatar']) }}"
                                         class="rounded-circle mr-2" style="width: 30px; height: 30px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-primary text-white mr-2 d-flex align-items-center justify-content-center"
                                         style="width: 30px; height: 30px; font-size: 11px; font-weight: 700;">
                                        {{ strtoupper(substr($m['name'], 0, 2)) }}
                                    </div>
                                @endif
                                <div style="min-width: 0;">
                                    <div class="font-weight-bold small text-truncate">{{ $m['name'] }}</div>
                                    <div class="text-muted" style="font-size: 10px;">{{ $m['division'] }}</div>
                                </div>
                            </div>
                            <span class="badge badge-success" title="Total {{ $m['points'] }} poin dari {{ $m['tasks_count'] }} task">
                                <i class="fas fa-star mr-1"></i>{{ $m['points'] }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small text-center mb-0">Belum ada data poin</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-2 bg-gradient-warning text-white">
                    <h6 class="m-0 font-weight-bold small">
                        <i class="fas fa-exclamation-circle mr-1"></i> Perlu Perhatian (Poin &lt; 3)
                    </h6>
                </div>
                <div class="card-body p-2">
                    @forelse($bottomMembers as $m)
                        <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded bg-light">
                            <div style="min-width: 0;">
                                <div class="font-weight-bold small text-truncate">{{ $m['name'] }}</div>
                                <div class="text-muted" style="font-size: 10px;">
                                    {{ $m['division'] }} &bull; Login: {{ $m['last_login'] }}
                                </div>
                            </div>
                            <span class="badge badge-{{ $m['points'] == 0 ? 'danger' : 'warning' }}">
                                <i class="fas fa-star mr-1"></i>{{ $m['points'] }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small text-center mb-0">Semua anggota produktif</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: RAPAT & SISTEM -->
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-alt mr-1"></i> Rapat Mendatang
                    </h6>
                </div>
                <div class="card-body">
                    @forelse($upcomingMeetings as $m)
                        <div class="p-2 mb-2 rounded bg-light border-left border-info" style="border-left-width: 3px !important;">
                            <div class="font-weight-bold">{{ $m['title'] }}</div>
                            <div class="small text-muted">
                                <i class="fas fa-calendar mr-1"></i>{{ $m['date'] }} &bull;
                                <i class="fas fa-clock ml-1 mr-1"></i>{{ $m['time'] }} &bull;
                                <i class="fas fa-map-marker-alt ml-1 mr-1"></i>{{ $m['location'] }}
                            </div>
                            <a href="{{ route('meetings.show', $m['id']) }}" class="btn btn-sm btn-outline-info mt-1">
                                Lihat Detail &rarr;
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-3">
                            <i class="fas fa-calendar-times fa-3x text-gray-300 mb-2"></i>
                            <p class="text-muted small mb-0">Belum ada rapat terjadwal</p>
                        </div>
                    @endforelse

                    @if($lastMeeting)
                        <hr>
                        <h6 class="font-weight-bold small"><i class="fas fa-file-alt mr-1"></i>Notulen Terakhir</h6>
                        <div class="p-2 rounded bg-light">
                            <div class="font-weight-bold">{{ $lastMeeting['title'] }}</div>
                            <div class="small text-muted">{{ $lastMeeting['date'] }}</div>
                            <div class="small mt-1">
                                <span class="badge badge-info">{{ $lastMeeting['total_decisions'] }} keputusan</span>
                                <span class="badge badge-success">{{ $lastMeeting['completed_actions'] }}/{{ $lastMeeting['total_actions'] }} action items</span>
                            </div>
                            <a href="{{ route('meetings.show', $lastMeeting['id']) }}" class="btn btn-sm btn-outline-primary mt-2">
                                Lihat Lengkap &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($isPembina && $systemHealth)
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-server mr-1"></i> Kesehatan Sistem
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small font-weight-bold">Storage</span>
                                <span class="small">{{ $systemHealth['storage_percent'] }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-{{ $systemHealth['storage_percent'] > 80 ? 'danger' : ($systemHealth['storage_percent'] > 60 ? 'warning' : 'success') }}"
                                     style="width: {{ $systemHealth['storage_percent'] }}%"></div>
                            </div>
                        </div>

                        <div class="row small">
                            <div class="col-6 mb-2">
                                <strong>Queue Pending:</strong>
                                <span class="badge badge-{{ $systemHealth['queue_pending'] > 10 ? 'warning' : 'success' }}">
                                    {{ $systemHealth['queue_pending'] }}
                                </span>
                            </div>
                            <div class="col-6 mb-2">
                                <strong>Failed Jobs:</strong>
                                <span class="badge badge-{{ $systemHealth['queue_failed'] > 0 ? 'danger' : 'success' }}">
                                    {{ $systemHealth['queue_failed'] }}
                                </span>
                            </div>
                            <div class="col-6 mb-2">
                                <strong>WAHA:</strong>
                                @php
                                    $wahaColor = match($systemHealth['waha_status']) {
                                        'connected'    => 'success',
                                        'need_qr'      => 'warning',
                                        'starting'     => 'info',
                                        'stopped'      => 'secondary',
                                        'unauthorized' => 'danger',
                                        'no_session'   => 'warning',
                                        'offline'      => 'dark',
                                        default        => 'danger',
                                    };
                                    $wahaLabel = match($systemHealth['waha_status']) {
                                        'connected'    => 'Connected',
                                        'need_qr'      => 'Scan QR',
                                        'starting'     => 'Starting',
                                        'stopped'      => 'Stopped',
                                        'unauthorized' => 'API Key Error',
                                        'no_session'   => 'No Session',
                                        'offline'      => 'Offline',
                                        default        => 'Unknown',
                                    };
                                @endphp
                                <span class="badge badge-{{ $wahaColor }}" title="{{ $systemHealth['waha_status'] }}">
                                    {{ $wahaLabel }}
                                </span>
                            </div>
                        </div>

                        <hr>
                        <div class="small text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Untuk monitoring lengkap, hubungi IT Support.
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script src="{{ asset('sb-admin-2/vendor/chart.js/Chart.min.js') }}"></script>
    <script>
        const performanceData = @json($performance);
        const dailyTrendData = @json($dailyTrend);

        const hBarCtx = document.getElementById('horizontalBarChart');
        if (hBarCtx) {
            new Chart(hBarCtx, {
                type: 'horizontalBar',
                data: {
                    labels: performanceData.map(d => d.label),
                    datasets: [{
                        label: 'Performance Score (%)',
                        data: performanceData.map(d => d.performance_score),
                        backgroundColor: performanceData.map(d => d.hex),
                        borderColor: performanceData.map(d => d.hex),
                        borderWidth: 1,
                        borderRadius: 6,
                        barThickness: 28,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        callbacks: { label: function(item) { return item.xLabel + '%'; } }
                    },
                    scales: {
                        xAxes: [{
                            ticks: { beginAtZero: true, max: 100, callback: v => v + '%', font: { size: 10, weight: '600' } },
                            gridLines: { color: 'rgba(234, 236, 244, 0.5)' }
                        }],
                        yAxes: [{ ticks: { font: { size: 11, weight: '600' } }, gridLines: { display: false } }]
                    }
                }
            });
        }

        const lineCtx = document.getElementById('dailyTrendChart');
        if (lineCtx) {
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: dailyTrendData.labels,
                    datasets: dailyTrendData.datasets.map(d => ({
                        label: d.label,
                        data: d.data,
                        borderColor: d.hex,
                        backgroundColor: d.hex + '20',
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: d.hex,
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
                        callbacks: { label: (item, data) => data.datasets[item.datasetIndex].label + ': ' + item.yLabel + '%' }
                    },
                    scales: {
                        xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 10, weight: '600' }, autoSkip: true } }],
                        yAxes: [{
                            ticks: { beginAtZero: true, max: 100, callback: v => v + '%', font: { size: 10, weight: '600' }, padding: 8 },
                            gridLines: { color: 'rgba(234, 236, 244, 0.5)', drawBorder: false, borderDash: [4, 4] }
                        }]
                    }
                }
            });
        }

        document.getElementById('rangeFilter').addEventListener('change', function() {
            window.location.href = "{{ route('workdesk') }}?days=" + this.value;
        });

        setInterval(function() {
            fetch("{{ route('workdesk.actions') }}")
                .then(r => r.json())
                .then(data => {
                    const t = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    document.getElementById('syncTime').textContent = 'Sync: ' + t;
                })
                .catch(() => {});
        }, 30000);
    </script>
@endsection