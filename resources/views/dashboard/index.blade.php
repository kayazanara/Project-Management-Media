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

                    <style>
                        .rank-item {
                            transition: all 0.2s ease;
                            position: relative;
                            border: 1px solid transparent;
                            cursor: pointer;
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
                        .rank-badge-gold { background: linear-gradient(135deg, #f59f0b 0%, #ffc107 100%); }
                        .rank-badge-silver { background: linear-gradient(135deg, #adb5bd 0%, #ced4da 100%); }
                        .rank-badge-bronze { background: linear-gradient(135deg, #d9480f 0%, #f76707 100%); }
                        .rank-badge-normal { background: #6c757d; }

                        #profilePopup {
                            position: fixed;
                            width: 280px;
                            background: #fff;
                            border-radius: 14px;
                            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25), 0 6px 15px rgba(0, 0, 0, 0.1);
                            opacity: 0;
                            visibility: hidden;
                            transition: opacity 0.2s ease, transform 0.2s ease;
                            transform: translateY(-5px);
                            z-index: 999999;
                            pointer-events: none;
                            overflow: hidden;
                        }
                        #profilePopup.show {
                            opacity: 1;
                            visibility: visible;
                            transform: translateY(0);
                            pointer-events: auto;
                        }
                    </style>

                    <div class="list-group list-group-flush">
                        @forelse($topMembers as $index => $member)
                            @php
                                $rank = $index + 1;
                                if ($index === 0) {
                                    $rankClass = 'rank-gold'; $badgeClass = 'rank-badge-gold'; $rankIcon = 'fa-crown';
                                } elseif ($index === 1) {
                                    $rankClass = 'rank-silver'; $badgeClass = 'rank-badge-silver'; $rankIcon = 'fa-medal';
                                } elseif ($index === 2) {
                                    $rankClass = 'rank-bronze'; $badgeClass = 'rank-badge-bronze'; $rankIcon = 'fa-medal';
                                } else {
                                    $rankClass = 'rank-default'; $badgeClass = 'rank-badge-normal'; $rankIcon = null;
                                }

                                $memberAvatarUrl = $member->avatar ? asset('storage/avatars/' . $member->avatar) : null;
                                $memberBgUrl = $member->background_image ? asset('storage/backgrounds/' . $member->background_image) : null;
                                $completedProjectsCount = $member->projects()->where('status', 'Completed')->count();
                            @endphp

                            <div class="list-group-item d-flex justify-content-between align-items-center px-3 mb-2 rounded rank-item {{ $rankClass }}"
                                 data-member-id="{{ $member->id }}"
                                 data-profile-url="{{ route('profile.show', $member->id) }}"
                                 data-name="{{ $member->name }}"
                                 data-jabatan="{{ $member->jabatan ?? '' }}"
                                 data-division="{{ $member->division ?? 'Tanpa Divisi' }}"
                                 data-avatar="{{ $memberAvatarUrl }}"
                                 data-bg="{{ $memberBgUrl }}"
                                 data-gradient="{{ $member->theme_gradient }}"
                                 data-points="{{ $member->points }}"
                                 data-task-count="{{ $member->task_points_count }}"
                                 data-project-count="{{ $completedProjectsCount }}"
                                 data-login-days="{{ $member->total_login_days ?? 0 }}"
                                 data-initials="{{ strtoupper(substr($member->name, 0, 2)) }}"
                                 onclick="window.location=this.dataset.profileUrl">

                                <div class="d-flex align-items-center" style="min-width: 0; flex: 1;">
                                    <div class="position-relative mr-3">
                                        @if($memberAvatarUrl)
                                            <img src="{{ $memberAvatarUrl }}" alt="{{ $member->name }}"
                                                 class="rounded-circle shadow-sm"
                                                 style="width: 46px; height: 46px; object-fit: cover; border: 2px solid #fff;">
                                        @else
                                            <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                                 style="width: 46px; height: 46px; background: linear-gradient(135deg, #4e73df, #224abe); color: #fff; font-weight: 700; font-size: 16px; border: 2px solid #fff;">
                                                {{ strtoupper(substr($member->name, 0, 2)) }}
                                            </div>
                                        @endif

                                        <span class="rank-badge {{ $badgeClass }}">
                                            @if($rankIcon)
                                                <i class="fas {{ $rankIcon }}"></i>
                                            @else
                                                {{ $rank }}
                                            @endif
                                        </span>
                                    </div>

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

                                @if($member->points > 0)
                                    <span class="badge badge-success badge-pill px-3 py-2 ml-2"
                                          style="font-size: 12px; white-space: nowrap;"
                                          title="Total {{ $member->points }} poin dari {{ $member->task_points_count }} task">
                                        <i class="fas fa-star mr-1"></i>{{ $member->points }}
                                    </span>
                                @else
                                    <span class="badge badge-light border text-muted badge-pill px-3 py-2 ml-2"
                                          style="font-size: 12px; white-space: nowrap;">
                                        0
                                    </span>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Belum ada anggota dengan poin di periode ini.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. AKTIVITAS DIVISI & PROJECT BERJALAN -->
    <div class="row">
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
                                @if($proj->is_overdue)
                                    <span class="badge badge-danger">Overdue</span>
                                @else
                                    <span class="badge badge-{{ $proj->status_badge }}">{{ $proj->status }}</span>
                                @endif
                            </div>
                            <small class="text-muted">Deadline: {{ $proj->deadline ? $proj->deadline->format('d M Y') : '-' }}</small>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 mt-5">Tidak ada project aktif saat ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Task Selesai (Group by Day) -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-check-double mr-1"></i> Task Selesai (Periode Ini)
                    </h6>
                    <span class="badge badge-success badge-pill px-3 py-1">
                        {{ $totalCompletedTasks }} Task
                    </span>
                </div>
                <div class="card-body" style="height: 600px; overflow-y: auto; background-color: #f8f9fa;">
                    @forelse($completedTasksByDay as $day)
                        <div class="mb-3 bg-white rounded shadow-sm" style="border-left: 4px solid #1cc88a;">
                            <div class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom"
                                 style="background-color: #f0f8f5;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-day text-success mr-2"></i>
                                    <strong class="text-dark" style="font-size: 14px;">
                                        {{ $day['date_formatted'] }}
                                    </strong>
                                </div>
                                <span class="badge badge-success badge-pill px-2 py-1">
                                    {{ $day['count'] }} Task
                                </span>
                            </div>
                            <div class="p-2">
                                @foreach($day['tasks'] as $task)
                                    <div class="d-flex justify-content-between align-items-start px-2 py-2 mb-1 rounded"
                                         style="transition: all 0.15s;"
                                         onmouseover="this.style.backgroundColor='#f0f3ff';"
                                         onmouseout="this.style.backgroundColor='transparent';">
                                        <div class="d-flex align-items-start flex-grow-1" style="min-width: 0;">
                                            <i class="fas fa-check-circle text-success mt-1 mr-2" style="flex-shrink: 0;"></i>
                                            <div style="min-width: 0; flex: 1;">
                                                <div class="font-weight-bold text-dark text-truncate" style="font-size: 13.5px;">
                                                    {{ $task->title }}
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-folder-open mr-1" style="font-size: 10px;"></i>
                                                    <a href="{{ route('projects.show', $task->project_id) }}" class="text-muted">
                                                        {{ $task->project->name ?? '-' }}
                                                    </a>
                                                </small>
                                                @if($task->contributors->count() > 0)
                                                    <div class="mt-1">
                                                        @foreach($task->contributors as $contributor)
                                                            <span class="badge badge-light border mr-1" style="font-size: 10px;">
                                                                <i class="fas fa-user text-success mr-1"></i>{{ $contributor->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="badge badge-light border text-muted ml-2" style="font-size: 11px; white-space: nowrap; flex-shrink: 0;">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ \Carbon\Carbon::parse($task->completed_at)->format('H:i') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-gray-300 mb-3"></i>
                            <p class="text-muted mb-0">Belum ada task yang diselesaikan pada periode ini.</p>
                            <small class="text-muted">Coba ubah filter waktu di atas.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- PROFILE POPUP --}}
    <div id="profilePopup">
        <div id="popupBg" style="height: 60px; position: relative; overflow: hidden; border-radius: 14px 14px 0 0;"></div>
        <div style="margin-top: -32px; text-align: center; position: relative; z-index: 2;">
            <div id="popupAvatarWrap"></div>
        </div>
        <div style="text-align: center; padding: 10px 14px 14px;">
            <div id="popupName" style="font-weight: 700; font-size: 14.5px; color: #1a1a2e; line-height: 1.3;"></div>
            <div id="popupJabatan" style="font-size: 11px; color: #f59f0b; font-weight: 600; margin-top: 4px; display: none;"></div>
            <div id="popupDivision" style="font-size: 11.5px; color: #8492a6; margin-top: 4px;"></div>

            <div style="border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-around;">
                <div style="flex: 1;">
                    <div id="popupPoints" style="font-weight: 700; font-size: 14px; color: #f59f0b;">0</div>
                    <div style="font-size: 10px; color: #8492a6; margin-top: 2px;">Poin</div>
                </div>
                <div style="flex: 1; border-left: 1px solid #eee; border-right: 1px solid #eee;">
                    <div id="popupTasks" style="font-weight: 700; font-size: 14px; color: #4e73df;">0</div>
                    <div style="font-size: 10px; color: #8492a6; margin-top: 2px;">Task</div>
                </div>
                <div style="flex: 1;">
                    <div id="popupLogins" style="font-weight: 700; font-size: 14px; color: #1cc88a;">0</div>
                    <div style="font-size: 10px; color: #8492a6; margin-top: 2px;">Login</div>
                </div>
            </div>

            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #eee;">
                <span style="font-size: 10.5px; color: #4e73df; font-weight: 600;">
                    <i class="fas fa-external-link-alt mr-1"></i>Klik untuk buka profil
                </span>
            </div>
        </div>
    </div>

    <script src="{{ asset('sb-admin-2/vendor/chart.js/Chart.min.js') }}"></script>
    <script>
        (function() {
            const popup = document.getElementById('profilePopup');
            const popupBg = document.getElementById('popupBg');
            const popupAvatarWrap = document.getElementById('popupAvatarWrap');
            const popupName = document.getElementById('popupName');
            const popupJabatan = document.getElementById('popupJabatan');
            const popupDivision = document.getElementById('popupDivision');
            const popupPoints = document.getElementById('popupPoints');
            const popupTasks = document.getElementById('popupTasks');
            const popupLogins = document.getElementById('popupLogins');

            let hoverTimeout = null;

            document.querySelectorAll('.rank-item').forEach(function(item) {
                item.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    const rect = this.getBoundingClientRect();
                    const data = this.dataset;

                    if (data.bg) {
                        popupBg.style.background = '#eee';
                        popupBg.innerHTML = '<img src="' + data.bg + '" style="width: 100%; height: 100%; object-fit: cover;">';
                    } else {
                        popupBg.style.background = data.gradient;
                        popupBg.innerHTML = '';
                    }

                    if (data.avatar) {
                        popupAvatarWrap.innerHTML = '<img src="' + data.avatar + '" alt="" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">';
                    } else {
                        popupAvatarWrap.innerHTML = '<div style="width: 64px; height: 64px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; color: #fff; border: 3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.15); background: ' + data.gradient + ';">' + data.initials + '</div>';
                    }

                    popupName.textContent = data.name;
                    popupDivision.textContent = data.division;

                    if (data.jabatan) {
                        popupJabatan.style.display = 'block';
                        popupJabatan.innerHTML = '<i class="fas fa-briefcase mr-1"></i>' + data.jabatan;
                    } else {
                        popupJabatan.style.display = 'none';
                    }

                    popupPoints.textContent = data.points;
                    popupTasks.textContent = data.taskCount;
                    popupLogins.textContent = data.loginDays;

                    const popupWidth = 280;
                    const popupHeight = 280;
                    const gap = 15;

                    let left = rect.left - popupWidth - gap;
                    let top = rect.top + (rect.height / 2) - (popupHeight / 2);

                    if (left < 10) { left = rect.right + gap; }
                    if (top < 10) top = 10;
                    if (top + popupHeight > window.innerHeight - 10) {
                        top = window.innerHeight - popupHeight - 10;
                    }

                    popup.style.left = left + 'px';
                    popup.style.top = top + 'px';

                    hoverTimeout = setTimeout(function() {
                        popup.classList.add('show');
                    }, 150);
                });

                item.addEventListener('mouseleave', function() {
                    clearTimeout(hoverTimeout);
                    popup.classList.remove('show');
                });
            });

            window.addEventListener('scroll', function() {
                popup.classList.remove('show');
            }, true);
        })();

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