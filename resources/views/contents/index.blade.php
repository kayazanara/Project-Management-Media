@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-boxes mr-2"></i> {{ $title }}</h1>
        <div>
            <a href="{{ route('contents.formStock') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-upload mr-1"></i> Upload Stock
            </a>
            <a href="{{ route('contents.raw') }}" class="btn btn-info btn-sm shadow-sm">
                <i class="fas fa-file-import mr-1"></i> Bahan Raw
            </a>
            <a href="{{ route('contents.activity') }}" class="btn btn-warning btn-sm shadow-sm">
                <i class="fas fa-running mr-1"></i> Activity
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Kartu angka: basisnya SAMA dengan daftar di bawah --}}
    <div class="row mb-3">
        @php
            $kartu = [
                ['label' => 'Butuh Metadata', 'nilai' => $stats['butuh_metadata'], 'warna' => 'warning', 'icon' => 'fa-tags'],
                ['label' => 'Siap Diposting', 'nilai' => $stats['siap_posting'], 'warna' => 'primary', 'icon' => 'fa-check-circle'],
                ['label' => 'Sudah Diposting', 'nilai' => $stats['sudah_diposting'], 'warna' => 'success', 'icon' => 'fa-paper-plane'],
                ['label' => 'Arsip', 'nilai' => $stats['arsip'], 'warna' => 'secondary', 'icon' => 'fa-archive'],
            ];
        @endphp

        @foreach ($kartu as $k)
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card border-left-{{ $k['warna'] }} shadow h-100 py-2">
                    <div class="card-body py-2">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-{{ $k['warna'] }} text-uppercase mb-1">{{ $k['label'] }}</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $k['nilai'] }}</div>
                            </div>
                            <div class="col-auto"><i class="fas {{ $k['icon'] }} fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Stok Konten
                <span class="badge badge-light border ml-2">Total stok: {{ $stats['total_stock'] }}</span>
            </h6>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab-butuh">
                        <i class="fas fa-tags mr-1"></i> Butuh Metadata
                        <span class="badge badge-warning ml-1">{{ $stats['butuh_metadata'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-siap">
                        <i class="fas fa-check-circle mr-1"></i> Siap Diposting
                        <span class="badge badge-primary ml-1">{{ $stats['siap_posting'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-posted">
                        <i class="fas fa-paper-plane mr-1"></i> Sudah Diposting
                        <span class="badge badge-success ml-1">{{ $stats['sudah_diposting'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-arsip">
                        <i class="fas fa-archive mr-1"></i> Arsip
                        <span class="badge badge-secondary ml-1">{{ $stats['arsip'] }}</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                @foreach ([
                    ['id' => 'tab-butuh', 'data' => $butuhMetadata, 'aksi' => 'metadata'],
                    ['id' => 'tab-siap', 'data' => $siapPosting, 'aksi' => 'post'],
                    ['id' => 'tab-posted', 'data' => $sudahDiposting, 'aksi' => 'unpost'],
                    ['id' => 'tab-arsip', 'data' => $arsip, 'aksi' => 'arsip'],
                ] as $tab)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $tab['id'] }}">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Kode</th>
                                        <th>Judul</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th class="text-center">Diposting</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tab['data'] as $c)
                                        <tr>
                                            <td><span class="badge badge-dark">{{ $c->content_code }}</span></td>
                                            <td>
                                                <strong>{{ $c->title }}</strong>
                                                @if ($c->caption)
                                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($c->caption, 60) }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $c->project->name ?? '-' }}</td>
                                            <td>
                                                @if (!$c->broadcast_filled_at)
                                                    <span class="badge badge-warning"><i class="fas fa-tags mr-1"></i>Butuh Metadata</span>
                                                @elseif ($c->status === 'Uploaded')
                                                    <span class="badge badge-success"><i class="fas fa-paper-plane mr-1"></i>Sudah Diposting</span>
                                                @elseif ($c->status === 'Archived')
                                                    <span class="badge badge-secondary">Arsip</span>
                                                @else
                                                    <span class="badge badge-primary"><i class="fas fa-check mr-1"></i>Ready (siap posting)</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <small>{{ $c->uploaded_at ? $c->uploaded_at->format('d M Y H:i') : '-' }}</small>
                                            </td>
                                            <td class="text-right">
                                                @if ($c->file_url)
                                                    <a href="{{ $c->file_url }}" target="_blank" class="btn btn-sm btn-outline-info" title="Buka File">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                @endif

                                                @if ($tab['aksi'] === 'metadata' && $bisaKelola)
                                                    <a href="{{ route('contents.formBroadcastMetadata', $c->id) }}" class="btn btn-sm btn-primary" title="Isi Metadata">
                                                        <i class="fas fa-tags mr-1"></i> Isi Metadata
                                                    </a>
                                                @endif

                                                @if ($tab['aksi'] === 'post' && $bisaKelola)
                                                    <form action="{{ route('contents.post', $c->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Tandai sudah diposting">
                                                            <i class="fas fa-paper-plane mr-1"></i> POST
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($tab['aksi'] === 'unpost' && $bisaKelola)
                                                    <form action="{{ route('contents.unpost', $c->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Batalkan posting konten ini? Status kembali ke Ready.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Batalkan posting">
                                                            <i class="fas fa-undo mr-1"></i> Batal Posting
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($bisaKelola)
                                                    <a href="{{ route('contents.edit', $c->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('contents.destroy', $c->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Yakin menghapus konten ini? Tidak bisa dibatalkan.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Tidak ada konten di bagian ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
