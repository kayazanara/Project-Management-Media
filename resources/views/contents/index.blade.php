@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-boxes mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('contents.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Tambah Konten Baru
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

    <!-- Statistik Total Stok Aktif (Ready) -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Stok Konten Siap Upload (Ready)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalReadyStock }} Konten</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box-open fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel 1: Konten Aktif (Ready to Upload) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-hourglass-start mr-1"></i> Daftar Konten Siap Upload (Ready)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No ID</th>
                            <th>Judul Konten</th>
                            <th>Project Sumber</th>
                            <th>Tipe Konten</th>
                            <th>Platform Tujuan</th>
                            <th>Link / File</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($readyContents as $index => $content)
                        <tr>
                            <td><strong>{{ $content->content_code }}</strong></td>
                            <td>{{ $content->title }}</td>
                            <td>
                                <a href="{{ route('projects.show', $content->project_id) }}">
                                    {{ $content->project->name ?? '-' }}
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $content->type }}</span>
                            </td>
                            <td>
                                @if(is_array($content->platform))
                                    @foreach($content->platform as $plt)
                                        <span class="badge badge-secondary mb-1">{{ $plt }}</span>
                                    @endforeach
                                @else
                                    <span class="badge badge-secondary">{{ $content->platform }}</span>
                                @endif
                            </td>
                            <td>
                                @if($content->file_url)
                                    <a href="{{ $content->file_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt"></i> Buka Link
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-success">Ready</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <form action="{{ route('contents.status', $content->id) }}" method="POST" class="d-inline mr-1">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="Uploaded">
                                        <button type="submit" class="btn btn-success btn-sm" title="Tandai Sudah Di-upload">
                                            <i class="fas fa-upload"></i> Upload
                                        </button>
                                    </form>
                                    <form action="{{ route('contents.status', $content->id) }}" method="POST" class="d-inline mr-1">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="Archived">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Arsipkan Konten">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('contents.edit', $content->id) }}" class="btn btn-warning btn-sm mr-1" title="Edit Konten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('contents.destroy', $content->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus konten ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada konten dengan status Ready saat ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel 2: Riwayat Konten (Uploaded & Archived - Terpisah dan Tidak Dihitung sebagai Stok) -->
    <div class="card shadow mb-4 border-left-secondary">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-history mr-1"></i> Riwayat Konten (Uploaded & Archived)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No ID</th>
                            <th>Judul Konten</th>
                            <th>Project Sumber</th>
                            <th>Tipe Konten</th>
                            <th>Platform Tujuan</th>
                            <th>Status</th>
                            <th>Tanggal Upload</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historyContents as $index => $history)
                        <tr>
                            <td><strong>{{ $history->content_code }}</strong></td>
                            <td>{{ $history->title }}</td>
                            <td>{{ $history->project->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-info">{{ $history->type }}</span>
                            </td>
                            <td>
                                @if(is_array($history->platform))
                                    @foreach($history->platform as $plt)
                                        <span class="badge badge-secondary mb-1">{{ $plt }}</span>
                                    @endforeach
                                @else
                                    <span class="badge badge-secondary">{{ $history->platform }}</span>
                                @endif
                            </td>
                            <td>
                                @if($history->status == 'Uploaded')
                                    <span class="badge badge-primary">Uploaded</span>
                                @else
                                    <span class="badge badge-dark">Archived</span>
                                @endif
                            </td>
                            <td>{{ $history->uploaded_at ? date('Y-m-d H:i', strtotime($history->uploaded_at)) : '-' }}</td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <form action="{{ route('contents.status', $history->id) }}" method="POST" class="d-inline mr-1">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="Ready">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Kembalikan ke Ready">
                                            <i class="fas fa-undo"></i> Batal / Ready
                                        </button>
                                    </form>
                                    <a href="{{ route('contents.edit', $history->id) }}" class="btn btn-warning btn-sm mr-1" title="Edit Konten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('contents.destroy', $history->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus riwayat konten ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada riwayat konten yang di-upload atau diarsipkan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection