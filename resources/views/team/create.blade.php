@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-plus mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('team.index') }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-exclamation-triangle mr-1"></i> Gagal Menyimpan Anggota!</strong> Mohon periksa kembali input berikut:
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('team.store') }}" method="POST">
                @csrf
                
                <div class="form-group">
                    <label><strong>Nama Lengkap</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required value="{{ old('name') }}">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Email</strong> <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" required value="{{ old('email') }}">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Password</strong> <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Role / Peran</strong> <span class="text-danger">*</span></label>
                    <select name="role" class="form-control @error('role') is-invalid @enderror" required>
                        <option value="" disabled selected>-- Pilih Role --</option>
                        <option value="Ustadz" {{ old('role') == 'Ustadz' ? 'selected' : '' }}>Ustadz</option>
                        <option value="Santri" {{ old('role') == 'Santri' ? 'selected' : '' }}>Santri</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Divisi / Posisi</strong> <span class="text-danger">*</span></label>
                    <select name="division" class="form-control @error('division') is-invalid @enderror" required>
                        <option value="" disabled selected>-- Pilih Divisi / Posisi --</option>
                        <option value="Pembina Media" {{ old('division') == 'Pembina Media' ? 'selected' : '' }}>Pembina Media</option>
                        <option value="Ketua Media" {{ old('division') == 'Ketua Media' ? 'selected' : '' }}>Ketua Media</option>
                        <option value="Lapangan" {{ old('division') == 'Lapangan' ? 'selected' : '' }}>Lapangan</option>
                        <option value="Editing" {{ old('division') == 'Editing' ? 'selected' : '' }}>Editing</option>
                        <option value="Broadcast" {{ old('division') == 'Broadcast' ? 'selected' : '' }}>Broadcast</option>
                        <option value="AI Research" {{ old('division') == 'AI Research' ? 'selected' : '' }}>AI Research</option>
                    </select>
                    @error('division')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Status</strong> <span class="text-danger">*</span></label>
                    <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                        <option value="Aktif" {{ old('status') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Non-Aktif" {{ old('status') == 'Non-Aktif' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Anggota</button>
            </form>
        </div>
    </div>
@endsection
