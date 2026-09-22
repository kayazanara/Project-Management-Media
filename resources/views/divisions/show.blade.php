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
                <div class="d-flex flex-wrap gap-2">
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $index => $proj)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $proj->name }}</strong></td>
                            <td>
                                @if($proj->status == 'On Progress')
                                    <span class="badge badge-warning">On Progress</span>
                                @elseif($proj->status == 'Review')
                                    <span class="badge badge-info">Review</span>
                                @elseif($proj->status == 'Revision')
                                    <span class="badge badge-primary">Revision</span>
                                @elseif($proj->status == 'Overdue')
                                    <span class="badge badge-danger">Overdue</span>
                                @else
                                    <span class="badge badge-secondary">Planning</span>
                                @endif
                            </td>
                            <td>{{ $proj->deadline }}</td>
                            <td class="text-center">
                                <a href="{{ route('projects.show', $proj->id) }}" class="btn btn-info btn-sm" title="Lihat Detail & Task">
                                    <i class="fas fa-eye"></i> Buka Project
                                </a>
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

    <!-- Daftar Task & Progress Anggota Divisi (Aktif) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Monitoring Task & Progress Anggota Divisi (Aktif)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="5%">Status</th>
                            <th>Nama Task / Checklist</th>
                            <th>Project Sumber</th>
                            <th>PIC (Anggota)</th>
                            <th>Target Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                        <tr>
                            <td class="text-center">
                                <form action="{{ route('tasks.toggle', $task->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $task->status == 'Completed' ? 'btn-success' : 'btn-outline-secondary' }}" title="Klik untuk ubah status">
                                        <i class="fas {{ $task->status == 'Completed' ? 'fa-check-square' : 'fa-square' }}"></i>
                                    </button>
                                </form>
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
                                <a href="{{ route('projects.show', $task->project_id) }}">
                                    {{ $task->project->name ?? '-' }}
                                </a>
                            </td>
                            <td><strong>{{ $task->assignedUser->name ?? '-' }}</strong></td>
                            <td>{{ $task->task_date ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada task aktif yang ditugaskan ke anggota di divisi ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Daftar Riwayat Laporan Harian Divisi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-success">Riwayat Laporan Harian {{ $divisionName }}</h6>
            
            <!-- Form Filter Waktu (Pekan / Bulan / Tahun) -->
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
                                <!-- Tombol Detail Laporan -->
                                <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#detailReportModal{{ $report->id }}" title="Detail Laporan">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <!-- Tombol Hapus Laporan -->
                                <form action="{{ route('division.reports.destroy', $report->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus laporan harian ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus Laporan"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Detail Laporan Harian per Item -->
                        <div class="modal fade" id="detailReportModal{{ $report->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title font-weight-bold text-success">Detail Laporan: {{ $report->report_date }}</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body text-left">
                                        <p><strong>Tanggal Laporan:</strong><br>{{ $report->report_date }}</p>
                                        <p><strong>Pembuat (Kepala Divisi):</strong><br>{{ $report->user->name ?? '-' }}</p>
                                        <p><strong>Project Terkait:</strong><br>{{ $report->project->name ?? 'Umum / Tanpa Project Khusus' }}</p>
                                        <p><strong>Pekerjaan Selesai (Otomatis):</strong><br>{{ $report->completed_work }}</p>
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

    <!-- Riwayat Project Selesai (Completed Projects) -> Dipindah ke Bawah Laporan Harian -->
    <div class="card shadow mb-4 border-left-success">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i> Riwayat Project Selesai (Completed)</h6>
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedProjects as $index => $compProj)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $compProj->name }}</strong></td>
                            <td>
                                <span class="badge badge-success">Completed</span>
                            </td>
                            <td>{{ $compProj->deadline }}</td>
                            <td class="text-center">
                                <a href="{{ route('projects.show', $compProj->id) }}" class="btn btn-info btn-sm" title="Lihat Detail Project">
                                    <i class="fas fa-eye"></i> Buka Project
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada project yang selesai untuk divisi ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Buat Laporan Harian -->
    <div class="modal fade" id="addReportModal" tabindex="-1" role="dialog" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
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
@endsection