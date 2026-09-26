@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users-cog mr-2"></i> {{ $title }}</h1>
        <button class="btn btn-success btn-sm shadow-sm" data-toggle="modal" data-target="#addReportModal">
            <i class="fas fa-file-alt mr-1"></i> Buat Laporan Harian
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Statistik Singkat Divisi -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Anggota Divisi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $members->count() }} Orang</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Project Terkait (Aktif)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $projects->count() }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-project-diagram fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Task Anggota (Aktif)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $tasks->count() }} Task</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tasks fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Anggota Divisi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Anggota {{ $divisionName }}</h6>
        </div>
        <div class="card-body">
            @if($members->isEmpty())
                <p class="text-muted mb-0">Belum ada anggota yang terdaftar di divisi ini.</p>
            @else
                <div class="d-flex flex-wrap">
                    @foreach($members as $member)
                        <span class="badge badge-secondary p-2 mr-2 mb-2 font-weight-normal" style="font-size: 14px;">
                            <i class="fas fa-user mr-1"></i> {{ $member->name }} <span class="text-warning">({{ $member->role }})</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Daftar Project Aktif Terkait Divisi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Project Aktif / Terkait Divisi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Project</th>
                            <th>Status Workflow</th>
                            <th>Deadline</th>
                            <th width="25%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $index => $proj)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $proj->name }}</strong>
                                @if($proj->is_overdue)
                                    <br><span class="badge badge-danger mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>OVERDUE
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $proj->status_badge }}">
                                    {{ $proj->status }}
                                </span>
                            </td>
                            <td>{{ $proj->deadline?->format('d M Y') ?? '-' }}</td>
                            <td class="text-center">
                                <a href="{{ route('projects.show', $proj->id) }}" class="btn btn-info btn-sm" title="Lihat Detail & Task">
                                    <i class="fas fa-eye"></i> Buka
                                </a>

                                {{-- Tombol Ajukan Review: hanya Kepala Divisi & status On Progress --}}
                                @if($proj->status == 'On Progress' && auth()->user()->isKepalaDivisi())
                                    <form action="{{ route('projects.requestReview', $proj->id) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Ajukan project ini ke tahap Review?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-warning btn-sm" title="Ajukan ke Review">
                                            <i class="fas fa-paper-plane"></i> Ajukan Review
                                        </button>
                                    </form>
                                @endif

                                {{-- Tombol Ajukan Review Ulang: hanya Kepala Divisi & status Revision --}}
                                @if($proj->status == 'Revision' && auth()->user()->isKepalaDivisi())
                                    <form action="{{ route('projects.requestReview', $proj->id) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Ajukan review ulang?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-info btn-sm" title="Ajukan Review Ulang">
                                            <i class="fas fa-redo"></i> Review Ulang
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada project aktif yang melibatkan anggota di divisi ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Monitoring Task & Progress -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Monitoring Task & Progress Anggota Divisi (Aktif)</h6>
        </div>
        <div class="card-body">

            <div class="row mb-3">
                <div class="col-md-7 mb-2">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="taskSearchInput" class="form-control"
                               placeholder="Cari nama task, deskripsi, atau PIC...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Hapus pencarian">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 mb-2">
                    <select id="projectFilter" class="form-control">
                        <option value="">-- Semua Project --</option>
                        @foreach($projects as $proj)
                            @if($proj->tasks->count() > 0)
                                <option value="{{ $proj->id }}">{{ $proj->name }} ({{ $proj->tasks->count() }} Task)</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="tasksContainer" style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
                @php $hasAnyTask = false; @endphp

                @foreach($projects as $project)
                    @if($project->tasks->count() > 0)
                        @php $hasAnyTask = true; @endphp
                        <div class="project-group mb-4" data-project-id="{{ $project->id }}">
                            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-folder-open mr-1"></i>
                                    <a href="{{ route('projects.show', $project->id) }}" class="text-primary">
                                        {{ $project->name }}
                                    </a>
                                </h6>
                                <div>
                                    <span class="badge badge-secondary">{{ $project->tasks->count() }} Task</span>
                                    <span class="badge badge-{{ $project->status_badge }}">{{ $project->status }}</span>
                                    @if($project->is_overdue)
                                        <span class="badge badge-danger">OVERDUE</span>
                                    @endif
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="5%">Status</th>
                                            <th>Nama Task / Checklist</th>
                                            <th>PIC</th>
                                            <th>Kontributor (Poin)</th>
                                            <th>Target Tanggal</th>
                                            <th>Tanggal Selesai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($project->tasks as $task)
                                            <tr class="task-row"
                                                data-task-title="{{ strtolower($task->title) }}"
                                                data-task-desc="{{ strtolower($task->description ?? '') }}"
                                                data-pic="{{ strtolower($task->assignedUser->name ?? '') }}">
                                                <td class="text-center">
                                                    @if($task->status == 'Completed')
                                                        <form action="{{ route('tasks.toggle', $task->id) }}" method="POST"
                                                              onsubmit="return confirm('Batalkan penyelesaian task ini? Kontributor tetap tersimpan.');">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="btn btn-sm btn-success" title="Klik untuk batalkan (reopen)">
                                                                <i class="fas fa-check-square"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                                data-toggle="modal"
                                                                data-target="#completeTaskModal{{ $task->id }}"
                                                                title="Klik untuk tandai selesai & pilih kontributor">
                                                            <i class="fas fa-square"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="{{ $task->status == 'Completed' ? 'text-muted text-decoration-line-through' : 'font-weight-bold' }}">
                                                        {{ $task->title }}
                                                    </span>
                                                    @if($task->description)
                                                        <br><small class="text-muted">{{ $task->description }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($task->assignedUser)
                                                        <span class="badge badge-secondary">
                                                            <i class="fas fa-user mr-1"></i>{{ $task->assignedUser->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted small">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($task->contributors->count() > 0)
                                                        @foreach($task->contributors as $contributor)
                                                            <span class="badge badge-success mr-1 mb-1">
                                                                <i class="fas fa-star mr-1"></i>{{ $contributor->name }}
                                                            </span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted small font-italic">Belum ada kontributor</span>
                                                    @endif
                                                </td>
                                                <td>{{ $task->task_date ?? '-' }}</td>
                                                <td>
                                                    @if($task->completed_at)
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-calendar-check mr-1"></i>
                                                            {{ \Carbon\Carbon::parse($task->completed_at)->format('d M Y, H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted small">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if(!$hasAnyTask)
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-tasks fa-3x text-gray-300 mb-3"></i>
                        <p class="mb-0">Belum ada task aktif yang ditugaskan ke anggota di divisi ini.</p>
                    </div>
                @endif

                <div id="noResults" class="text-center text-muted py-5" style="display: none;">
                    <i class="fas fa-search fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">Tidak ada task yang cocok dengan filter/pencarian.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Laporan Harian Divisi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-success">Riwayat Laporan Harian {{ $divisionName }}</h6>
            <form action="{{ route('division.show', $divisionName) }}" method="GET" class="form-inline">
                <select name="filter" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">-- Semua Waktu --</option>
                    <option value="week" {{ (isset($currentFilter) && $currentFilter == 'week') ? 'selected' : '' }}>Pekan Ini</option>
                    <option value="month" {{ (isset($currentFilter) && $currentFilter == 'month') ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="year" {{ (isset($currentFilter) && $currentFilter == 'year') ? 'selected' : '' }}>Tahun Ini</option>
                </select>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pembuat</th>
                            <th>Pekerjaan Selesai</th>
                            <th>Kendala & Rencana</th>
                            <th>Catatan</th>
                            <th width="12%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyReports ?? [] as $report)
                        <tr>
                            <td>{{ $report->report_date }}</td>
                            <td>{{ $report->user->name ?? '-' }}</td>
                            <td><span class="text-success font-weight-bold">{{ $report->completed_work }}</span></td>
                            <td>
                                <small><strong>Kendala:</strong> {{ $report->problems ?? '-' }}</small><br>
                                <small><strong>Rencana:</strong> {{ $report->next_plan ?? '-' }}</small>
                            </td>
                            <td>{{ $report->notes ?? '-' }}</td>
                            <td class="text-center">
                                <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#detailReportModal{{ $report->id }}" title="Detail Laporan">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <form action="{{ route('division.reports.destroy', $report->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus laporan harian ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus Laporan"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada riwayat laporan harian untuk filter ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Riwayat Project Selesai -->
    <div class="card shadow mb-4 border-left-success">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-check-circle mr-1"></i> Riwayat Project Selesai (Completed)
            </h6>

            <form action="{{ route('division.show', $divisionName) }}" method="GET" class="form-inline">
                <input type="hidden" name="filter" value="{{ $currentFilter }}">

                <select name="completed_month" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">-- Semua Bulan --</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $filterMonth == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>

                <select name="completed_year" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">-- Semua Tahun --</option>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ $filterYear == $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>

                @if($filterMonth || $filterYear)
                    <a href="{{ route('division.show', $divisionName) }}" class="btn btn-secondary btn-sm" title="Reset Filter">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                @endif
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Project</th>
                            <th>Mulai</th>
                            <th>Deadline</th>
                            <th>Tanggal Selesai</th>
                            <th>Durasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedProjects as $index => $compProj)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $compProj->name }}</strong></td>
                            <td>{{ $compProj->start_date?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $compProj->deadline?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @if($compProj->completed_at)
                                    <span class="badge badge-success">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        {{ \Carbon\Carbon::parse($compProj->completed_at)->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                @if($compProj->completed_at && $compProj->start_date)
                                    @php
                                        $start = \Carbon\Carbon::parse($compProj->start_date)->startOfDay();
                                        $end = \Carbon\Carbon::parse($compProj->completed_at)->startOfDay();
                                        $days = (int) $start->diffInDays($end);
                                    @endphp
                                    <span class="badge badge-info">
                                        <i class="fas fa-clock mr-1"></i>{{ $days }} hari
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('projects.show', $compProj->id) }}" class="btn btn-info btn-sm" title="Lihat Detail Project">
                                    <i class="fas fa-eye"></i> Buka
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Belum ada project yang selesai untuk filter ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- MODAL COMPLETE TASK --}}
    @foreach($tasks as $task)
        @if($task->status != 'Completed')
            <div class="modal fade" id="completeTaskModal{{ $task->id }}" tabindex="-1" role="dialog" aria-labelledby="completeTaskModalLabel{{ $task->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form action="{{ route('tasks.toggle', $task->id) }}" method="POST" class="modal-content">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title font-weight-bold" id="completeTaskModalLabel{{ $task->id }}">
                                <i class="fas fa-check-circle mr-1"></i> Tandai Task Selesai
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3 p-2 rounded" style="background-color: #f0f8f5; border-left: 4px solid #1cc88a;">
                                <p class="mb-0"><strong>Task:</strong> {{ $task->title }}</p>
                                @if($task->assignedUser)
                                    <p class="mb-0 small text-muted">
                                        PIC: <strong>{{ $task->assignedUser->name }}</strong>
                                    </p>
                                @endif
                            </div>

                            <label class="font-weight-bold mb-2 d-block">
                                <i class="fas fa-users mr-1 text-success"></i> Pilih Kontributor (yang dapat poin)
                                <span class="text-danger">*</span>
                            </label>
                            <p class="small text-muted mb-2">
                                Pilih siapa saja yang berkontribusi dalam task ini. Yang hanya membantu meng-checklist juga bisa dipilih.
                            </p>

                            <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto; background-color: #f8f9fa;">
                                @foreach($task->project->users as $member)
                                    @php
                                        $isChecked = $task->contributors->contains($member->id)
                                                  || $task->assigned_to == $member->id;
                                    @endphp
                                    <label for="contributor_{{ $task->id }}_{{ $member->id }}"
                                           class="d-flex align-items-center p-2 mb-2 rounded bg-white shadow-sm"
                                           style="cursor: pointer; border: 2px solid transparent; transition: all 0.15s; margin-bottom: 0.5rem;"
                                           onmouseover="this.style.borderColor='#4e73df'; this.style.backgroundColor='#f0f3ff';"
                                           onmouseout="this.style.borderColor='transparent'; this.style.backgroundColor='#ffffff';">
                                        <input type="checkbox"
                                               id="contributor_{{ $task->id }}_{{ $member->id }}"
                                               name="contributors[]"
                                               value="{{ $member->id }}"
                                               style="width: 22px; height: 22px; margin: 0 12px 0 0; cursor: pointer; flex-shrink: 0;"
                                               {{ $isChecked ? 'checked' : '' }}>
                                        @if($member->avatar)
                                            <img src="{{ asset('storage/avatars/' . $member->avatar) }}"
                                                 class="rounded-circle mr-2"
                                                 style="width: 32px; height: 32px; object-fit: cover; border: 2px solid #fff; flex-shrink: 0;">
                                        @else
                                            <div class="rounded-circle bg-primary text-white mr-2 d-flex align-items-center justify-content-center"
                                                 style="width: 32px; height: 32px; font-size: 12px; font-weight: 700; flex-shrink: 0;">
                                                {{ strtoupper(substr($member->name, 0, 2)) }}
                                            </div>
                                        @endif
                                        <span class="font-weight-bold text-dark flex-grow-1" style="font-size: 14px;">
                                            {{ $member->name }}
                                        </span>
                                        @if($task->assigned_to == $member->id)
                                            <span class="badge badge-info badge-pill px-2 py-1 ml-2" style="font-size: 11px;">
                                                PIC
                                            </span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check mr-1"></i> Tandai Selesai
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    {{-- MODAL DETAIL LAPORAN HARIAN --}}
    @foreach($dailyReports ?? [] as $report)
        <div class="modal fade" id="detailReportModal{{ $report->id }}" tabindex="-1" role="dialog" aria-labelledby="detailReportModalLabel{{ $report->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold text-success" id="detailReportModalLabel{{ $report->id }}">Detail Laporan: {{ $report->report_date }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-left">
                        <p><strong>Tanggal Laporan:</strong><br>{{ $report->report_date }}</p>
                        <p><strong>Pembuat (Kepala Divisi):</strong><br>{{ $report->user->name ?? '-' }}</p>
                        <p><strong>Project Terkait:</strong><br>{{ $report->project->name ?? 'Umum / Tanpa Project Khusus' }}</p>
                        <p><strong>Pekerjaan Selesai:</strong><br>{{ $report->completed_work }}</p>
                        <p><strong>Pekerjaan Belum Selesai:</strong><br>{{ $report->unfinished_work }}</p>
                        <p><strong>Kendala / Hambatan:</strong><br>{{ $report->problems ?? '-' }}</p>
                        <p><strong>Rencana Berikutnya:</strong><br>{{ $report->next_plan ?? '-' }}</p>
                        <p><strong>Catatan Tambahan:</strong><br>{{ $report->notes ?? '-' }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Modal Buat Laporan Harian -->
    <div class="modal fade" id="addReportModal" tabindex="-1" role="dialog" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <form action="{{ route('division.reports.store', $divisionName) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-success" id="addReportModalLabel">Buat Laporan Harian: {{ $divisionName }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle mr-1"></i> Data <strong>Pekerjaan Selesai</strong> akan ditarik secara otomatis dari task/checklist anggota divisi yang sudah dicentang selesai. Anda cukup melengkapi catatan di bawah ini.
                    </div>
                    <div class="form-group">
                        <label><strong>Tanggal Laporan</strong> <span class="text-danger">*</span></label>
                        <input type="date" name="report_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label><strong>Pilih Project Terkait (Opsional)</strong></label>
                        <select name="project_id" class="form-control">
                            <option value="">-- Pilih Project --</option>
                            @foreach($projects as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label><strong>Kendala / Hambatan (Jika ada)</strong></label>
                        <textarea name="problems" class="form-control" rows="2" placeholder="Tuliskan kendala di lapangan/divisi..."></textarea>
                    </div>
                    <div class="form-group">
                        <label><strong>Rencana Berikutnya (Next Plan)</strong></label>
                        <textarea name="next_plan" class="form-control" rows="2" placeholder="Tuliskan rencana kerja berikutnya..."></textarea>
                    </div>
                    <div class="form-group">
                        <label><strong>Catatan Tambahan Kepala Divisi</strong></label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan opsional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm">Simpan & Terbitkan Laporan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- JS: SEARCH + FILTER + MOVE MODAL --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal').forEach(function (modal) {
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });

            const searchInput = document.getElementById('taskSearchInput');
            const projectFilter = document.getElementById('projectFilter');
            const clearBtn = document.getElementById('clearSearchBtn');
            const noResults = document.getElementById('noResults');

            function applyFilter() {
                const search = (searchInput.value || '').toLowerCase().trim();
                const selectedProject = projectFilter.value;
                let totalVisible = 0;

                document.querySelectorAll('.project-group').forEach(function (group) {
                    const groupProjectId = group.dataset.projectId;

                    if (selectedProject && groupProjectId !== selectedProject) {
                        group.style.display = 'none';
                        return;
                    }

                    let groupVisibleCount = 0;
                    group.querySelectorAll('.task-row').forEach(function (row) {
                        const title = row.dataset.taskTitle || '';
                        const desc = row.dataset.taskDesc || '';
                        const pic = row.dataset.pic || '';

                        const match = !search
                            || title.includes(search)
                            || desc.includes(search)
                            || pic.includes(search);

                        row.style.display = match ? '' : 'none';
                        if (match) groupVisibleCount++;
                    });

                    group.style.display = groupVisibleCount > 0 ? '' : 'none';
                    totalVisible += groupVisibleCount;
                });

                noResults.style.display = totalVisible === 0 ? 'block' : 'none';
            }

            searchInput.addEventListener('input', applyFilter);
            projectFilter.addEventListener('change', applyFilter);
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                applyFilter();
            });
        });
    </script>
@endsection