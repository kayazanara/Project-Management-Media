<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\Project;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContentController extends Controller
{
    protected WhatsappNotifier $notifier;

    public function __construct(WhatsappNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    // ============================================================
    // HELPER: AKSES EDIT/DELETE KONTEN
    // ============================================================

    private function canEditOrDeleteContent(User $user): bool
    {
        if ($user->isPembina() || $user->isKetuaMedia()) {
            return true;
        }

        if ($user->isKepalaDivisi() && str_contains(strtolower($user->division ?? ''), 'broadcast')) {
            return true;
        }

        return false;
    }

    // ============================================================
    // HELPER: FILTER PROJECT SESUAI DIVISI USER
    // ============================================================

    private function getProjectsForUpload()
    {
        $user = Auth::user();

        if ($user->isManager()) {
            return Project::where('status', '!=', Project::STATUS_COMPLETED)
                ->orderBy('name')
                ->get();
        }

        $division = trim($user->division ?? '');
        if (empty($division)) {
            return Project::where('status', '!=', Project::STATUS_COMPLETED)
                ->orderBy('name')
                ->get();
        }

        $divisionUserIds = User::where('division', $division)->pluck('id');

        return Project::whereHas('users', function ($q) use ($divisionUserIds) {
                $q->whereIn('users.id', $divisionUserIds);
            })
            ->where('status', '!=', Project::STATUS_COMPLETED)
            ->orderBy('name')
            ->get();
    }

    private function canUserUploadToProject(User $user, Project $project): bool
    {
        if ($user->isManager()) {
            return true;
        }

        $division = trim($user->division ?? '');
        if (empty($division)) {
            return false;
        }

        return $project->users()->where('users.division', $division)->exists();
    }

    private function getRedirectAfterActivity(User $user)
    {
        if ($user->isManager()) {
            return route('contents.index');
        }

        $divName = strtolower(trim($user->division ?? ''));

        if (str_contains($divName, 'broadcast')) {
            return route('contents.index');
        }

        $map = [
            'lapangan'    => 'Tim Lapangan',
            'editing'     => 'Tim Editing',
            'broadcast'   => 'Tim Broadcast',
            'ai research' => 'Tim AI Research',
            'research'    => 'Tim AI Research',
        ];

        foreach ($map as $keyword => $routeName) {
            if (str_contains($divName, $keyword)) {
                return route('division.show', $routeName);
            }
        }

        return route('dashboard');
    }

    // ============================================================
    // INDEX
    // ============================================================

    public function index(Request $request)
    {
        $query = Content::where('status', 'Ready')->with('project');

        if ($request->filled('type') && in_array($request->type, ['raw', 'content_stock', 'activity'])) {
            $query->where('upload_type', $request->type);
        }

        $readyContents = $query->orderBy('created_at', 'desc')->get();

        $historyQuery = Content::whereIn('status', ['Uploaded', 'Archived', 'Completed'])->with('project');

        if ($request->filled('type') && in_array($request->type, ['raw', 'content_stock', 'activity'])) {
            $historyQuery->where('upload_type', $request->type);
        }

        $historyContents = $historyQuery->orderBy('updated_at', 'desc')->take(50)->get();

        $stats = [
            'total_ready'    => Content::where('status', 'Ready')->count(),
            'total_raw'      => Content::where('upload_type', 'raw')->count(),
            'total_stock'    => Content::where('upload_type', 'content_stock')->count(),
            'total_activity' => Content::where('upload_type', 'activity')->count(),
            'waiting_meta'   => Content::where('upload_type', 'content_stock')
                ->whereNull('broadcast_filled_at')
                ->where('status', '!=', 'Completed')
                ->count(),
        ];

        return view('contents.index', [
            'title'           => 'Stock Management',
            'readyContents'   => $readyContents,
            'historyContents' => $historyContents,
            'totalReadyStock' => $stats['total_ready'],
            'stats'           => $stats,
            'filterType'      => $request->input('type'),
        ]);
    }

    // ============================================================
    // CREATE / STORE (Legacy)
    // ============================================================

    public function create()
    {
        return view('contents.create', [
            'title'    => 'Tambah Konten Baru',
            'projects' => Project::all(),
        ]);
    }

    public function store(Request $request)
    {
        // ⚡ FIX (S3): sebelumnya TANPA cek hak akses -> anggota Broadcast mana pun bisa
        // membuat konten di project mana pun.
        if (! $this->canEditOrDeleteContent(Auth::user())) {
            abort(403, 'Anda tidak berhak menambah konten.');
        }

        $request->validate([
            'project_id'      => 'required|exists:projects,id',
            'title'           => 'required|string|max:255',
            'category_prefix' => 'required|string|in:DAK,DOK,MEM,PSB',
            'type'            => 'required|string',
            'platform'        => 'required|array|min:1',
            'platform.*'      => 'string',
            'file_url'        => 'nullable|string',
            'caption'         => 'nullable|string',
        ]);

        $contentCode = $this->generateContentCode($request->category_prefix);

        Content::create([
            'content_code' => $contentCode,
            'project_id'   => $request->project_id,
            'title'        => $request->title,
            'type'         => $request->type,
            'platform'     => $request->platform,
            'file_url'     => $request->file_url,
            'caption'      => $request->caption,
            'upload_type'  => 'content_stock',
            'status'       => 'Ready',
        ]);

        return redirect()->route('contents.index')
            ->with('success', 'Konten baru berhasil ditambahkan ke Stock Management!');
    }

    // ============================================================
    // EDIT / UPDATE / DESTROY — + Guard
    // ============================================================

    public function edit($id)
    {
        $user = Auth::user();

        if (!$this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak mengedit konten.');
        }

        $content = Content::findOrFail($id);

        return view('contents.edit', [
            'title'    => 'Edit Konten: ' . $content->content_code,
            'content'  => $content,
            'projects' => Project::all(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak mengedit konten.');
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title'      => 'required|string|max:255',
            'type'       => 'nullable|string',
            'platform'   => 'nullable|array',
            'platform.*' => 'string',
            'file_url'   => 'nullable|string',
            'caption'    => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);

        // ⚡ FIX (S1): JANGAN pernah mengosongkan data yang sudah ada.
        // Sebelumnya 'file_url' & 'caption' diambil mentah dari request (tanpa fallback),
        // sementara form Edit tidak memuat nilai lama -> sekali klik Simpan,
        // link file & caption/deskripsi konten HILANG.
        $content->update([
            'project_id' => $request->project_id,
            'title'      => $request->title,
            'type'       => $request->filled('type') ? $request->type : $content->type,
            'platform'   => $request->filled('platform') ? $request->platform : $content->platform,
            'file_url'   => $request->filled('file_url') ? $request->file_url : $content->file_url,
            'caption'    => $request->filled('caption') ? $request->caption : $content->caption,
        ]);

        return redirect()->route('contents.index')
            ->with('success', 'Data konten berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $user = Auth::user();

        if (!$this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak menghapus konten.');
        }

        $content = Content::findOrFail($id);
        $content->delete();

        return redirect()->route('contents.index')
            ->with('success', 'Konten berhasil dihapus dari sistem!');
    }

    // ============================================================
    // UPDATE STATUS
    // ============================================================

    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        // ⚡ FIX (S2): sebelumnya TIDAK ADA cek hak akses sama sekali -> siapa pun yang bisa
        // membuka grup route ini (termasuk anggota Broadcast biasa) bisa mengubah status
        // konten milik siapa pun. Sekarang disamakan dengan edit/update/destroy.
        if (! $this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak mengubah status konten.');
        }

        $content = Content::findOrFail($id);

        $request->validate([
            'status' => 'required|in:Ready,Uploaded,Archived',
        ], [
            'status.in' => 'Status hanya boleh Ready, Uploaded (sudah diposting), atau Archived.',
        ]);

        $updateData = ['status' => $request->status];

        if ($request->status === 'Uploaded' && !$content->uploaded_at) {
            $updateData['uploaded_at'] = now();
        } elseif ($request->status === 'Ready') {
            $updateData['uploaded_at'] = null;
        }

        $content->update($updateData);

        return redirect()->back()->with('success', 'Status konten berhasil diperbarui!');
    }

    // ============================================================
    // ⚡ POST / UNPOST — alur baru: Ready -> "Sudah Diposting"
    // ============================================================

    /**
     * Tandai konten sudah diposting.
     * Nilai status tetap memakai 'Uploaded' yang sudah ada (tanpa ubah struktur data),
     * hanya ditampilkan sebagai "Sudah Diposting" di UI.
     */
    public function post(Request $request, $id)
    {
        $user = Auth::user();

        if (! $this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak memposting konten.');
        }

        $content = Content::findOrFail($id);

        if ($content->upload_type !== 'content_stock') {
            return back()->with('error', 'Tombol Post hanya untuk konten Stock.');
        }

        if (! $content->broadcast_filled_at) {
            return back()->with('error', 'Isi metadata dulu sebelum konten bisa diposting.');
        }

        if ($content->status === 'Uploaded') {
            return back()->with('error', 'Konten ini sudah diposting.');
        }

        $content->update([
            'status'      => 'Uploaded',
            'uploaded_at' => now(),
        ]);

        return back()->with('success', 'Konten ' . $content->content_code . ' ditandai SUDAH DIPOSTING.');
    }

    /**
     * Batalkan posting (kembali ke Ready).
     */
    public function unpost(Request $request, $id)
    {
        $user = Auth::user();

        if (! $this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak membatalkan posting.');
        }

        $content = Content::findOrFail($id);

        if ($content->status !== 'Uploaded') {
            return back()->with('error', 'Konten ini tidak dalam status sudah diposting.');
        }

        $content->update([
            'status'      => 'Ready',
            'uploaded_at' => null,
        ]);

        return back()->with('success', 'Posting konten ' . $content->content_code . ' dibatalkan (kembali ke Ready).');
    }

    // ============================================================
    // FORM UPLOAD 3 JENIS
    // ============================================================

    public function formRaw(Request $request)
    {
        return view('contents.upload-raw', [
            'title'            => 'Upload Bahan Mentah (Raw)',
            'projects'         => $this->getProjectsForUpload(),
            'defaultProjectId' => $request->input('project_id'),
        ]);
    }

    public function formStock(Request $request)
    {
        return view('contents.upload-stock', [
            'title'            => 'Upload Content Stock',
            'projects'         => $this->getProjectsForUpload(),
            'defaultProjectId' => $request->input('project_id'),
        ]);
    }

    public function formActivity(Request $request)
    {
        return view('contents.upload-activity', [
            'title'            => 'Upload Activity',
            'projects'         => $this->getProjectsForUpload(),
            'defaultProjectId' => $request->input('project_id'),
        ]);
    }

    // ============================================================
    // UPLOAD RAW
    // ============================================================

    public function uploadRaw(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'project_id'      => 'required|exists:projects,id',
            'title'           => 'required|string|max:255',
            'file_url'        => 'required|string',
            'target_division' => 'required|string|max:100',
            'notes'           => 'nullable|string',
        ]);

        $project = Project::findOrFail($request->project_id);
        $isInvolved = $project->users()->where('users.id', $user->id)->exists();
        $sameDivision = !empty($user->division)
            && $project->users()->where('users.division', $user->division)->exists();

        if (!$isInvolved && !$sameDivision && !$user->isManager()) {
            return back()->with('error', 'Anda tidak berhak upload ke project ini.');
        }

        $contentCode = $this->generateContentCode('RAW');

        $content = Content::create([
            'content_code'    => $contentCode,
            'project_id'      => $project->id,
            'title'           => $request->title,
            'type'            => 'Raw Material',
            'platform'        => [],
            'upload_type'     => 'raw',
            'target_division' => $request->target_division,
            'file_url'        => $request->file_url,
            'caption'         => $request->notes,
            'status'          => 'Ready',
        ]);

        $targetUsers = User::where('division', 'like', '%' . $request->target_division . '%')
            ->where('status', 'Aktif')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        foreach ($targetUsers as $u) {
            if ($u->id === $user->id) continue;
            try {
                $msg = $this->notifier->rawUploadedTemplate(
                    $u->name,
                    $project->name,
                    $user->name,
                    $request->title,
                    $request->target_division,
                    $content->id
                );
                $this->notifier->sendAndLog($u, $project->id, 'raw_uploaded', $msg);
            } catch (\Throwable $e) {
                Log::warning("[Upload Raw] Gagal notif ke user {$u->id}: {$e->getMessage()}");
            }
        }

        return redirect()
            ->to($this->getRedirectAfterActivity($user))
            ->with('success', "Raw berhasil diupload! Notifikasi terkirim ke Divisi {$request->target_division}.");
    }

    // ============================================================
    // UPLOAD STOCK — MULTI-ROW
    // ============================================================

    public function uploadStock(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'project_id'        => 'required|exists:projects,id',
            'items'             => 'required|array|min:1',
            'items.*.title'     => 'required|string|max:255',
            'items.*.file_url'  => 'required|string|max:500',
            'items.*.prefix'    => 'required|string|in:DAK,DOK,MEM,PSB',
        ], [
            'items.required'            => 'Minimal 1 konten harus diisi.',
            'items.min'                 => 'Minimal 1 konten harus diisi.',
            'items.*.title.required'    => 'Judul konten wajib diisi.',
            'items.*.file_url.required' => 'Link file wajib diisi.',
            'items.*.prefix.required'   => 'Prefix kode wajib dipilih.',
            'items.*.prefix.in'         => 'Prefix kode tidak valid.',
        ]);

        $project = Project::findOrFail($request->project_id);

        if (!$this->canUserUploadToProject($user, $project)) {
            return back()->with('error', 'Anda tidak berhak upload ke project ini.');
        }

        DB::beginTransaction();
        try {
            $createdContents = [];

            foreach ($request->items as $item) {
                $contentCode = $this->generateContentCode($item['prefix']);

                $content = Content::create([
                    'content_code' => $contentCode,
                    'project_id'   => $project->id,
                    'title'        => $item['title'],
                    'type'         => null,
                    'platform'     => [],
                    'file_url'     => $item['file_url'],
                    'caption'      => null,
                    'upload_type'  => 'content_stock',
                    'status'       => 'Ready',
                ]);

                $createdContents[] = $content;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Upload Stock] Rollback: ' . $e->getMessage());
            return back()->with('error', 'Gagal upload: ' . $e->getMessage())->withInput();
        }

        $broadcastUsers = User::where('division', 'like', '%broadcast%')
            ->where('status', 'Aktif')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $totalCount = count($createdContents);
        $firstContent = $createdContents[0];

        foreach ($broadcastUsers as $u) {
            if ($u->id === $user->id) continue;
            try {
                $codeLabel = $totalCount > 1
                    ? $firstContent->content_code . ' (+' . ($totalCount - 1) . ' lainnya)'
                    : $firstContent->content_code;

                $titleLabel = $totalCount > 1
                    ? $firstContent->title . ' dll'
                    : $firstContent->title;

                $msg = $this->notifier->stockUploadedTemplate(
                    $u->name,
                    $codeLabel,
                    $titleLabel,
                    $project->name ?? '-',
                    $firstContent->id
                );
                $this->notifier->sendAndLog($u, $project->id, 'stock_uploaded', $msg);
            } catch (\Throwable $e) {
                Log::warning("[Upload Stock] Gagal notif ke user {$u->id}: {$e->getMessage()}");
            }
        }

        return redirect()
            ->to($this->getRedirectAfterActivity($user))
            ->with('success', "{$totalCount} stok konten berhasil diupload! Menunggu Broadcast mengisi metadata.");
    }

    // ============================================================
    // UPLOAD ACTIVITY
    // ============================================================

    public function uploadActivity(Request $request)
    {
        $user = Auth::user();

        if (!$user->isKepalaDivisi() && !$user->isManager()) {
            return back()->with('error', 'Hanya Kepala Divisi atau Manager yang bisa upload activity.');
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title'      => 'required|string|max:255',
            'file_url'   => 'nullable|string',
            'caption'    => 'required|string|min:10',
        ], [
            'caption.required' => 'Deskripsi activity wajib diisi (min 10 karakter).',
            'caption.min'      => 'Deskripsi activity minimal 10 karakter.',
        ]);

        $project = Project::findOrFail($request->project_id);

        if (!$this->canUserUploadToProject($user, $project)) {
            return back()->with('error', 'Anda tidak berhak upload ke project ini.');
        }

        $contentCode = $this->generateContentCode('ACT');

        Content::create([
            'content_code' => $contentCode,
            'project_id'   => $project->id,
            'title'        => $request->title,
            'type'         => 'Activity',
            'platform'     => [],
            'upload_type'  => 'activity',
            'file_url'     => $request->file_url,
            'caption'      => $request->caption,
            'status'       => 'Completed',
            'uploaded_at'  => now(),
        ]);

        return redirect()
            ->to($this->getRedirectAfterActivity($user))
            ->with('success', 'Activity berhasil diupload & ditandai Complete!');
    }

    // ============================================================
    // METADATA BROADCAST
    // ============================================================

    public function fillBroadcastMetadata(Request $request, $id)
    {
        $user = Auth::user();

        // ⚡ FIX: hanya Kepala Divisi Broadcast / Ketua Media / Pembina
        // (sebelumnya semua anggota Broadcast boleh mengisi metadata).
        if (! $this->canEditOrDeleteContent($user)) {
            return back()->with('error', 'Hanya Kepala Divisi Broadcast, Ketua Media, atau Pembina yang bisa mengisi metadata.');
        }

        $request->validate([
            'category'     => 'required|string|max:100',
            'content_type' => 'required|string|max:100',
            'platform'     => 'required|array|min:1',
            'platform.*'   => 'string',
            'caption'      => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);

        // ⚡ FIX (S10): metadata hanya untuk konten Stock
        if ($content->upload_type !== 'content_stock') {
            return back()->with('error', 'Metadata hanya untuk konten Stock.');
        }

        $content->update([
            'category'            => $request->category,
            'content_type'        => $request->content_type,
            'type'                => $request->content_type,
            'platform'            => $request->platform,
            'caption'             => $request->caption,
            'broadcast_filled_at' => now(),
            // ⚡ FIX (alur baru): setelah metadata, status jadi READY (siap diposting),
            // BUKAN 'Completed' — 'Completed' itu yang memotong alur & bikin konten
            // langsung lompat ke riwayat tanpa pernah lewat tahap "diposting".
            'status'              => 'Ready',
        ]);

        return redirect()->route('contents.index')
            ->with('success', 'Metadata tersimpan! Konten berstatus READY — siap diposting.');
    }

    public function formBroadcastMetadata($id)
    {
        $user = Auth::user();

        // ⚡ FIX (S10): form metadata ikut dibatasi (sebelumnya cek hanya di PATCH,
        // jadi halaman form-nya bisa dibuka siapa pun).
        if (! $this->canEditOrDeleteContent($user)) {
            abort(403, 'Anda tidak berhak mengisi metadata konten.');
        }

        $content = Content::findOrFail($id);

        if ($content->upload_type !== 'content_stock') {
            return redirect()->route('contents.index')
                ->with('error', 'Metadata hanya untuk konten Stock.');
        }

        return view('contents.broadcast-edit', [
            'title'   => 'Isi Metadata: ' . $content->content_code,
            'content' => $content,
        ]);
    }

    // ============================================================
    // HELPER
    // ============================================================

    /**
     * ⚡ FIX (S4): kode konten dihitung dari NOMOR TERBESAR (bukan baris id terakhir),
     * format seragam 3 digit mengikuti data lamamu, dan dicek supaya tidak bentrok.
     *
     * Sebelumnya: ambil baris dengan id terbesar lalu str_replace mentah -> kalau ada kode
     * lama berformat beda (DAK023 vs DAK24) nomornya bisa salah, dan kalau bentrok SELURUH
     * batch upload gagal karena ada unique index di content_code.
     */
    private function generateContentCode(string $prefix): string
    {
        $angkaTerbesar = 0;

        foreach (Content::where('content_code', 'like', $prefix . '%')->pluck('content_code') as $kode) {
            $angka = (int) preg_replace('/\D/', '', substr((string) $kode, strlen($prefix)));

            if ($angka > $angkaTerbesar) {
                $angkaTerbesar = $angka;
            }
        }

        $next = $angkaTerbesar + 1;

        // Lewati nomor yang ternyata sudah dipakai (jaga-jaga kalau ada kode berformat lain)
        while (Content::where('content_code', $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}