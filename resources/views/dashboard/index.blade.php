@extends('layouts.app')

@section('content')
    <!-- Page Heading & Filter Periode -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tachometer-alt mr-2"></i> {{ $title }}</h1>
        
        <!-- Form Filter Periode -->
        <form action="{{ route('dashboard') }}" method="GET" class="form-inline">
            <div class="input-group input-group-sm mr-2">
                <select name="filter" class="form-control" onchange="this.form.submit()">
                    <option value="week" {{ $filter == 'week' ? 'selected' : '' }}>Pekan Ini</option>
                    <option value="month" {{ $filter == 'month' ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="year" {{ $filter == 'year' ? 'selected' : '' }}>Tahun Ini</option>
                </select>
            </div>
            <div class="input-group input-group-sm mr-2">
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control" placeholder="Dari Tanggal" onchange="this.form.submit()">
            </div>
            <div class="input-group input-group-sm mr-2">
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control" placeholder="Sampai Tanggal" onchange="this.form.submit()">
            </div>
            @if($startDate || $endDate || $filter != 'week')
                <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm" title="Reset Filter"><i class="fas fa-sync-alt"></i></a>
            @endif
        </form>
    </div>

    <!-- 1. KPI CARDS -->
    <div class="row">
        <!-- Project Aktif -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Project Aktif / Total</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $activeProjectsCount }} / {{ $totalProjects }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-project-diagram fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Hari Ini -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Task Selesai Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $tasksCompletedTodayCount }} / {{ $tasksTodayCount }} Task</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tasks fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Anggota -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Anggota Tim</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalMembers }} Orang</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Ready Stock -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Stok Konten Ready</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $readyStockCount }} Konten</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. GRAFIK PRODUKTIVITAS & PERINGKAT ANGGOTA -->
    <div class="row">
        <!-- Grafik Trend Task Selesai -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-line mr-1"></i> Grafik Produktivitas Task Selesai</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 360px;">
                        <canvas id="productivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Peringkat Anggota (Leaderboard) -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-trophy mr-1"></i> Peringkat Anggota (Periode Ini)
                    </h6>
                    <span class="badge badge-success badge-pill px-2 py-1">
                        {{ $topMembers->count() }} Anggota
                    </span>
                </div>
                <div class="card-body px-3" style="height: 360px; overflow-y: auto;">

                    {{-- ============ CSS UNTUK RANK ITEM ============ --}}
                    <style>
                        .rank-item {
                            transition: all 0.2s ease;
                            position: relative;
                            border: 1px solid transparent;
                        }
                        .rank-item:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
                        }
                        .rank-gold {
                            background: linear-gradient(135deg, #fff9db 0%, #ffe8a3 100%);
                            border-left: 5px solid #f59f0b;
                            box-shadow: 0 2px 8px rgba(245, 159, 11, 0.18) !important;
                        }
                        .rank-silver {
                            background: linear-gradient(135deg, #f8f9fa 0%, #e0e4e8 100%);
                            border-left: 5px solid #adb5bd;
                            box-shadow: 0 2px 8px rgba(173, 181, 189, 0.18) !important;
                        }
                        .rank-bronze {
                            background: linear-gradient(135deg, #fff5f0 0%, #ffd9c2 100%);
                            border-left: 5px solid #d9480f;
                            box-shadow: 0 2px 8px rgba(217, 72, 15, 0.18) !important;
                        }
                        .rank-default {
                            background-color: #f8f9fa;
                            border-left: 5px solid #e3e6f0;
                        }
                        .rank-badge {
                            position: absolute;
                            bottom: -2px;
                            right: -2px;
                            width: 22px;
                            height: 22px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 10px;
                            font-weight: 700;
                            color: #fff;
                            border: 2px solid #fff;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
                            z-index: 2;
                        }
                        .rank-badge-gold {
                            background: linear-gradient(135deg, #f59f0b 0%, #ffc107 100%);
                        }
                        .rank-badge-silver {
                            background: linear-gradient(135deg, #adb5bd 0%, #ced4da 100%);
                        }
                        .rank-badge-bronze {
                            background: linear-gradient(135deg, #d9480f 0%, #f76707 100%);
                        }
                        .rank-badge-normal {
                            background: #6c757d;
                        }
                    </style>
                    {{-- ============ END CSS ============ --}}

                    <div class="list-group list-group-flush">
                        @forelse($topMembers as $index => $member)
                            @php
                                $rank = $index + 1;

                                if ($index === 0) {
                                    $rankClass = 'rank-gold';
                                    $badgeClass = 'rank-badge-gold';
                                    $rankIcon = 'fa-crown';
                                } elseif ($index === 1) {
                                    $rankClass = 'rank-silver';
                                    $badgeClass = 'rank-badge-silver';
                                    $rankIcon = 'fa-medal';
                                } elseif ($index === 2) {
                                    $rankClass = 'rank-bronze';
                                    $badgeClass = 'rank-badge-bronze';
                                    $rankIcon = 'fa-medal';
                                } else {
                                    $rankClass = 'rank-default';
                                    $badgeClass = 'rank-badge-normal';
                                    $rankIcon = null;
                                }
                            @endphp

                            <div class="list-group-item d-flex justify-content-between align-items-center px-3 mb-2 rounded rank-item {{ $rankClass }}">
                                <div class="d-flex align-items-center" style="min-width: 0; flex: 1;">

                                    {{-- Avatar + Badge Rank --}}
                                    <div class="position-relative mr-3">
                                        @if($member->avatar)
                                            <img src="{{ asset('storage/avatars/' . $member->avatar) }}"
                                                 alt="{{ $member->name }}"
                                                 class="rounded-circle shadow-sm"
                                                 style="width: 46px; height: 46px; object-fit: cover; border: 2px solid #fff;">
                                        @else
                                            <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                                 style="width: 46px; height: 46px; background: linear-gradient(135deg, #4e73df, #224abe); color: #fff; font-weight: 700; font-size: 16px; border: 2px solid #fff;">
                                                {{ strtoupper(substr($member->name, 0, 2)) }}
                                            </div>
                                        @endif

                                        {{-- Badge rank di pojok kanan bawah avatar --}}
                                        <span class="rank-badge {{ $badgeClass }}">
                                            @if($rankIcon)
                                                <i class="fas {{ $rankIcon }}"></i>
                                            @else
                                                {{ $rank }}
                                            @endif
                                        </span>
                                    </div>

                                    {{-- Nama & Divisi --}}
                                    <div style="min-width: 0; flex: 1;">
                                        <div class="font-weight-bold text-dark text-truncate" style="font-size: 14px;">
                                            {{ $member->name }}
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-users mr-1" style="font-size: 10px;"></i>
                                            {{ $member->division ?? 'Tanpa Divisi' }}
                                        </small>
                                    </div>
                                </div>

                                {{-- Badge Task Selesai --}}
                                @if($member->tasks_count > 0)
                                    <span class="badge badge-success badge-pill px-3 py-2 ml-2"
                                          style="font-size: 12px; white-space: nowrap;"
                                          title="{{ $member->tasks_count }} task diselesaikan">
                                        <i class="fas fa-check-circle mr-1"></i>{{ $member->tasks_count }}
                                    </span>
                                @else
                                    <span class="badge badge-light border text-muted badge-pill px-3 py-2 ml-2"
                                          style="font-size: 12px; white-space: nowrap;"
                                          title="Belum ada task selesai">
                                        0
                                    </span>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Belum ada anggota terdaftar.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. AKTIVITAS DIVISI & PROJECT BERJALAN -->
    <div class="row">
        <!-- Aktivitas Divisi -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-desktop mr-1"></i> Rangkuman Aktivitas Divisi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr class="small text-uppercase">
                                    <th>Divisi</th>
                                    <th>Anggota</th>
                                    <th>Project Aktif</th>
                                    <th>Task Hari Ini</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($divisionActivities as $div)
                                <tr>
                                    <td><strong><a href="{{ route('division.show', $div['name']) }}">{{ $div['name'] }}</a></strong></td>
                                    <td>{{ $div['members_count'] }} Orang</td>
                                    <td>{{ $div['active_projects'] }} Project</td>
                                    <td>{{ $div['tasks_today'] }} Task</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Berjalan dengan Tinggi Sama & Scroll Dropdown -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-project-diagram mr-1"></i> Project Berjalan (Aktif)</h6>
                </div>
                <div class="card-body px-3" style="height: 275px; overflow-y: auto;">
                    @forelse($activeProjects as $proj)
                        <div class="mb-3 border-bottom pb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-gray-800">
                                    <a href="{{ route('projects.show', $proj->id) }}">{{ $proj->name }}</a>
                                </span>
                                <span class="badge badge-warning">{{ $proj->status }}</span>
                            </div>
                            <small class="text-muted">Deadline: {{ $proj->deadline }}</small>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 mt-5">Tidak ada project aktif saat ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Script Chart.js -->
    <script src="{{ asset('sb-admin-2/vendor/chart.js/Chart.min.js') }}"></script>
    <script>
        var ctx = document.getElementById("productivityChart");
        var productivityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    label: "Task Selesai",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: {!! json_encode($chartData) !!},
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                scales: {
                    xAxes: [{ time: { unit: 'date' }, gridLines: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 10 } }],
                    yAxes: [{ ticks: { maxTicksLimit: 5, padding: 10, beginAtZero: true, callback: function(value) { if (value % 1 === 0) { return value; } } }, gridLines: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] } }],
                },
                legend: { display: false },
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10,
                }
            }
        });
    </script>
@endsection