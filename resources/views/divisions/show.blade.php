@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $userDiv = strtolower(trim($user->divisi ?? $user->division ?? ''));
        $userRole = strtolower(trim($user->role ?? ''));
        $isManager = str_contains($userDiv, 'pembina') || str_contains($userDiv, 'ketua') || $userRole === 'administrator' || str_contains($userRole, 'ustadz');
        
        $backUrl = route('projects.index');
        if (!$isManager) {
            if (str_contains($userDiv, 'lapangan')) {
                $backUrl = route('division.show', 'Tim Lapangan');
            } elseif (str_contains($userDiv, 'editing')) {
                $backUrl = route('division.show', 'Tim Editing');
            } elseif (str_contains($userDiv, 'broadcast')) {
                $backUrl = route('division.show', 'Tim Broadcast');
            } elseif (str_contains($userDiv, 'ai') || str_contains($userDiv, 'research')) {
                $backUrl = route('division.show', 'Tim AI Research');
            }
        }
    @endphp

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tasks mr-2"></i> {{ $project->name }}</h1>
        <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Gagal!</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Informasi Utama & Progress Otomatis -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi & Brief Project</h6>
                </div>
                <div class="card-body">
                    <div class="form-group row align-items-center mb-3">
                        <label class="col-sm-4 col-form-label font-weight-bold">Status Workflow:</label>
                        <div class="col-sm-8">
                            <form action="{{ route('projects.updateStatus', $project->id) }}" method="POST" class="d-inline-flex align-items-center">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="form-control form-control-sm mr-2 font-weight-bold {{ $project->status == 'Completed' ? 'text-success border-success' : ($project->status == 'Overdue' ? 'text-danger border-danger' : ($project->status == 'Revision' ? 'text-info border-info' : 'text-warning border-warning')) }}" style="width: 170px;" onchange="this.form.submit()">
                                    <option value="Planning" {{ $project->status == 'Planning' ? 'selected' : '' }}>Planning</option>
                                    <option value="On Progress" {{ $project->status == 'On Progress' ? 'selected' : '' }}>On Progress</option>
                                    <option value="Review" {{ $project->status == 'Review' ? 'selected' : '' }}>Review</option>
                                    <option value="Revision" {{ $project->status == 'Revision' ? 'selected' : '' }}>Revision</option>
                                    <option value="Completed" {{ $project->status == 'Completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="Overdue" {{ $project->status == 'Overdue' ? 'selected' : '' }}>Overdue</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <p><strong>Tanggal Mulai:</strong> {{ $project->start_date }} | <strong>Deadline:</strong> {{ $project->deadline }}</p>
                    <p><strong>Orang Terlibat:</strong> 
                        @foreach($project->users as $usr)
                            <span class="badge badge-info">{{ $usr->name }}</span>
                        @endforeach
                    </p>
                    <p><strong>Link Referensi:</strong> 
                        @if($project->reference_link)
                            <a href="{{ $project->reference_link }}" target="_blank">Buka Link</a>
                        @else
                            -
                        @endif
                    </p>
                    <hr>
                    <p><strong>Brief / Instruksi:</strong></p>
                    <p class="text-muted">{{ $project->brief ?? 'Tidak ada brief khusus.' }}</p>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Progress Otomatis</h6>
                </div>
                <div class="card-body text-center">
                    <h2 class="font-weight-bold text-primary mb-3">{{ $progress }}%</h2>
                    <div class="progress mb-4" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progress }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">{{ $progress }}%</div>
                    </div>
                    <p class="text-muted small mb-0">Dihitung dari jumlah task yang selesai dibagi total keseluruhan task.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Task & Checklist -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Task & Checklist Harian</h6>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addTaskModal">
                <i class="fas fa-plus mr-1"></i> Tambah Task
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="5%">Status</th>
                            <th>Nama Task / Checklist</th>
                            <th>PIC</th>
                            <th>Kontributor (Poin)</th>
                            <th>Target Tanggal</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->tasks as $task)
                        <tr>
                            <td class="text-center">
                                @if($task->status == 'Completed')
                                    <form action="{{ route('tasks.toggle', $task->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success" title="Klik untuk buka kembali">
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
                                    <br><small class="text-muted">{{ Str::limit($task->description, 50) }}</small>
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
                            <td class="text-center">
                                <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#detailTaskModal{{ $task->id }}" title="Detail Task">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="{{ route('tasks.edit', $task->id) }}" class="btn btn-warning btn-sm" title="Edit Task">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('tasks.destroy', $task->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus task ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus Task"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada task yang dibuat untuk project ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===================================================== --}}
    {{-- MODALS DIPINDAH KE LUAR TABEL (biar tidak berantakan) --}}
    {{-- ===================================================== --}}

    @foreach($project->tasks as $task)
        {{-- ============ MODAL COMPLETE TASK ============ --}}
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
                            @foreach($project->users as $member)
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

        {{-- ============ MODAL DETAIL TASK ============ --}}
        <div class="modal fade" id="detailTaskModal{{ $task->id }}" tabindex="-1" role="dialog" aria-labelledby="detailTaskModalLabel{{ $task->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold text-primary" id="detailTaskModalLabel{{ $task->id }}">Detail Task: {{ $task->title }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-left">
                        <p><strong>Judul Task:</strong><br>{{ $task->title }}</p>
                        <p><strong>Status Pengerjaan:</strong><br>
                            <span class="badge {{ $task->status == 'Completed' ? 'badge-success' : 'badge-warning' }}">{{ $task->status }}</span>
                        </p>
                        <p><strong>Penanggung Jawab (PIC):</strong><br>{{ $task->assignedUser->name ?? 'Belum ada PIC' }}</p>
                        <p><strong>Kontributor (Poin):</strong><br>
                            @forelse($task->contributors as $c)
                                <span class="badge badge-success mr-1 mb-1">
                                    <i class="fas fa-star mr-1"></i>{{ $c->name }}
                                </span>
                            @empty
                                <span class="text-muted">Belum ada kontributor</span>
                            @endforelse
                        </p>
                        <p><strong>Target Tanggal:</strong><br>{{ $task->task_date ?? '-' }}</p>
                        <p><strong>Waktu Selesai:</strong><br>{{ $task->completed_at ?? '-' }}</p>
                        <p><strong>Deskripsi / Catatan:</strong><br>{{ $task->description ?? 'Tidak ada catatan tambahan.' }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Modal Tambah Task -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form action="{{ route('projects.tasks.store', $project->id) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Tambah Task / Checklist Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><strong>Judul Task / Checklist</strong> <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Contoh: Membuat konsep & skrip">
                    </div>
                    <div class="form-group">
                        <label><strong>Penanggung Jawab (PIC)</strong></label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Pilih Anggota --</option>
                            @foreach($project->users as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label><strong>Target Tanggal</strong></label>
                        <input type="date" name="task_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><strong>Deskripsi / Catatan Tambahan</strong></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Task</button>
                </div>
            </form>
        </div>
    </div>
@endsection