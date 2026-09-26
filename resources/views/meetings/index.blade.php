@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-calendar-alt mr-2"></i> Rapat & Notulen
        </h1>
        <small class="text-muted">Kelola catatan rapat, keputusan, dan action items</small>
    </div>
    @if(auth()->user()->isManager())
        <a href="{{ route('meetings.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus mr-1"></i> Buat Rapat
        </a>
    @endif
</div>

{{-- Filter --}}
<div class="card shadow mb-4">
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-4 mb-2">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari judul / agenda / lokasi..."
                       value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-md-3 mb-2">
                <select name="status" class="form-control form-control-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="published" {{ ($filters['status'] ?? '') === 'published' ? 'selected' : '' }}>Published</option>
                    <option value="draft"     {{ ($filters['status'] ?? '') === 'draft'     ? 'selected' : '' }}>Draft</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <input type="month" name="month" class="form-control form-control-sm"
                       value="{{ $filters['month'] ?? '' }}">
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-primary btn-sm btn-block">
                    <i class="fas fa-search mr-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- List --}}
<div class="card shadow mb-4">
    <div class="card-body p-0">
        @forelse($meetings as $m)
            <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge badge-{{ $m->status === 'published' ? 'success' : 'secondary' }} mr-2">
                            {{ ucfirst($m->status) }}
                        </span>
                        <h6 class="mb-0 font-weight-bold text-dark text-truncate">{{ $m->title }}</h6>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-calendar mr-1"></i>{{ $m->date_formatted }} •
                        <i class="fas fa-clock ml-1 mr-1"></i>{{ substr($m->start_time, 0, 5) }} - {{ substr($m->end_time, 0, 5) }} •
                        <i class="fas fa-map-marker-alt ml-1 mr-1"></i>{{ $m->location ?? '-' }}
                    </div>
                    @if($m->agenda)
                        <div class="small text-muted mt-1 text-truncate">
                            <i class="fas fa-list mr-1"></i>{{ Str::limit($m->agenda, 120) }}
                        </div>
                    @endif
                    <div class="mt-1">
                        <span class="badge badge-info">{{ count($m->decisions ?? []) }} keputusan</span>
                        <span class="badge badge-primary">{{ $m->completed_action_items }}/{{ $m->total_action_items }} action</span>
                        <span class="badge badge-light">{{ count($m->attendees ?? []) }} peserta</span>
                    </div>
                </div>
                <div class="ml-3 text-right">
                    <a href="{{ route('meetings.show', $m->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye mr-1"></i> Detail
                    </a>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-gray-300 mb-3"></i>
                <h5 class="text-muted">Belum ada rapat</h5>
                @if(auth()->user()->isManager())
                    <a href="{{ route('meetings.create') }}" class="btn btn-primary btn-sm mt-2">
                        <i class="fas fa-plus mr-1"></i> Buat Rapat Pertama
                    </a>
                @endif
            </div>
        @endforelse
    </div>
    @if($meetings->hasPages())
        <div class="card-footer bg-white">
            {{ $meetings->links() }}
        </div>
    @endif
</div>
@endsection