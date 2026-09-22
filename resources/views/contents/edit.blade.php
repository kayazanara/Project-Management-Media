@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-plus-circle mr-2"></i> {{ $title }}</h1>
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
            <h6 class="m-0 font-weight-bold text-primary">Form Entri Konten Baru</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('contents.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label><strong>Pilih Kategori ID Konten</strong> <span class="text-danger">*</span></label>
                    <select name="category_prefix" class="form-control" required>
                        <option value="">-- Pilih Jenis / Kategori ID --</option>
                        <option value="DAK">DAK (Konten Dakwah)</option>
                        <option value="DOK">DOK (Dokumentasi)</option>
                        <option value="MEM">MEM (Meme)</option>
                        <option value="PSB">PSB (Promosi / PSB)</option>
                    </select>
                    <small class="text-muted">ID unik (seperti DAK01, DOK01, dst.) akan digenerate secara otomatis oleh sistem berdasarkan kategori ini.</small>
                </div>

                <div class="form-group">
                    <label><strong>Pilih Project Sumber</strong> <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-control" required>
                        <option value="">-- Pilih Project Asal Konten --</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}">{{ $proj->name }} (Deadline: {{ $proj->deadline }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Judul Konten</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Video Reels PSB Pendaftaran Santri Baru" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tipe Konten</strong> <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="">-- Pilih Tipe --</option>
                                <option value="Reels">Reels</option>
                                <option value="Poster">Poster</option>
                                <option value="Feed">Feed</option>
                                <option value="Carousel">Carousel</option>
                                <option value="Story">Story</option>
                                <option value="Video">Video</option>
                                <option value="Photo">Photo</option>
                                <option value="Announcement">Announcement</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Platform Tujuan (Pilih minimal satu)</strong> <span class="text-danger">*</span></label>
                            <div class="border p-3 rounded bg-light" style="max-height: 140px; overflow-y: auto;">
                                @php
                                    $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'WhatsApp Grup', 'WhatsApp Channel', 'Website', 'Other'];
                                @endphp
                                @foreach($platforms as $plt)
                                    <div class="custom-control custom-checkbox mb-1">
                                        <input type="checkbox" name="platform[]" value="{{ $plt }}" class="custom-control-input" id="plat_{{ $loop->index }}">
                                        <label class="custom-control-label" for="plat_{{ $loop->index }}">{{ $plt }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Link File / URL Aset Konten</strong></label>
                    <input type="url" name="file_url" class="form-control" placeholder="https://drive.google.com/...">
                </div>

                <div class="form-group">
                    <label><strong>Caption / Teks Postingan</strong></label>
                    <textarea name="caption" class="form-control" rows="3" placeholder="Tuliskan caption atau deskripsi konten..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-save mr-1"></i> Simpan Konten
                </button>
                <a href="{{ route('contents.index') }}" class="btn btn-secondary btn-sm shadow-sm">Batal</a>
            </form>
        </div>
    </div>
@endsection