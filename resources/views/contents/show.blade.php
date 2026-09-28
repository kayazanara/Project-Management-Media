@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt mr-2"></i> {{ $title }}</h1>
        <div>
            <a href="{{ $content->upload_type === 'raw' ? route('contents.raw') : route('contents.activity') }}"
               class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        {{ $content->title }}
                        <span class="badge badge-dark ml-2">{{ $content->content_code }}</span>
                        <span class="badge badge-{{ $content->upload_type === 'raw' ? 'info' : 'warning' }} ml-1">
                            {{ ucfirst($content->upload_type) }}
                        </span>
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th style="width: 180px;">Project</th><td>{{ $content->project->name ?? '-' }}</td></tr>
                        <tr><th>Tipe</th><td>{{ $content->type ?? '-' }}</td></tr>
                        <tr>
                            <th>Platform</th>
                            <td>
                                @php $plat = is_array($content->platform) ? $content->platform : []; @endphp
                                @forelse ($plat as $p)
                                    <span class="badge badge-light border">{{ $p }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                        </tr>
                        @if ($content->upload_type === 'raw')
                            <tr><th>Target Divisi</th><td>{{ $content->target_division ?? '-' }}</td></tr>
                        @endif
                        <tr><th>Status</th><td><span class="badge badge-secondary">{{ $content->status }}</span></td></tr>
                        <tr>
                            <th>Link File</th>
                            <td>
                                @if ($content->file_url)
                                    <a href="{{ $content->file_url }}" target="_blank">{{ $content->file_url }}</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Caption / Deskripsi</th>
                            <td>{!! nl2br(e($content->caption ?? '-')) !!}</td>
                        </tr>
                        <tr><th>Dibuat</th><td>{{ $content->created_at ? $content->created_at->format('d M Y H:i') : '-' }}</td></tr>
                    </table>

                    <a href="{{ $content->file_url }}" target="_blank" class="btn btn-info btn-sm {{ $content->file_url ? '' : 'disabled' }}">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka File
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if ($content->upload_type === 'raw')
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pengambilan Bahan</h6>
                    </div>
                    <div class="card-body">
                        @if ($content->taken_at)
                            <p class="mb-1">
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Sudah diambil</span>
                            </p>
                            <p class="small text-muted mb-3">
                                Oleh: <strong>{{ $pengambil->name ?? '-' }}</strong><br>
                                Waktu: {{ $content->taken_at->format('d M Y H:i') }}
                            </p>
                        @else
                            <p><span class="badge badge-warning">Belum diambil</span></p>
                            <p class="small text-muted">Tandai kalau bahan ini sudah diambil untuk diedit.</p>
                        @endif

                        @if ($bisaAmbil)
                            @if (!$content->taken_at)
                                <form action="{{ route('contents.ambil', $content->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                                        <i class="fas fa-hand-paper mr-1"></i> Ambil untuk Editing
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('contents.lepas', $content->id) }}" method="POST"
                                      onsubmit="return confirm('Batalkan penanda pengambilan?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm btn-block">
                                        <i class="fas fa-undo mr-1"></i> Batal Ambil
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Aksi</h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('contents.edit', $content->id) }}" class="btn btn-warning btn-sm btn-block">
                        <i class="fas fa-edit mr-1"></i> Edit Konten
                    </a>
                    <small class="text-muted d-block mt-2">
                        Tombol Edit mengikuti hak akses: Kepala Divisi Broadcast, Ketua Media &amp; Pembina.
                    </small>
                </div>
            </div>
        </div>
    </div>
@endsection
