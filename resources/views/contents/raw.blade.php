@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-import mr-2"></i> {{ $title }}</h1>
        <div>
            <a href="{{ route('contents.formRaw') }}" class="btn btn-info btn-sm shadow-sm">
                <i class="fas fa-plus mr-1"></i> Upload Raw
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
        Daftar <strong>link file bahan mentah</strong> yang diupload ketua/kepala divisi, siap dipakai tim Editing.
        Kalau bahan sudah diambil untuk diedit, klik <strong>Ambil untuk Editing</strong> supaya tim lain tahu
        (tercatat siapa &amp; kapan).
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Kode</th>
                            <th>Bahan / Link</th>
                            <th>Project</th>
                            <th>Target Divisi</th>
                            <th class="text-center">Status Ambil</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($raws as $r)
                            <tr>
                                <td><span class="badge badge-dark">{{ $r->content_code }}</span></td>
                                <td>
                                    <strong>{{ $r->title }}</strong>
                                    @if ($r->file_url)
                                        <br>
                                        <a href="{{ $r->file_url }}" target="_blank" class="small">
                                            <i class="fas fa-external-link-alt mr-1"></i> Buka file bahan
                                        </a>
                                    @endif
                                    @if ($r->caption)
                                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->caption, 80) }}</small>
                                    @endif
                                </td>
                                <td>{{ $r->project->name ?? '-' }}</td>
                                <td>{{ $r->target_division ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($r->taken_at)
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Sudah diambil</span>
                                        <br><small class="text-muted">
                                            {{ $daftarUser[$r->taken_by] ?? '-' }} &bull; {{ $r->taken_at->format('d M Y H:i') }}
                                        </small>
                                    @else
                                        <span class="badge badge-warning">Belum diambil</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('contents.show', $r->id) }}" class="btn btn-sm btn-info" title="Lihat detail">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>

                                    @if ($bisaAmbil)
                                        @if (!$r->taken_at)
                                            <form action="{{ route('contents.ambil', $r->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary" title="Tandai sudah diambil untuk diedit">
                                                    <i class="fas fa-hand-paper mr-1"></i> Ambil untuk Editing
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('contents.lepas', $r->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Batalkan penanda pengambilan bahan ini?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Batalkan penanda">
                                                    <i class="fas fa-undo mr-1"></i> Batal Ambil
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada bahan raw yang diupload.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
