@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('contents.index') }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Edit Konten <span class="badge badge-primary">{{ $content->content_code }}</span>
            </h6>
        </div>
        <div class="card-body">
            {{-- ⚡ FIX (S1): sebelumnya form ini mengarah ke contents.store (MEMBUAT konten BARU,
                 bukan mengedit) dan tidak memuat data lama sama sekali. Sekarang benar-benar
                 mengirim ke contents.update dengan semua nilai lama sudah terisi. --}}
            <form action="{{ route('contents.update', $content->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label><strong>ID Konten</strong></label>
                    <input type="text" class="form-control" value="{{ $content->content_code }}" readonly>
                    <small class="text-muted">Kode ini dibuat otomatis saat konten pertama dibuat dan tidak berubah.</small>
                </div>

                <div class="form-group">
                    <label><strong>Project Sumber</strong> <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-control" required>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" {{ (int) old('project_id', $content->project_id) === (int) $proj->id ? 'selected' : '' }}>
                                {{ $proj->name }}{{ $proj->deadline ? ' (Deadline: ' . $proj->deadline . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Judul Konten</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $content->title) }}" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tipe Konten</strong></label>
                            <select name="type" class="form-control">
                                <option value="">-- Pilih Tipe --</option>
                                @foreach(['Reels', 'Poster', 'Feed', 'Carousel', 'Story', 'Video', 'Photo', 'Announcement', 'Other'] as $tipe)
                                    <option value="{{ $tipe }}" {{ old('type', $content->type) === $tipe ? 'selected' : '' }}>{{ $tipe }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Platform Tujuan</strong></label>
                            <div class="border p-3 rounded bg-light" style="max-height: 140px; overflow-y: auto;">
                                @php
                                    $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'WhatsApp Grup', 'WhatsApp Channel', 'Website', 'Other'];
                                    $platformLama = is_array($content->platform) ? $content->platform : [];
                                    $platformTerpilih = old('platform', $platformLama);
                                @endphp
                                @foreach($platforms as $plt)
                                    <div class="custom-control custom-checkbox mb-1">
                                        <input type="checkbox" name="platform[]" value="{{ $plt }}" class="custom-control-input"
                                               id="plat_{{ $loop->index }}"
                                               {{ in_array($plt, $platformTerpilih, true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="plat_{{ $loop->index }}">{{ $plt }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Kalau tidak ada yang dicentang, platform lama tetap disimpan.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Link File / URL Aset Konten</strong></label>
                    <input type="url" name="file_url" class="form-control" placeholder="https://drive.google.com/..."
                           value="{{ old('file_url', $content->file_url) }}">
                    <small class="text-muted">Kalau dikosongkan, link lama tetap disimpan (tidak akan hilang).</small>
                </div>

                <div class="form-group">
                    <label><strong>Caption / Teks Postingan</strong></label>
                    <textarea name="caption" class="form-control" rows="3"
                              placeholder="Tuliskan caption atau deskripsi konten...">{{ old('caption', $content->caption) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
                <a href="{{ route('contents.index') }}" class="btn btn-secondary btn-sm shadow-sm">Batal</a>
            </form>
        </div>
    </div>
@endsection
