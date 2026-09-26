@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('contents.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Stock Management
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">
            <i class="fas fa-file-upload mr-2"></i> Upload Bahan Mentah (Raw)
        </h1>
        <small class="text-muted">Bahan ini akan diteruskan ke divisi target untuk diproses lanjut</small>
    </div>
</div>

<form action="{{ route('contents.uploadRaw') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Info Raw</h6>
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
                                <option value="{{ $p->id }}" {{ old('project_id', $defaultProjectId) == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Judul / Nama File <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255"
                               placeholder="Contoh: Hasil shooting sesi 1">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Link File Raw <span class="text-danger">*</span></label>
                        <input type="text" name="file_url" class="form-control" value="{{ old('file_url') }}" required
                               placeholder="Google Drive / Dropbox / WeTransfer / dll">
                        <small class="text-muted">Sementara pakai link (belum upload file langsung).</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Target Divisi <span class="text-danger">*</span></label>
                        <select name="target_division" class="form-control" required>
                            <option value="Editing"    {{ old('target_division', 'Editing') === 'Editing'    ? 'selected' : '' }}>Editing</option>
                            <option value="Broadcast"  {{ old('target_division') === 'Broadcast'  ? 'selected' : '' }}>Broadcast</option>
                            <option value="AI Research"{{ old('target_division') === 'AI Research'? 'selected' : '' }}>AI Research</option>
                            <option value="Lapangan"   {{ old('target_division') === 'Lapangan'   ? 'selected' : '' }}>Lapangan</option>
                        </select>
                        <small class="text-muted">Divisi ini akan menerima notifikasi WA.</small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Catatan (opsional)</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Keterangan tambahan...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Raw bisa diupload <strong>kapan saja</strong>, tidak perlu menunggu tahap Upload project.
                    </div>
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Upload & Kirim Notif
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