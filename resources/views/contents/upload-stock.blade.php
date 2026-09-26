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
        <small class="text-muted">
            Konten siap posting — akan diteruskan ke Broadcast untuk metadata
        </small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <h6 class="font-weight-bold mb-2">
            <i class="fas fa-exclamation-triangle mr-1"></i> Gagal Upload:
        </h6>
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('contents.uploadStock') }}" method="POST" id="uploadForm">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            {{-- Project Selector --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-folder-open mr-1"></i> Project
                    </h6>
                </div>
                <div class="card-body">
                    <select name="project_id" class="form-control" required>
                        <option value="">-- Pilih Project --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id', $defaultProjectId) == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        @if(auth()->user()->isManager())
                            <i class="fas fa-info-circle mr-1"></i>Menampilkan semua project aktif.
                        @else
                            <i class="fas fa-info-circle mr-1"></i>Menampilkan project yang melibatkan divisi
                            <strong>{{ auth()->user()->division ?? '-' }}</strong>.
                        @endif
                    </small>
                </div>
            </div>

            {{-- Daftar Konten (Multi-Row) --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list mr-1"></i> Daftar Konten
                        <span class="badge badge-primary ml-1" id="rowCount">0</span>
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">
                        <i class="fas fa-plus mr-1"></i> Tambah Konten
                    </button>
                </div>
                <div class="card-body" id="itemsContainer">
                    {{-- Rows di-inject oleh JS --}}
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4" style="position: sticky; top: 80px;">
                <div class="card-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-clock mr-1"></i>
                        Setelah diupload, konten masuk ke daftar <strong>Ready</strong>
                        dan menunggu Broadcast mengisi metadata.
                    </div>
                    <div class="alert alert-info small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Bisa upload <strong>lebih dari 1 konten</strong> sekaligus dengan klik "Tambah Konten".
                    </div>

                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-upload mr-1"></i> Upload Semua & Kirim Notif
                    </button>
                    <a href="{{ route('contents.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Template Row (hidden) --}}
<template id="rowTemplate">
    <div class="item-row border rounded p-3 mb-3" style="background: #f8f9fc;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 font-weight-bold text-primary">
                <i class="fas fa-image mr-1"></i> Konten <span class="row-number"></span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Hapus konten ini">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="form-group mb-2">
            <label class="small font-weight-bold">
                Judul Konten <span class="text-danger">*</span>
            </label>
            <input type="text" name="items[__INDEX__][title]" class="form-control form-control-sm" required
                   placeholder="Contoh: Reels opening video profil pondok">
        </div>

        <div class="form-group mb-2">
            <label class="small font-weight-bold">
                Link File <span class="text-danger">*</span>
            </label>
            <input type="text" name="items[__INDEX__][file_url]" class="form-control form-control-sm" required
                   placeholder="Google Drive / Dropbox / WeTransfer">
        </div>

        <div class="form-group mb-0">
            <label class="small font-weight-bold">
                Prefix Kode <span class="text-danger">*</span>
            </label>
            <select name="items[__INDEX__][prefix]" class="form-control form-control-sm" required>
                <option value="DAK">DAK — Dakwah</option>
                <option value="DOK">DOK — Dokumentasi</option>
                <option value="MEM" selected>MEM — Meme</option>
                <option value="PSB">PSB — Publikasi</option>
            </select>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('itemsContainer');
    const template = document.getElementById('rowTemplate');
    const rowCountBadge = document.getElementById('rowCount');
    let rowIndex = 0;

    function updateCount() {
        rowCountBadge.textContent = container.children.length;
    }

    function renumber() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach(function (r, i) {
            r.querySelector('.row-number').textContent = '#' + (i + 1);
        });
        updateCount();
    }

    function addRow() {
        const html = template.innerHTML.replace(/__INDEX__/g, rowIndex);
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        const row = div.firstElementChild;

        row.querySelector('.remove-row').addEventListener('click', function () {
            if (container.children.length <= 1) {
                alert('Minimal 1 konten harus diisi.');
                return;
            }
            row.remove();
            renumber();
        });

        container.appendChild(row);
        rowIndex++;
        renumber();
    }

    // Tambah 1 baris otomatis saat pertama kali buka
    addRow();

    document.getElementById('addRow').addEventListener('click', addRow);
});
</script>
@endsection