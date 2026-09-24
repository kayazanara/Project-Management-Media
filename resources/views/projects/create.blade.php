@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-plus-circle mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-exclamation-triangle mr-1"></i> Gagal Menyimpan Project!</strong> Mohon periksa kembali input yang kurang atau salah:
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
            <form action="{{ route('projects.store') }}" method="POST">
                @csrf
                
                <div class="form-group">
                    <label><strong>Nama Project</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Video Dokumentasi Pengajian Akbar" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Orang yang Terlibat (Pilih Anggota Tim)</strong> <span class="text-danger">*</span></label>
                    <div class="border p-3 rounded bg-white @error('user_ids') border-danger @enderror" style="max-height: 180px; overflow-y: auto;">
                        @foreach($users as $user)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ $user->id }}" id="user_{{ $user->id }}" {{ (is_array(old('user_ids')) && in_array($user->id, old('user_ids'))) ? 'checked' : '' }}>
                                <label class="form-check-label" for="user_{{ $user->id }}" style="cursor: pointer; width: 100%;">
                                    {{ $user->name }} <span class="text-muted font-italic">({{ $user->role }} - {{ $user->division ?? 'Tanpa Divisi' }})</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('user_ids')
                        <small class="text-danger font-weight-bold mt-1 d-block">{{ $message }}</small>
                    @else
                        <small class="text-muted">Bisa mencentang lebih dari satu orang.</small>
                    @enderror
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><strong>Tanggal Masuk Project (Start Date)</strong> <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                        @error('start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label><strong>Deadline</strong> <span class="text-danger">*</span></label>
                        <input type="date" name="deadline" class="form-control @error('deadline') is-invalid @enderror" value="{{ old('deadline') }}" required>
                        @error('deadline')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Link Referensi</strong> (Opsional)</label>
                    <input type="text" name="reference_link" class="form-control @error('reference_link') is-invalid @enderror" placeholder="Tuliskan link Google Drive, Figma, dll..." value="{{ old('reference_link') }}">
                    @error('reference_link')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label><strong>Brief Project</strong> (Opsional)</label>
                    <textarea name="brief" class="form-control @error('brief') is-invalid @enderror" rows="3" placeholder="Tuliskan detail brief atau instruksi pengerjaan...">{{ old('brief') }}</textarea>
                    @error('brief')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Project</button>
            </form>
        </div>
    </div>
@endsection