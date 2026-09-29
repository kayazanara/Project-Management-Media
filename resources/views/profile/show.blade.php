@extends('layouts.app')

@section('content')
    @php
        $avatarUrl = $user->avatar
            ? asset('storage/avatars/' . $user->avatar)
            : asset('sb-admin-2/img/undraw_profile.svg');

        $bgStyle = $user->background_image
            ? "background-image: url('" . asset('storage/backgrounds/' . $user->background_image) . "'); background-size: cover; background-position: center;"
            : "background: " . $user->theme_gradient . ";";
    @endphp

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-circle mr-2"></i> Profil Anggota
        </h1>
        <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="card shadow mb-4" style="border-radius: 20px; overflow: hidden;">
        <div style="height: 200px; {{ $bgStyle }} position: relative;">
            <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.15));"></div>
        </div>

        <div class="card-body text-center" style="margin-top: -80px; position: relative; z-index: 2;">
            <div class="d-inline-block position-relative">
                <img src="{{ $avatarUrl }}"
                     alt="{{ $user->name }}"
                     class="rounded-circle shadow"
                     style="width: 160px; height: 160px; object-fit: cover; border: 6px solid #fff;">
            </div>

            <h2 class="font-weight-bold mt-3 mb-1" style="font-size: 26px;">{{ $user->name }}</h2>

            @if($user->jabatan)
                <div class="mb-2">
                    <span class="badge badge-warning badge-pill px-3 py-1" style="font-size: 13px;">
                        <i class="fas fa-briefcase mr-1"></i>{{ $user->jabatan }}
                    </span>
                </div>
            @endif

            <p class="text-muted mb-2">
                <span class="badge badge-primary px-3 py-1">{{ $user->role }}</span>
                <span class="badge badge-secondary px-3 py-1">{{ $user->division ?? 'Tanpa Divisi' }}</span>
            </p>

            @if($user->bio)
                <p class="text-muted mx-auto mb-3" style="max-width: 500px; font-style: italic;">
                    "{{ $user->bio }}"
                </p>
            @endif

            @if($user->social_links)
                <div class="mb-3">
                    @if(!empty($user->social_links['instagram']))
                        <a href="https://instagram.com/{{ ltrim($user->social_links['instagram'], '@') }}" target="_blank" class="btn btn-sm btn-outline-danger mx-1">
                            <i class="fab fa-instagram"></i>
                        </a>
                    @endif
                    @if(!empty($user->social_links['github']))
                        <a href="https://github.com/{{ $user->social_links['github'] }}" target="_blank" class="btn btn-sm btn-outline-dark mx-1">
                            <i class="fab fa-github"></i>
                        </a>
                    @endif
                    @if(!empty($user->social_links['facebook']))
                        <a href="https://facebook.com/{{ $user->social_links['facebook'] }}" target="_blank" class="btn btn-sm btn-outline-primary mx-1">
                            <i class="fab fa-facebook"></i>
                        </a>
                    @endif
                    @if(!empty($user->social_links['tiktok']))
                        <a href="https://tiktok.com/@{{ ltrim($user->social_links['tiktok'], '@') }}" target="_blank" class="btn btn-sm btn-outline-dark mx-1">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    @endif
                </div>
            @endif

            @if($isOwnProfile)
                <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-edit mr-1"></i> Edit Profil
                </a>
            @endif
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Task Selesai</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['completed_tasks'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-double fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Project Selesai</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['completed_projects'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-trophy fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Hari Login</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['login_days'] }} hari</div>
                            {{-- ⚡ FITUR BARU: streak login (hari berturut-turut) --}}
                            <div class="small text-muted mt-1">
                                <i class="fas fa-fire text-warning mr-1"></i>
                                Streak: <strong>{{ $stats['login_streak'] ?? 0 }} hari</strong> berturut-turut
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bergabung</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['member_since_days'] }} hari</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-plus fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history mr-1"></i> Task Terbaru yang Diselesaikan
            </h6>
        </div>
        <div class="card-body">
            @forelse($recentTasks as $task)
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                    <div class="d-flex align-items-start flex-grow-1" style="min-width: 0;">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <div style="min-width: 0;">
                            <div class="font-weight-bold text-dark text-truncate" style="font-size: 14px;">
                                {{ $task->title }}
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-folder mr-1"></i>
                                <a href="{{ route('projects.show', $task->project_id) }}" class="text-muted">
                                    {{ $task->project->name ?? '-' }}
                                </a>
                            </small>
                        </div>
                    </div>
                    <small class="text-muted" style="white-space: nowrap;">
                        {{ \Carbon\Carbon::parse($task->completed_at)->format('d M Y') }}
                    </small>
                </div>
            @empty
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-gray-300 mb-2"></i>
                    <p class="text-muted mb-0">Belum ada task yang diselesaikan.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection