@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="{{ route('meetings.index') }}" class="small text-muted">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Rapat
        </a>
        <h1 class="h3 mb-0 text-gray-800 mt-1">{{ $meeting->title }}</h1>
        <small class="text-muted">
            <span class="badge badge-{{ $meeting->status === 'published' ? 'success' : 'secondary' }}">
                {{ ucfirst($meeting->status) }}
            </span>
            • Dibuat oleh {{ $meeting->creator->name ?? '-' }}
            @if($meeting->published_at)
                • Dipublikasikan {{ $meeting->published_at->translatedFormat('d F Y H:i') }}
            @endif
        </small>
    </div>
    <div>
        @if($canEdit)
            @if($meeting->status === 'draft')
                <form action="{{ route('meetings.publish', $meeting->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Publikasikan notulen ini? Notifikasi WA akan dikirim.')">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-success btn-sm shadow-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Publish
                    </button>
                </form>
            @endif
            <a href="{{ route('meetings.exportPdf', $meeting->id) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
            <a href="{{ route('meetings.exportWord', $meeting->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-word mr-1"></i> Word
            </a>
            <a href="{{ route('meetings.edit', $meeting->id) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-edit mr-1"></i> Edit
            </a>
            <form action="{{ route('meetings.destroy', $meeting->id) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Hapus rapat ini?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        {{-- Info --}}
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row small">
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-calendar text-primary mr-1"></i> Tanggal:</strong>
                        {{ $meeting->date_formatted }}
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-clock text-primary mr-1"></i> Waktu:</strong>
                        {{ substr($meeting->start_time, 0, 5) }} - {{ substr($meeting->end_time, 0, 5) }}
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-map-marker-alt text-primary mr-1"></i> Lokasi:</strong>
                        {{ $meeting->location ?? '-' }}
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-users text-primary mr-1"></i> Peserta:</strong>
                        {{ count($meeting->attendees ?? []) }} orang
                    </div>
                </div>
            </div>
        </div>

        {{-- Agenda --}}
        @if($meeting->agenda)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Agenda</h6>
                </div>
                <div class="card-body" style="white-space: pre-line;">{{ $meeting->agenda }}</div>
            </div>
        @endif

        {{-- Pembahasan --}}
        @if($meeting->discussion)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-comments mr-1"></i> Pembahasan</h6>
                </div>
                <div class="card-body">
                    {!! $meeting->discussion !!}
                </div>
            </div>
        @endif

        {{-- Keputusan --}}
        @if(!empty($meeting->decisions))
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-gavel mr-1"></i> Keputusan</h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0 pl-3">
                        @foreach($meeting->decisions as $d)
                            <li class="mb-1">{{ $d['decision'] ?? '-' }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @endif

        {{-- Catatan --}}
        @if($meeting->notes)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-sticky-note mr-1"></i> Catatan</h6>
                </div>
                <div class="card-body" style="white-space: pre-line;">{{ $meeting->notes }}</div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Action Items --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-tasks mr-1"></i> Action Items
                </h6>
                <span class="badge badge-primary" id="actionCounter">
                    {{ $meeting->completed_action_items }}/{{ $meeting->total_action_items }}
                </span>
            </div>
            <div class="card-body" id="actionItemsContainer">
                @forelse($meeting->action_items ?? [] as $idx => $item)
                    @php
                        $done = ($item['status'] ?? 'pending') === 'done';
                        $pic  = $item['assigned_to'] ? \App\Models\User::find($item['assigned_to']) : null;
                    @endphp
                    <div class="d-flex align-items-start p-2 mb-2 rounded border {{ $done ? 'bg-light' : '' }}">
                        <div class="mr-2 pt-1">
                            <input type="checkbox" class="action-toggle" data-meeting="{{ $meeting->id }}"
                                   data-item="{{ $idx }}" {{ $done ? 'checked' : '' }}>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="font-weight-bold small {{ $done ? 'text-muted text-decoration-line-through' : 'text-dark' }}">
                                {{ $item['task'] ?? '-' }}
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                <i class="fas fa-user mr-1"></i>{{ $pic->name ?? 'Tanpa PIC' }}
                                @if(!empty($item['deadline']))
                                    • <i class="fas fa-clock ml-1 mr-1"></i>{{ $item['deadline'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small text-center mb-0">Tidak ada action item</p>
                @endforelse
            </div>
        </div>

        {{-- Peserta --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users mr-1"></i> Peserta</h6>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                @forelse($attendees as $u)
                    <div class="d-flex align-items-center mb-2">
                        @if($u->avatar)
                            <img src="{{ asset('storage/avatars/' . $u->avatar) }}"
                                 class="rounded-circle mr-2" style="width: 30px; height: 30px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-primary text-white mr-2 d-flex align-items-center justify-content-center"
                                 style="width: 30px; height: 30px; font-size: 11px; font-weight: 700;">
                                {{ strtoupper(substr($u->name, 0, 2)) }}
                            </div>
                        @endif
                        <div style="min-width: 0;">
                            <div class="font-weight-bold small text-truncate">{{ $u->name }}</div>
                            <div class="text-muted" style="font-size: 10.5px;">{{ $u->division ?? '-' }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small text-center mb-0">Belum ada peserta terdaftar</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.action-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
        const meetingId = this.dataset.meeting;
        const itemId    = this.dataset.item;
        const el        = this;

        fetch('{{ route('meetings.action-items.toggle', ['itemId' => '__ITEM__']) }}'.replace('__ITEM__', itemId), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ meeting_id: meetingId })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                el.checked = !el.checked;
                alert(data.message || 'Gagal update.');
                return;
            }
            document.getElementById('actionCounter').textContent = data.completed + '/' + data.total;
            const label = el.parentElement.parentElement.querySelector('.font-weight-bold');
            if (data.status === 'done') {
                label.classList.add('text-muted', 'text-decoration-line-through');
                label.classList.remove('text-dark');
            } else {
                label.classList.remove('text-muted', 'text-decoration-line-through');
                label.classList.add('text-dark');
            }
        })
        .catch(() => {
            el.checked = !el.checked;
            alert('Terjadi kesalahan jaringan.');
        });
    });
});
</script>
@endpush
@endsection