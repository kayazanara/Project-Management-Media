@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-running mr-2"></i> {{ $title }}</h1>
        <div>
            <a href="{{ route('contents.uploadActivity') }}" class="btn btn-warning btn-sm shadow-sm">
                <i class="fas fa-plus mr-1"></i> Upload Activity
            </a>
            <a href="{{ route('contents.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-boxes mr-1"></i> Stock Management
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-light border small mb-3">
        Riwayat laporan <strong>Activity</strong> yang diinput oleh ketua/kepala divisi.
        Di halaman ini kamu bisa <strong>Lihat</strong> dan <strong>Edit</strong> setiap activity.
        @if (!$bisaEdit)
            <br><small class="text-muted">Kamu hanya bisa melihat. Tombol Edit terbatas untuk Kepala Divisi Broadcast, Ketua Media &amp; Pembina.</small>
        @endif
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Judul / Laporan</th>
                            <th>Project</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $a)
                            <tr>
                                <td><span class="badge badge-dark">{{ $a->content_code }}</span></td>
                                <td><small>{{ $a->created_at ? $a->created_at->format('d M Y') : '-' }}</small></td>
                                <td>
                                    <strong>{{ $a->title }}</strong>
                                    @if ($a->caption)
                                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($a->caption, 100) }}</small>
                                    @endif
                                </td>
                                <td>{{ $a->project->name ?? '-' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('contents.show', $a->id) }}" class="btn btn-sm btn-info" title="Lihat detail">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                    @if ($bisaEditPerId[$a->id] ?? false)
                                        <a href="{{ route('contents.edit', $a->id) }}" class="btn btn-sm btn-warning" title="Edit activity">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada activity yang diupload.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
