@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('team.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-user-plus fa-sm text-white-50 mr-1"></i> Tambah Anggota Tim
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

    <!-- Statistik Role -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Anggota</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }} Orang</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Manager</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <i class="fas fa-shield-alt"></i> {{ $stats['pembina'] }} Pembina<br>
                                <i class="fas fa-crown"></i> {{ $stats['ketua_media'] }} Ketua Media
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-tie fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Kepala Divisi</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['kepala_divisi'] }} Orang</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-cog fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Anggota</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['anggota'] }} Orang</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Anggota -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Anggota Tim Media</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. WhatsApp</th>
                            <th>Role</th>
                            <th>Divisi</th>
                            <th>Peran Struktural</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $index => $user)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($user->phone)
                                    <a href="https://wa.me/{{ $user->phone }}" target="_blank" class="text-success">
                                        <i class="fab fa-whatsapp mr-1"></i>{{ $user->phone }}
                                    </a>
                                @else
                                    <span class="text-muted font-italic"><i class="fas fa-minus-circle mr-1"></i>Belum diisi</span>
                                @endif
                            </td>
                            <td>
                                @if($user->role == 'Ustadz')
                                    <span class="badge badge-success">Ustadz</span>
                                @else
                                    <span class="badge badge-primary">Santri</span>
                                @endif
                            </td>
                            <td>{{ $user->division ?? '-' }}</td>
                            <td>
                                @switch($user->division_role)
                                    @case('pembina')
                                        <span class="badge badge-danger">
                                            <i class="fas fa-shield-alt mr-1"></i>Pembina Media
                                        </span>
                                        @break
                                    @case('ketua_media')
                                        <span class="badge badge-warning">
                                            <i class="fas fa-crown mr-1"></i>Ketua Media
                                        </span>
                                        @break
                                    @case('kepala_divisi')
                                        <span class="badge badge-info">
                                            <i class="fas fa-user-cog mr-1"></i>Kepala Divisi
                                        </span>
                                        @break
                                    @default
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-user mr-1"></i>Anggota
                                        </span>
                                @endswitch
                            </td>
                            <td>
                                @if($user->status == 'Aktif')
                                    <span class="badge badge-success">Aktif</span>
                                @else
                                    <span class="badge badge-secondary">Non-Aktif</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('team.edit', $user->id) }}" class="btn btn-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('team.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus anggota ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection