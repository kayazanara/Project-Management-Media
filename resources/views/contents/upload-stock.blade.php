@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('contents.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Stock Management
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">
            <i class="fas fa-boxes mr-2"></i> Upload Content Stock
        </h1>
        <small class="text-muted">Konten siap posting — akan diteruskan ke Broadcast untuk metadata</small>
    </div>
</div>

<form action="{{ route('contents.uploadStock') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Info Konten</h6>
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
                        <label class="font-weight-bold small">Judul Konten <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small">Prefix Kode <span class="text-danger">*</span></label>
                                <select name="category_prefix" class="form-control" required>
                                    <option value="DAK" {{ old('category_prefix') === 'DAK' ? 'selected' : '' }}>DAK — Dakwah</option>
                                    <option value="DOK" {{ old('category_prefix') === 'DOK' ? 'selected' : '' }}>DOK — Dokumentasi</option>
                                    <option value="MEM" {{ old('category_prefix') === 'MEM' ? 'selected' : '' }}>MEM — Media</option>
                                    <option value="PSB" {{ old('category_prefix') === 'PSB' ? 'selected' : '' }}>PSB — Publikasi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small">Tipe Konten <span class="text-danger">*</span></label>
                                <input type="text" name="type" class="form-control" value="{{ old('type') }}" required
                                       placeholder="Video / Poster / Artikel / dll">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Platform <span class="text-danger">*</span></label>
                        @php
                            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter/X', 'Website'];
                            $selected = old('platform', []);
                        @endphp
                        @foreach($platforms as $pf)
                            <div class="custom-control custom-checkbox custom-control-inline">
                                <input type="checkbox" class="custom-control-input" id="pf_{{ $loop->index }}"
                                       name="platform[]" value="{{ $pf }}"
                                       {{ in_array($pf, $selected) ? 'checked' : '' }}>
                                <label class="custom-control-label small" for="pf_{{ $loop->index }}">{{ $pf }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Link File</label>
                        <input type="text" name="file_url" class="form-control" value="{{ old('file_url') }}"
                               placeholder="Google Drive / Dropbox / dll">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Caption Awal (opsional)</label>
                        <textarea name="caption" rows="3" class="form-control">{{ old('caption') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-clock mr-1"></i>
                        Setelah diupload, konten akan masuk ke daftar <strong>Ready</strong> dan menunggu Broadcast mengisi metadata.
                    </div>
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-upload mr-1"></i> Upload & Kirim Notif
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