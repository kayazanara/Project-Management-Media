@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-project-diagram mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Tambah Project Baru
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Statistik Jumlah Proyek Berdasarkan Status (9 Status + Overdue Flag) --}}
    <div class="row">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Planning</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['planning'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-hourglass-start fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Task Review</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['task_review'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tasks fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Scoring</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['scoring'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-star fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">On Progress</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['on_progress'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-spinner fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Review</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['review'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-search fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Revision</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['revision'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-redo fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['approved'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Upload</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['upload'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-upload fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['overdue'] }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Selesai</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $completedProjects->count() }} Project</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-double fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar Proyek Aktif --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Proyek Aktif</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Project</th>
                            <th>Orang Terlibat</th>
                            <th>Mulai</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Link Referensi</th>
                            <th style="min-width: 150px;">Progress</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $index => $project)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $project->name }}</strong>
                                @if($project->is_overdue)
                                    <br><span class="badge badge-danger mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>OVERDUE
                                    </span>
                                @endif
                            </td>
                            <td>
                                @foreach($project->users as $usr)
                                    <span class="badge badge-info">{{ $usr->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $project->start_date?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $project->deadline?->format('d M Y') ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $project->status_badge }}">
                                    {{ $project->status }}
                                </span>
                            </td>
                            <td>
                                @if($project->reference_link)
                                    <a href="{{ $project->reference_link }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt"></i> Buka Link
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 mr-2" style="height: 22px;">
                                        <div class="progress-bar bg-{{ $project->progress_color }}"
                                             role="progressbar"
                                             style="width: {{ $project->progress }}%;"
                                             aria-valuenow="{{ $project->progress }}"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            {{ $project->progress }}%
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    {{ $project->completed_tasks_count ?? 0 }}/{{ $project->total_tasks_count ?? 0 }} task
                                </small>
                            </td>
                            <td>
                                <a href="{{ route('projects.show', $project->id) }}" class="btn btn-info btn-sm" title="Detail & Task">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('projects.edit', $project->id) }}" class="btn btn-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('projects.destroy', $project->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus project ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">Belum ada proyek aktif saat ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Riwayat Proyek Selesai --}}
    <div class="card shadow mb-4 border-left-success">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i> Riwayat Proyek Selesai (Completed)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Project</th>
                            <th>Orang Terlibat</th>
                            <th>Mulai</th>
                            <th>Deadline</th>
                            <th>Selesai</th>
                            <th>Progress</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedProjects as $index => $compProject)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $compProject->name }}</strong></td>
                            <td>
                                @foreach($compProject->users as $usr)
                                    <span class="badge badge-info">{{ $usr->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $compProject->start_date?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $compProject->deadline?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @if($compProject->completed_at)
                                    <span class="badge badge-success">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        {{ $compProject->completed_at->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="badge badge-success">Completed</span>
                                @endif
                            </td>
                            <td>
                                <div class="progress" style="height: 22px;">
                                    <div class="progress-bar bg-{{ $compProject->progress_color }}"
                                         role="progressbar"
                                         style="width: {{ $compProject->progress }}%;"
                                         aria-valuenow="{{ $compProject->progress }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        {{ $compProject->progress }}%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('projects.show', $compProject->id) }}" class="btn btn-info btn-sm" title="Lihat Detail">
                                    <i class="fas fa-eye"></i> Buka Project
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada proyek yang selesai.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection