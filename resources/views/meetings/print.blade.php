<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — Notulen Rapat</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 0;
            padding: 30px 40px;
            line-height: 1.5;
        }
        h1 { font-size: 20px; margin: 0 0 4px; color: #4e73df; }
        h2 {
            font-size: 14px;
            margin: 24px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #4e73df;
            color: #4e73df;
        }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #4e73df; padding-bottom: 16px; }
        .header .brand { font-size: 11px; color: #666; letter-spacing: 2px; text-transform: uppercase; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-table td { padding: 4px 8px; vertical-align: top; }
        .meta-table td:first-child { width: 110px; font-weight: 600; color: #555; }
        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .content-box { padding: 10px 12px; background: #f8f9fc; border-left: 3px solid #4e73df; white-space: pre-line; }
        .content-box-rich { padding: 10px 12px; background: #f8f9fc; border-left: 3px solid #4e73df; }
        ol, ul { padding-left: 20px; margin: 4px 0; }
        li { margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11.5px; }
        table.data th, table.data td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        table.data th { background: #4e73df; color: #fff; font-weight: 600; }
        table.data tr:nth-child(even) td { background: #f8f9fc; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10.5px; font-weight: 600; }
        .badge-success { background: #1cc88a; color: #fff; }
        .badge-secondary { background: #858796; color: #fff; }
        .badge-pending { background: #f6c23e; color: #333; }
        .badge-done { background: #1cc88a; color: #fff; }
        .footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 10.5px; color: #888; text-align: center; }
        @media print {
            body { padding: 15px 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

@if($autoPrint)
    <div class="no-print" style="margin-bottom: 16px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
        <strong>Preview PDF:</strong> Gunakan dialog print browser (<em>Ctrl/Cmd + P</em>) → pilih "Save as PDF".
        <button onclick="window.print()" style="margin-left: 12px; padding: 6px 14px; background: #4e73df; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
            Print / Save PDF
        </button>
    </div>
@endif

<div class="header">
    <div class="brand">Project Management Media</div>
    <h1>Notulen Rapat</h1>
    <div style="font-size: 13px; color: #666;">{{ $meeting->title }}</div>
</div>

<div class="section">
    <table class="meta-table">
        <tr>
            <td>Hari / Tanggal</td>
            <td>: {{ $meeting->date_formatted }}</td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>: {{ substr($meeting->start_time, 0, 5) }} - {{ substr($meeting->end_time, 0, 5) }} WIB</td>
        </tr>
        <tr>
            <td>Lokasi</td>
            <td>: {{ $meeting->location ?? '-' }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>: <span class="badge badge-{{ $meeting->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($meeting->status) }}</span></td>
        </tr>
        <tr>
            <td>Pencatat</td>
            <td>: {{ $meeting->creator->name ?? '-' }}</td>
        </tr>
        @if($meeting->published_at)
        <tr>
            <td>Dipublikasikan</td>
            <td>: {{ $meeting->published_at->translatedFormat('d F Y H:i') }}</td>
        </tr>
        @endif
    </table>
</div>

@if(!empty($attendees) && count($attendees))
<div class="section">
    <h2>Peserta Rapat ({{ count($attendees) }})</h2>
    <table class="data">
        <thead>
            <tr><th style="width: 40px;">#</th><th>Nama</th><th>Divisi</th></tr>
        </thead>
        <tbody>
            @foreach($attendees as $i => $u)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->division ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($meeting->agenda)
<div class="section">
    <h2>Agenda</h2>
    <div class="content-box">{{ $meeting->agenda }}</div>
</div>
@endif

@if($meeting->discussion)
<div class="section">
    <h2>Pembahasan</h2>
    <div class="content-box-rich">{!! $meeting->discussion !!}</div>
</div>
@endif

@if(!empty($meeting->decisions))
<div class="section">
    <h2>Keputusan</h2>
    <ol>
        @foreach($meeting->decisions as $d)
            <li>{{ $d['decision'] ?? '-' }}</li>
        @endforeach
    </ol>
</div>
@endif

@if(!empty($meeting->action_items))
<div class="section">
    <h2>Action Items ({{ $meeting->completed_action_items }}/{{ $meeting->total_action_items }} selesai)</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Tugas</th>
                <th style="width: 130px;">PIC</th>
                <th style="width: 100px;">Deadline</th>
                <th style="width: 90px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meeting->action_items as $i => $a)
                @php
                    $pic  = $a['assigned_to'] ? \App\Models\User::find($a['assigned_to']) : null;
                    $done = ($a['status'] ?? 'pending') === 'done';
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $a['task'] ?? '-' }}</td>
                    <td>{{ $pic->name ?? '-' }}</td>
                    <td>{{ $a['deadline'] ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $done ? 'done' : 'pending' }}">
                            {{ $done ? 'Done' : 'Pending' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($meeting->notes)
<div class="section">
    <h2>Catatan Tambahan</h2>
    <div class="content-box">{{ $meeting->notes }}</div>
</div>
@endif

<div class="footer">
    Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }} • Project Management Media
</div>

@if($autoPrint)
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
@endif
</body>
</html>