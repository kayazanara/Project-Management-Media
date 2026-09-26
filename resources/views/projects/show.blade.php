@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $userDiv = strtolower(trim($user->division ?? ''));
        $isManager = $user->isManager();
        $isKepalaDivisi = $user->isKepalaDivisi();
        $isKetuaMedia = $user->isKetuaMedia();
        $isPembina = $user->isPembina();

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

        $statusLabel = [
            'Planning'    => ['label' => 'Planning', 'color' => 'secondary', 'icon' => 'fa-clipboard-list'],
            'Task Review' => ['label' => 'Task Review', 'color' => 'info', 'icon' => 'fa-tasks'],
            'Scoring'     => ['label' => 'Scoring', 'color' => 'primary', 'icon' => 'fa-star'],
            'On Progress' => ['label' => 'On Progress', 'color' => 'warning', 'icon' => 'fa-spinner'],
            'Review'      => ['label' => 'Review', 'color' => 'info', 'icon' => 'fa-search'],
            'Revision'    => ['label' => 'Revision', 'color' => 'danger', 'icon' => 'fa-redo'],
            'Approved'    => ['label' => 'Approved', 'color' => 'success', 'icon' => 'fa-check-circle'],
            'Upload'      => ['label' => 'Upload', 'color' => 'primary', 'icon' => 'fa-upload'],
            'Completed'   => ['label' => 'Completed', 'color' => 'success', 'icon' => 'fa-check-double'],
        ];
        $statusInfo = $statusLabel[$project->status] ?? ['label' => $project->status, 'color' => 'secondary', 'icon' => 'fa-circle'];

        // Task summary
        $completedTasksList = $project->tasks->where('status', 'Completed');
        $pendingTasksList = $project->tasks->where('status', '!=', 'Completed');

        // ⚡ Fallback: kalau controller tidak mengirim, hitung sendiri
        if (!isset($projectContentsCount)) {
            $projectContentsCount = \App\Models\Content::where('project_id', $project->id)->count();
        }
    @endphp

    {{-- HEADER --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                <i class="fas fa-tasks mr-2"></i> {{ $project->name }}
            </h1>
            <div>
                @if($project->is_overdue && $project->status !== 'Completed')
                    <span class="badge badge-danger">
                        <i class="fas fa-exclamation-triangle mr-1"></i>OVERDUE
                    </span>
                @endif
                <span class="badge badge-{{ $statusInfo['color'] }}" style="font-size: 13px;">
                    <i class="fas {{ $statusInfo['icon'] }} mr-1"></i>{{ $statusInfo['label'] }}
                </span>
            </div>
        </div>
        <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali
        </a>
    </div>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- WORKFLOW PANEL --}}
    @if($project->status !== 'Completed')
        <div class="card shadow mb-4" style="border-left: 4px solid #4e73df;">
            <div class="card-header py-3 bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-stream mr-1"></i> Panel Workflow
                </h6>
            </div>
            <div class="card-body">

                {{-- 1. PLANNING --}}
                @if($project->status === 'Planning')
                    <div class="text-center py-3">
                        <i class="fas fa-clipboard-list fa-3x text-secondary mb-3"></i>
                        <h5>Tahap Planning</h5>
                        <p class="text-muted mb-3">
                            Project baru dibuat. Silakan susun task yang perlu dikerjakan.
                        </p>
                        @if($isKetuaMedia)
                            <form action="{{ route('projects.submitTaskReview', $project->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Ajukan task ke Pembina untuk scoring?')">
                                    <i class="fas fa-paper-plane mr-1"></i> Ajukan ke Pembina ({{ $project->tasks->count() }} Task)
                                </button>
                            </form>
                        @elseif($isPembina)
                            <span class="badge badge-info">Menunggu Ketua Media menyusun task</span>
                        @endif
                    </div>
                @endif

                {{-- 2. TASK REVIEW --}}
                @if($project->status === 'Task Review')
                    <div class="text-center py-3">
                        <i class="fas fa-tasks fa-3x text-info mb-3"></i>
                        <h5>Tahap Task Review</h5>
                        <p class="text-muted mb-3">
                            Ketua Media sudah menyusun task. Pembina perlu memberi bobot & approve setiap task.
                        </p>
                        @if($isPembina)
                            <button class="btn btn-primary" data-toggle="modal" data-target="#scoringModal">
                                <i class="fas fa-star mr-1"></i> Mulai Scoring
                            </button>
                        @else
                            <span class="badge badge-info">Menunggu scoring dari Pembina</span>
                        @endif
                    </div>
                @endif

                {{-- 3. SCORING --}}
                @if($project->status === 'Scoring')
                    <div class="text-center py-3">
                        <i class="fas fa-star fa-3x text-primary mb-3"></i>
                        <h5>Tahap Scoring</h5>
                        <p class="text-muted mb-3">
                            Scoring selesai. Rilis project untuk memulai pengerjaan tim.
                        </p>
                        @if($isPembina)
                            <form action="{{ route('projects.release', $project->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-success btn-lg"
                                        onclick="return confirm('Rilis project? Semua anggota akan menerima notifikasi.')">
                                    <i class="fas fa-rocket mr-1"></i> Rilis Project
                                </button>
                            </form>
                        @else
                            <span class="badge badge-primary">Menunggu rilis dari Pembina</span>
                        @endif
                    </div>
                @endif

                {{-- 4. ON PROGRESS --}}
                @if($project->status === 'On Progress')
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-3x text-warning mb-3"></i>
                        <h5>Tahap On Progress</h5>
                        <p class="text-muted mb-3">
                            Tim sedang mengerjakan task. Kepala Divisi dapat mengajukan review setelah selesai.
                        </p>
                        @if($isKepalaDivisi)
                            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#reviewRequestModal">
                                <i class="fas fa-paper-plane mr-1"></i> Ajukan Review
                            </button>
                        @else
                            <span class="badge badge-warning">Menunggu Kepala Divisi ajukan review</span>
                        @endif
                    </div>
                @endif

                {{-- 5. REVIEW --}}
                @if($project->status === 'Review')
                    <div class="py-3">
                        <div class="text-center mb-4">
                            <i class="fas fa-search fa-3x text-info mb-3"></i>
                            <h5>Tahap Review</h5>
                            <p class="text-muted mb-0">Menunggu persetujuan Pembina & Ketua Media.</p>
                        </div>

                        @if($latestReviewRequest)
                            <div class="card border-left-info mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="font-weight-bold text-info mb-0">
                                            <i class="fas fa-user-check mr-1"></i> Pengajuan Review dari Kepala Divisi
                                        </h6>
                                        <small class="text-muted">{{ $latestReviewRequest->created_at->translatedFormat('d M Y, H:i') }}</small>
                                    </div>
                                    <p class="mb-2">
                                        <strong>{{ $latestReviewRequest->user->name ?? '-' }}</strong>
                                        <span class="badge badge-secondary ml-1">{{ $latestReviewRequest->user->division ?? '-' }}</span>
                                    </p>

                                    <div class="p-2 rounded bg-light mb-3" style="white-space: pre-line;">
                                        {{ $latestReviewRequest->note }}
                                    </div>

                                    @if($latestReviewRequest->file_url)
                                        <p class="mb-3">
                                            <strong><i class="fas fa-link mr-1"></i> Link File Hasil:</strong><br>
                                            <a href="{{ $latestReviewRequest->file_url }}" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="fas fa-external-link-alt mr-1"></i> Buka Link
                                            </a>
                                        </p>
                                    @endif

                                    <hr>

                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="h5 mb-0 font-weight-bold text-success">{{ $completedTasksList->count() }}</div>
                                            <small class="text-muted">Task Selesai</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 mb-0 font-weight-bold text-warning">{{ $pendingTasksList->count() }}</div>
                                            <small class="text-muted">Task Pending</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 mb-0 font-weight-bold text-primary">{{ $project->tasks->count() }}</div>
                                            <small class="text-muted">Total Task</small>
                                        </div>
                                    </div>

                                    @if($completedTasksList->count() > 0)
                                        <h6 class="small font-weight-bold text-success mb-2">
                                            <i class="fas fa-check-circle mr-1"></i> Task Selesai
                                        </h6>
                                        <ul class="small mb-3">
                                            @foreach($completedTasksList as $t)
                                                <li>{{ $t->title }}</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if($pendingTasksList->count() > 0)
                                        <h6 class="small font-weight-bold text-warning mb-2">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Task Belum Selesai
                                        </h6>
                                        <ul class="small mb-0">
                                            @foreach($pendingTasksList as $t)
                                                <li>{{ $t->title }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="row justify-content-center mb-3">
                            <div class="col-md-5">
                                <div class="card border-{{ $project->approved_by_pembina_at ? 'success' : 'secondary' }}">
                                    <div class="card-body py-2">
                                        <i class="fas fa-shield-alt mr-1"></i> <strong>Pembina</strong><br>
                                        @if($project->approved_by_pembina_at)
                                            <span class="badge badge-success">
                                                <i class="fas fa-check mr-1"></i>APPROVED
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">Belum ACC</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="card border-{{ $project->approved_by_ketua_at ? 'success' : 'secondary' }}">
                                    <div class="card-body py-2">
                                        <i class="fas fa-crown mr-1"></i> <strong>Ketua Media</strong><br>
                                        @if($project->approved_by_ketua_at)
                                            <span class="badge badge-success">
                                                <i class="fas fa-check mr-1"></i>APPROVED
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">Belum ACC</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($isManager)
                            <div class="text-center">
                                <div class="btn-group">
                                    @if(($isPembina && !$project->approved_by_pembina_at) || ($isKetuaMedia && !$project->approved_by_ketua_at))
                                        <form action="{{ route('projects.approve', $project->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-success"
                                                    onclick="return confirm('Setujui project ini?')">
                                                <i class="fas fa-check mr-1"></i> Approve
                                            </button>
                                        </form>
                                    @endif
                                    <button class="btn btn-danger" data-toggle="modal" data-target="#revisionModal">
                                        <i class="fas fa-redo mr-1"></i> Minta Revisi
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- 6. REVISION --}}
                @if($project->status === 'Revision')
                    <div class="py-3">
                        <div class="text-center mb-3">
                            <i class="fas fa-redo fa-3x text-danger mb-3"></i>
                            <h5>Tahap Revision</h5>
                            <p class="text-muted mb-0">
                                Ada catatan revisi dari Pembina/Ketua Media. Silakan perbaiki dan ajukan review ulang.
                            </p>
                        </div>

                        @if($latestRevision)
                            <div class="card border-left-danger mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="font-weight-bold text-danger mb-0">
                                            <i class="fas fa-comment-dots mr-1"></i> Catatan Revisi Terakhir
                                        </h6>
                                        <small class="text-muted">{{ $latestRevision->created_at->translatedFormat('d M Y, H:i') }}</small>
                                    </div>
                                    <p class="mb-1 small"><strong>Dari:</strong> {{ $latestRevision->user->name ?? '-' }}</p>
                                    <div class="p-2 rounded bg-light" style="white-space: pre-line;">
                                        {{ $latestRevision->note }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($isKepalaDivisi)
                            <div class="text-center">
                                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#reviewRequestModal">
                                    <i class="fas fa-paper-plane mr-1"></i> Ajukan Review Ulang
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- 7. APPROVED --}}
                @if($project->status === 'Approved')
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>Tahap Approved</h5>
                        <p class="text-muted mb-0">Project disetujui. Berlanjut ke tahap Upload.</p>
                    </div>
                @endif

                {{-- 8. UPLOAD — ⚡ Dengan guard content + tombol upload --}}
                @if($project->status === 'Upload')
                    <div class="py-3">
                        <div class="text-center mb-4">
                            <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                            <h5>Tahap Upload</h5>
                            <p class="text-muted mb-0">
                                Upload konten (raw / stock / activity). Setelah selesai, klik "Mark Complete".
                            </p>
                        </div>

                        @if($isManager || $isKepalaDivisi)
                            @php
                                $hasContent = $projectContentsCount > 0;
                            @endphp

                            @if($hasContent)
                                {{-- Sudah ada konten, boleh complete --}}
                                <div class="alert alert-success small mb-3">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <strong>{{ $projectContentsCount }} konten</strong> sudah di-upload untuk project ini. Siap di-complete.
                                </div>

                                <div class="text-center mb-3">
                                    <a href="{{ route('contents.formRaw', ['project_id' => $project->id]) }}" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-file-upload mr-1"></i> Upload Raw Lagi
                                    </a>
                                    <a href="{{ route('contents.formStock', ['project_id' => $project->id]) }}" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-boxes mr-1"></i> Upload Stock Lagi
                                    </a>
                                    <a href="{{ route('contents.formActivity', ['project_id' => $project->id]) }}" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-video mr-1"></i> Upload Activity Lagi
                                    </a>
                                </div>

                                <div class="text-center">
                                    <form action="{{ route('projects.markComplete', $project->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-success btn-lg"
                                                onclick="return confirm('Tandai project sebagai COMPLETED?')">
                                            <i class="fas fa-check-double mr-1"></i> Mark as Complete
                                        </button>
                                    </form>
                                </div>
                            @else
                                {{-- Belum ada konten --}}
                                <div class="alert alert-warning mb-3">
                                    <h6 class="font-weight-bold mb-2">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Belum Ada Konten yang Di-upload
                                    </h6>
                                    <p class="small mb-2">
                                        Sebelum bisa <strong>Mark Complete</strong>, minimal upload <strong>1 konten</strong> (raw / stock / activity).
                                    </p>
                                    <p class="small mb-0">
                                        <strong>Kalau tidak ada bahan</strong> untuk di-upload, silakan upload <strong>Activity</strong> —
                                        seperti live kajian / event — dan isi deskripsinya sebagai dokumentasi project.
                                    </p>
                                </div>

                                <div class="text-center">
                                    <a href="{{ route('contents.formActivity', ['project_id' => $project->id]) }}" class="btn btn-primary">
                                        <i class="fas fa-video mr-1"></i> Upload Activity Dulu
                                    </a>
                                    <a href="{{ route('contents.formStock', ['project_id' => $project->id]) }}" class="btn btn-outline-success btn-sm ml-1">
                                        <i class="fas fa-boxes mr-1"></i> Upload Stock
                                    </a>
                                    <a href="{{ route('contents.formRaw', ['project_id' => $project->id]) }}" class="btn btn-outline-info btn-sm ml-1">
                                        <i class="fas fa-file-upload mr-1"></i> Upload Raw
                                    </a>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>
        </div>
    @endif

    {{-- INFO UTAMA + PROGRESS --}}
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi & Brief Project</h6>
                </div>
                <div class="card-body">
                    <p><strong>Tanggal Mulai:</strong> {{ $project->start_date->format('d M Y') }} |
                       <strong>Deadline:</strong> {{ $project->deadline->format('d M Y') }}</p>

                    <p><strong>Orang Terlibat:</strong>
                        @foreach($project->users as $usr)
                            <span class="badge badge-info">{{ $usr->name }}</span>
                        @endforeach
                    </p>

                    <p><strong>Link Referensi:</strong>
                        @if($project->reference_link)
                            <a href="{{ $project->reference_link }}" target="_blank">Buka Link</a>
                        @else - @endif
                    </p>

                    <hr>
                    <p><strong>Brief / Instruksi:</strong></p>
                    <p class="text-muted">{{ $project->brief ?? 'Tidak ada brief khusus.' }}</p>

                    <hr>
                    <p><strong>Timeline:</strong></p>
                    <small class="text-muted">
                        @if($project->released_at)
                            Rilis: {{ $project->released_at->format('d M Y H:i') }}<br>
                        @endif
                        @if($project->approved_by_pembina_at)
                            Approved Pembina: {{ $project->approved_by_pembina_at->format('d M Y H:i') }}<br>
                        @endif
                        @if($project->approved_by_ketua_at)
                            Approved Ketua Media: {{ $project->approved_by_ketua_at->format('d M Y H:i') }}<br>
                        @endif
                        @if($project->completed_at)
                            Completed: {{ $project->completed_at->format('d M Y H:i') }}
                        @endif
                    </small>
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
                        <div class="progress-bar bg-success" style="width: {{ $progress }}%">{{ $progress }}%</div>
                    </div>
                    <p class="text-muted small mb-0">Dihitung dari task selesai / total task.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- RIWAYAT REVISI + PENGAJUAN REVIEW --}}
    @php
        $allRevisionHistory = $project->revisions;
    @endphp

    @if($allRevisionHistory->count() > 0)
        <div class="card shadow mb-4 border-left-secondary">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-history mr-1"></i> Riwayat Aktivitas Review & Revisi ({{ $allRevisionHistory->count() }})
                </h6>
            </div>
            <div class="card-body">
                @foreach($allRevisionHistory as $rev)
                    @php
                        $isReviewRequest = ($rev->type ?? 'revision') === 'review_request';
                        $borderColor = $isReviewRequest ? 'info' : 'danger';
                        $badge = $isReviewRequest
                            ? '<span class="badge badge-info">Pengajuan Review</span>'
                            : '<span class="badge badge-danger">Catatan Revisi</span>';
                    @endphp
                    <div class="border-left border-{{ $borderColor }} pl-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                {!! $badge !!}
                                <strong class="ml-2">{{ $rev->user->name ?? '-' }}</strong>
                                <small class="text-muted ml-1">({{ $rev->user->division ?? '-' }})</small>
                            </div>
                            <small class="text-muted">{{ $rev->time_ago }}</small>
                        </div>
                        <p class="mb-1" style="white-space: pre-line;">{{ $rev->note }}</p>

                        @if($isReviewRequest && $rev->file_url)
                            <a href="{{ $rev->file_url }}" target="_blank" class="small">
                                <i class="fas fa-link mr-1"></i> {{ Str::limit($rev->file_url, 60) }}
                            </a>
                        @endif

                        @if(!$isReviewRequest)
                            <small class="text-muted">
                                Status:
                                @if($rev->resolved_at)
                                    <span class="badge badge-success">Resolved</span>
                                @else
                                    <span class="badge badge-warning">Open</span>
                                @endif
                            </small>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- DAFTAR TASK --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Daftar Task & Checklist ({{ $project->tasks->count() }})
            </h6>
            @if($isPembina || $isKetuaMedia)
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addTaskModal">
                    <i class="fas fa-plus mr-1"></i> Tambah Task
                </button>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="5%">Status</th>
                            <th>Task</th>
                            <th>PIC</th>
                            <th>Kontributor</th>
                            <th>Bobot</th>
                            <th>Target</th>
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
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check-square"></i>
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-toggle="modal" data-target="#completeTaskModal{{ $task->id }}">
                                        <i class="fas fa-square"></i>
                                    </button>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $task->status == 'Completed' ? 'text-muted text-decoration-line-through' : 'font-weight-bold' }}">
                                    {{ $task->title }}
                                </span>
                                @if($task->description)
                                    <br><small class="text-muted">{{ Str::limit($task->description, 60) }}</small>
                                @endif
                                @if($task->is_approved)
                                    <span class="badge badge-success badge-sm ml-1"><i class="fas fa-check"></i></span>
                                @endif
                            </td>
                            <td>
                                @if($task->assignedUser)
                                    <span class="badge badge-secondary">{{ $task->assignedUser->name }}</span>
                                @else - @endif
                            </td>
                            <td>
                                @if($task->contributors->count() > 0)
                                    @foreach($task->contributors as $c)
                                        <span class="badge badge-success mr-1 mb-1">
                                            <i class="fas fa-star mr-1"></i>{{ $c->name }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($task->weight > 0)
                                    <span class="badge badge-primary">{{ $task->weight }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $task->task_date ?? '-' }}</td>
                            <td class="text-center">
                                @if($isPembina || $isKetuaMedia)
                                    <a href="{{ route('tasks.edit', $task->id) }}" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('tasks.destroy', $task->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus task?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Belum ada task.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- MODALS --}}
    @foreach($project->tasks as $task)
        @if($task->status != 'Completed')
        <div class="modal fade" id="completeTaskModal{{ $task->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('tasks.toggle', $task->id) }}" method="POST" class="modal-content">
                    @csrf @method('PATCH')
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title font-weight-bold">Tandai Task Selesai</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Task:</strong> {{ $task->title }}</p>
                        <hr>
                        <label class="font-weight-bold">Pilih Kontributor:</label>
                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                            @foreach($project->users as $m)
                                <label class="d-flex align-items-center p-2 mb-1 rounded bg-light" style="cursor:pointer;">
                                    <input type="checkbox" name="contributors[]" value="{{ $m->id }}"
                                           style="width:20px;height:20px;margin-right:10px;"
                                           {{ $task->assigned_to == $m->id ? 'checked' : '' }}>
                                    <span class="font-weight-bold">{{ $m->name }}</span>
                                    @if($task->assigned_to == $m->id)
                                        <span class="badge badge-info ml-2">PIC</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm">Tandai Selesai</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Add Task Modal --}}
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('projects.tasks.store', $project->id) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Task Baru</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><strong>Judul Task</strong> <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>PIC</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach($project->users as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Tanggal</label>
                        <input type="date" name="task_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Scoring Modal --}}
    @if($isPembina && $project->status === 'Task Review')
    <div class="modal fade" id="scoringModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form action="{{ route('projects.submitScoring', $project->id) }}" method="POST" class="modal-content">
                @csrf @method('PATCH')
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-star mr-1"></i> Scoring Task</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Berikan bobot (min 1, tanpa batas max) & approve setiap task.</p>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th width="15%">Bobot</th>
                                <th width="10%">Approve</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($project->tasks as $idx => $task)
                                <tr>
                                    <td>
                                        {{ $task->title }}
                                        <input type="hidden" name="tasks[{{ $idx }}][id]" value="{{ $task->id }}">
                                    </td>
                                    <td>
                                        <input type="number" name="tasks[{{ $idx }}][weight]"
                                               class="form-control form-control-sm"
                                               min="1" value="{{ $task->weight ?: 5 }}" required>
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="tasks[{{ $idx }}][is_approved]" value="0">
                                        <input type="checkbox" name="tasks[{{ $idx }}][is_approved]" value="1"
                                               {{ $task->is_approved ? 'checked' : '' }}
                                               style="width:20px;height:20px;">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Scoring</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Review Request Modal --}}
    @if($isKepalaDivisi && in_array($project->status, ['On Progress', 'Revision']))
    <div class="modal fade" id="reviewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form action="{{ route('projects.requestReview', $project->id) }}" method="POST" class="modal-content">
                @csrf @method('PATCH')
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-paper-plane mr-1"></i>
                        {{ $project->status === 'Revision' ? 'Ajukan Review Ulang' : 'Ajukan Review' }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">

                    <div class="alert alert-light border small mb-3">
                        <i class="fas fa-info-circle mr-1 text-info"></i>
                        Isi catatan pengantar supaya Pembina & Ketua Media punya konteks apa yang sudah dikerjakan.
                        Kalau ada link file hasil (Drive, dll), sertakan di bawah.
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">
                            Catatan Pengantar <span class="text-danger">*</span>
                        </label>
                        <textarea name="note" class="form-control" rows="5" minlength="10" required
                                  placeholder="Contoh: Video sudah selesai di-edit, tinggal cek color grading & audio. Semua task Lapangan sudah selesai.">{{ old('note') }}</textarea>
                        <small class="text-muted">Minimal 10 karakter. Jelaskan progres & apa yang perlu di-review.</small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Link File Hasil (Opsional)</label>
                        <input type="url" name="file_url" class="form-control" value="{{ old('file_url') }}"
                               placeholder="https://drive.google.com/...">
                        <small class="text-muted">Link Google Drive / Dropbox / WeTransfer, dll.</small>
                    </div>

                    <hr>
                    <h6 class="small font-weight-bold text-muted mb-2">
                        <i class="fas fa-tasks mr-1"></i> Ringkasan Task
                    </h6>
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="h5 mb-0 font-weight-bold text-success">{{ $completedTasksList->count() }}</div>
                            <small class="text-muted">Selesai</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0 font-weight-bold text-warning">{{ $pendingTasksList->count() }}</div>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0 font-weight-bold text-primary">{{ $project->tasks->count() }}</div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>

                    @if($pendingTasksList->count() > 0)
                        <div class="alert alert-warning small mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Masih ada <strong>{{ $pendingTasksList->count() }} task</strong> yang belum selesai.
                            Pastikan memang sudah siap di-review.
                        </div>
                    @endif

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info btn-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Revision Modal --}}
    @if($isManager && $project->status === 'Review')
    <div class="modal fade" id="revisionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('projects.requestRevision', $project->id) }}" method="POST" class="modal-content">
                @csrf @method('PATCH')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-redo mr-1"></i> Minta Revisi</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <label class="font-weight-bold">Catatan Revisi <span class="text-danger">*</span></label>
                    <textarea name="note" class="form-control" rows="5" minlength="10" required
                              placeholder="Jelaskan apa yang perlu diperbaiki..."></textarea>
                    <small class="text-muted">Minimal 10 karakter.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Kirim Revisi</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal').forEach(function (modal) {
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });
        });
    </script>
@endsection