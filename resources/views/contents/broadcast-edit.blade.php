@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('contents.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Stock Management
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">
            <i class="fas fa-tags mr-2"></i> Isi Metadata Konten
        </h1>
        <small class="text-muted">
            Kode: <strong>{{ $content->content_code }}</strong> — {{ $content->title }}
        </small>
    </div>
</div>

<form action="{{ route('contents.broadcastMetadata', $content->id) }}" method="POST">
    @csrf
    @method('PATCH')

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Metadata Broadcast</h6>
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
                        <label class="font-weight-bold small">Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control"
                               value="{{ old('category', $content->category) }}" required maxlength="100"
                               placeholder="Contoh: Kajian Rutin, Event, Dakwah Umum">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Content Type <span class="text-danger">*</span></label>
                        <input type="text" name="content_type" class="form-control"
                               value="{{ old('content_type', $content->content_type) }}" required maxlength="100"
                               placeholder="Reels / Feed / Story / Video Panjang / dll">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Platform <span class="text-danger">*</span></label>
                        @php
                            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter/X', 'Website'];
                            $selected = old('platform', $content->platform ?? []);
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

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Caption Final</label>
                        <textarea name="caption" rows="4" class="form-control">{{ old('caption', $content->caption) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Info Konten</h6>
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Project:</strong><br>{{ $content->project->name ?? '-' }}</p>
                    <p class="mb-1"><strong>Upload:</strong><br>{{ $content->created_at->format('d M Y H:i') }}</p>
                    <p class="mb-3"><strong>Status:</strong><br>
                        <span class="badge badge-{{ $content->status === 'Completed' ? 'success' : 'warning' }}">
                            {{ $content->status }}
                        </span>
                    </p>
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-check mr-1"></i> Simpan & Complete
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