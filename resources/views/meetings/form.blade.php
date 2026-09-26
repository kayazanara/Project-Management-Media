@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('meetings.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">
            <i class="fas fa-{{ $mode === 'create' ? 'plus' : 'edit' }} mr-2"></i>
            {{ $title }}
        </h1>
    </div>
</div>

<form action="{{ $mode === 'create' ? route('meetings.store') : route('meetings.update', $meeting->id) }}"
      method="POST">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="row">
        <div class="col-lg-8">
            {{-- Info Dasar --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Rapat</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="font-weight-bold small">Judul Rapat <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $meeting->title) }}" required maxlength="255">
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" name="meeting_date" class="form-control @error('meeting_date') is-invalid @enderror"
                                       value="{{ old('meeting_date', $meeting->meeting_date ? \Carbon\Carbon::parse($meeting->meeting_date)->toDateString() : '') }}" required>
                                @error('meeting_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-bold small">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror"
                                       value="{{ old('start_time', substr($meeting->start_time ?? '08:00', 0, 5)) }}" required>
                                @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-bold small">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror"
                                       value="{{ old('end_time', substr($meeting->end_time ?? '10:00', 0, 5)) }}" required>
                                @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Lokasi</label>
                        <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                               value="{{ old('location', $meeting->location) }}" maxlength="255" placeholder="Ruang Media / Zoom / ...">
                        @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Agenda</label>
                        <textarea name="agenda" rows="3" class="form-control @error('agenda') is-invalid @enderror"
                                  placeholder="1. Pembukaan&#10;2. Pembahasan...">{{ old('agenda', $meeting->agenda) }}</textarea>
                        @error('agenda') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Pembahasan --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pembahasan</h6>
                </div>
                <div class="card-body">
                    <div id="discussionEditor" style="min-height: 200px; background: #fff;">
                        {!! old('discussion', $meeting->discussion ?? '') !!}
                    </div>
                    <input type="hidden" name="discussion" id="discussionInput"
                           value="{{ old('discussion', $meeting->discussion ?? '') }}">
                </div>
            </div>

            {{-- Keputusan --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-gavel mr-1"></i> Keputusan
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" id="addDecision">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                </div>
                <div class="card-body" id="decisionsContainer">
                    @php $decisions = old('decisions', $meeting->decisions ?? []); @endphp
                    @forelse($decisions as $i => $d)
                        <div class="input-group mb-2 decision-row">
                            <input type="text" name="decisions[{{ $i }}][decision]"
                                   class="form-control" value="{{ $d['decision'] ?? '' }}"
                                   placeholder="Isi keputusan...">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-danger remove-decision">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            {{-- Action Items --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tasks mr-1"></i> Action Items
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addAction">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                </div>
                <div class="card-body" id="actionsContainer">
                    @php $actions = old('action_items', $meeting->action_items ?? []); @endphp
                    @forelse($actions as $i => $a)
                        <div class="border rounded p-2 mb-2 action-row">
                            <div class="form-group mb-2">
                                <input type="text" name="action_items[{{ $i }}][task]"
                                       class="form-control form-control-sm"
                                       value="{{ $a['task'] ?? '' }}" placeholder="Deskripsi tugas...">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <select name="action_items[{{ $i }}][assigned_to]" class="form-control form-control-sm">
                                        <option value="">-- PIC --</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ ($a['assigned_to'] ?? '') == $u->id ? 'selected' : '' }}>
                                                {{ $u->name }} — {{ $u->division }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="date" name="action_items[{{ $i }}][deadline]"
                                           class="form-control form-control-sm"
                                           value="{{ $a['deadline'] ?? '' }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-block remove-action">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            {{-- Catatan --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-secondary">Catatan Tambahan</h6>
                </div>
                <div class="card-body">
                    <textarea name="notes" rows="3" class="form-control">{{ old('notes', $meeting->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Aksi --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Publikasi</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="font-weight-bold small">Status</label>
                        <select name="status" class="form-control">
                            <option value="draft"     {{ old('status', $meeting->status) === 'draft'     ? 'selected' : '' }}>
                                Draft (belum dipublikasi)
                            </option>
                            <option value="published" {{ old('status', $meeting->status) === 'published' ? 'selected' : '' }}>
                                Published (kirim notif WA)
                            </option>
                        </select>
                        <small class="text-muted">Notifikasi WA dikirim saat status = Published.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                    <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        Batal
                    </a>
                </div>
            </div>

            {{-- Peserta --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Peserta Rapat</h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @php $selected = old('attendees', $meeting->attendees ?? []); @endphp
                    @foreach($users as $u)
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="att_{{ $u->id }}"
                                   name="attendees[]" value="{{ $u->id }}"
                                   {{ in_array($u->id, $selected) ? 'checked' : '' }}>
                            <label class="custom-control-label small" for="att_{{ $u->id }}">
                                {{ $u->name }}
                                <span class="text-muted">— {{ $u->division ?? '-' }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Quill CSS/JS --}}
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ============ QUILL ============
    const quill = new Quill('#discussionEditor', {
        theme: 'snow',
        placeholder: 'Tulis pembahasan rapat...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean']
            ]
        }
    });

    // Sync ke hidden input saat submit
    document.querySelector('form').addEventListener('submit', function () {
        document.getElementById('discussionInput').value = quill.root.innerHTML;
    });

    // ============ DECISIONS ============
    let decisionIdx = {{ count(old('decisions', $meeting->decisions ?? [])) }};

    function bindDecisionRemove(row) {
        row.querySelector('.remove-decision').addEventListener('click', () => row.remove());
    }

    document.querySelectorAll('.decision-row').forEach(bindDecisionRemove);

    document.getElementById('addDecision').addEventListener('click', function () {
        const div = document.createElement('div');
        div.className = 'input-group mb-2 decision-row';
        div.innerHTML = `
            <input type="text" name="decisions[${decisionIdx}][decision]" class="form-control" placeholder="Isi keputusan...">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-danger remove-decision"><i class="fas fa-times"></i></button>
            </div>`;
        document.getElementById('decisionsContainer').appendChild(div);
        bindDecisionRemove(div);
        decisionIdx++;
    });

    // ============ ACTION ITEMS ============
    let actionIdx = {{ count(old('action_items', $meeting->action_items ?? [])) }};

    function bindActionRemove(row) {
        row.querySelector('.remove-action').addEventListener('click', () => row.remove());
    }

    document.querySelectorAll('.action-row').forEach(bindActionRemove);

    document.getElementById('addAction').addEventListener('click', function () {
        const div = document.createElement('div');
        div.className = 'border rounded p-2 mb-2 action-row';
        div.innerHTML = `
            <div class="form-group mb-2">
                <input type="text" name="action_items[${actionIdx}][task]" class="form-control form-control-sm" placeholder="Deskripsi tugas...">
            </div>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <select name="action_items[${actionIdx}][assigned_to]" class="form-control form-control-sm">
                        <option value="">-- PIC --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->division }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <input type="date" name="action_items[${actionIdx}][deadline]" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-2">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-block remove-action"><i class="fas fa-times"></i></button>
                </div>
            </div>`;
        document.getElementById('actionsContainer').appendChild(div);
        bindActionRemove(div);
        actionIdx++;
    });
});
</script>
@endsection