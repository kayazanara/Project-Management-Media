@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('contents.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Stock Management
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">
            <i class="fas fa-video mr-2"></i> Upload Activity
        </h1>
        <small class="text-muted">Kegiatan / event / live — langsung ditandai Complete</small>
    </div>
</div>

<form action="{{ route('contents.uploadActivity') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Info Activity</h6>
                </div>
                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="font-weight-bold small">Project <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-control" required>
                            <option value="">-- Pilih Project --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Judul / Nama Kegiatan <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255"
                               placeholder="Contoh: Live Kajian Rutin Ahad">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Link File / Dokumentasi</label>
                        <input type="text" name="file_url" class="form-control" value="{{ old('file_url') }}"
                               placeholder="Google Drive / YouTube / dll">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Deskripsi / Catatan</label>
                        <textarea name="caption" rows="3" class="form-control">{{ old('caption') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="alert alert-success small mb-3">
                        <i class="fas fa-check-circle mr-1"></i>
                        Activity akan langsung ditandai <strong>Completed</strong> setelah submit. Tidak perlu metadata.
                    </div>
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-upload mr-1"></i> Upload & Complete
                    </button>
                    <a href="{{ route('contents.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection