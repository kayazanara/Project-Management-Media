@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-boxes mr-2"></i> Stock Management
        </h1>
        <small class="text-muted">Kelola 3 jenis konten: Raw, Content Stock, Activity</small>
    </div>
    <div>
        @if(auth()->user()->isBroadcast() || auth()->user()->isManager() || auth()->user()->isKepalaDivisi())
            <a href="{{ route('contents.formRaw') }}" class="btn btn-sm btn-outline-info shadow-sm">
                <i class="fas fa-file-upload mr-1"></i> Raw
            </a>
            <a href="{{ route('contents.formActivity') }}" class="btn btn-sm btn-outline-warning shadow-sm">
                <i class="fas fa-video mr-1"></i> Activity
            </a>
        @endif
        @if(auth()->user()->isManager() || auth()->user()->isBroadcast())
            <a href="{{ route('contents.formStock') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-boxes mr-1"></i> Stock
            </a>
        @endif
    </div>
</div>

{{-- STATS --}}
<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Ready</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_ready'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Raw</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_raw'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Stock</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_stock'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Butuh Metadata</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['waiting_meta'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER --}}
<div class="card shadow mb-4">
    <div class="card-body py-2">
        <form method="GET" class="form-inline">
            <label class="small text-muted mr-2">Filter Tipe:</label>
            <select name="type" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="raw"           {{ $filterType === 'raw'           ? 'selected' : '' }}>Raw</option>
                <option value="content_stock" {{ $filterType === 'content_stock' ? 'selected' : '' }}>Content Stock</option>
                <option value="activity"      {{ $filterType === 'activity'      ? 'selected' : '' }}>Activity</option>
            </select>
            @if($filterType)
                <a href="{{ route('contents.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- READY CONTENT --}}
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-fire mr-1"></i> Konten Aktif ({{ $readyContents->count() }})
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Tipe</th>
                        <th>Project</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($readyContents as $c)
                        <tr>
                            <td><strong>{{ $c->content_code }}</strong></td>
                            <td>{{ $c->title }}</td>
                            <td>
                                @if($c->upload_type === 'raw')
                                    <span class="badge badge-info">Raw</span>
                                @elseif($c->upload_type === 'activity')
                                    <span class="badge badge-warning">Activity</span>
                                @else
                                    <span class="badge badge-success">Stock</span>
                                @endif
                            </td>
                            <td>{{ $c->project->name ?? '-' }}</td>
                            <td>{{ $c->target_division ?? '-' }}</td>
                            <td>
                                @if($c->upload_type === 'content_stock' && !$c->broadcast_filled_at)
                                    <span class="badge badge-warning">Butuh Metadata</span>
                                @else
                                    <span class="badge badge-{{ $c->status === 'Completed' ? 'success' : 'primary' }}">
                                        {{ $c->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($c->upload_type === 'content_stock' && !$c->broadcast_filled_at && (auth()->user()->isBroadcast() || auth()->user()->isManager()))
                                    <a href="{{ route('contents.formBroadcastMetadata', $c->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-tags mr-1"></i> Isi Metadata
                                    </a>
                                @endif
                                @if($c->file_url)
                                    <a href="{{ $c->file_url }}" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada konten aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- HISTORY --}}
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-secondary">
            <i class="fas fa-history mr-1"></i> Riwayat Konten ({{ $historyContents->count() }})
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Tipe</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Diupdate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historyContents as $c)
                        <tr>
                            <td>{{ $c->content_code }}</td>
                            <td>{{ $c->title }}</td>
                            <td>
                                <span class="badge badge-light">
                                    {{ $c->upload_type ?? 'content_stock' }}
                                </span>
                            </td>
                            <td>{{ $c->project->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $c->status === 'Completed' ? 'success' : 'secondary' }}">
                                    {{ $c->status }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $c->updated_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Belum ada riwayat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection