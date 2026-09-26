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
    // HELPER: FILTER PROJECT SESUAI DIVISI USER
    // ============================================================

    private function getProjectsForUpload()
    {
        $user = Auth::user();

        // Manager (Pembina / Ketua Media) → semua project aktif
        if ($user->isManager()) {
            return Project::where('status', '!=', Project::STATUS_COMPLETED)
                ->orderBy('name')
                ->get();
        }

        // Selain manager → hanya project yang melibatkan divisi user
        $division = trim($user->division ?? '');
        if (empty($division)) {
            // Fallback: user tanpa divisi → tampil semua biar tidak stuck
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
    // EDIT / UPDATE / DESTROY
    // ============================================================

    public function edit($id)
    {
        $content = Content::findOrFail($id);

        return view('contents.edit', [
            'title'    => 'Edit Konten: ' . $content->content_code,
            'content'  => $content,
            'projects' => Project::all(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title'      => 'required|string|max:255',
            'type'       => 'required|string',
            'platform'   => 'required|array|min:1',
            'platform.*' => 'string',
            'file_url'   => 'nullable|string',
            'caption'    => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);

        $content->update([
            'project_id' => $request->project_id,
            'title'      => $request->title,
            'type'       => $request->type,
            'platform'   => $request->platform,
            'file_url'   => $request->file_url,
            'caption'    => $request->caption,
        ]);

        return redirect()->route('contents.index')
            ->with('success', 'Data konten berhasil diperbarui!');
    }

    public function destroy($id)
    {
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
        $content = Content::findOrFail($id);

        $request->validate([
            'status' => 'required|in:Ready,Uploaded,Archived,Completed',
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

        return redirect()->route('contents.index')
            ->with('success', "Raw berhasil diupload! Notifikasi terkirim ke Divisi {$request->target_division}.");
    }

    // ============================================================
    // UPLOAD STOCK — ⚡ MULTI-ROW
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
                    'type'         => null,       // Diisi Broadcast saat metadata
                    'platform'     => [],
                    'file_url'     => $item['file_url'],
                    'caption'      => null,       // Diisi Broadcast saat metadata
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

        // Notifikasi WA ke Broadcast (setelah commit)
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

        return redirect()->route('contents.index')
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

        return redirect()->route('contents.index')
            ->with('success', 'Activity berhasil diupload & ditandai Complete!');
    }

    // ============================================================
    // METADATA BROADCAST
    // ============================================================

    public function fillBroadcastMetadata(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->isBroadcast() && !$user->isManager()) {
            return back()->with('error', 'Hanya Divisi Broadcast atau Manager yang bisa mengisi metadata.');
        }

        $request->validate([
            'category'     => 'required|string|max:100',
            'content_type' => 'required|string|max:100',
            'platform'     => 'required|array|min:1',
            'platform.*'   => 'string',
            'caption'      => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);

        $content->update([
            'category'            => $request->category,
            'content_type'        => $request->content_type,
            'type'                => $request->content_type,   // sinkron dengan kolom `type`
            'platform'            => $request->platform,
            'caption'             => $request->caption,
            'broadcast_filled_at' => now(),
            'status'              => 'Completed',
            'uploaded_at'         => $content->uploaded_at ?? now(),
        ]);

        return redirect()->route('contents.index')
            ->with('success', 'Metadata berhasil diisi & konten ditandai Complete!');
    }

    public function formBroadcastMetadata($id)
    {
        $content = Content::findOrFail($id);

        if ($content->upload_type !== 'content_stock') {
            return redirect()->route('contents.index')
                ->with('error', 'Hanya content_stock yang perlu metadata.');
        }

        return view('contents.broadcast-edit', [
            'title'   => 'Isi Metadata: ' . $content->content_code,
            'content' => $content,
        ]);
    }

    // ============================================================
    // HELPER
    // ============================================================

    private function generateContentCode(string $prefix): string
    {
        $last = Content::where('content_code', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $next = 1;
        if ($last) {
            $n = (int) str_replace($prefix, '', $last->content_code);
            $next = $n + 1;
        }

        return $prefix . str_pad($next, 2, '0', STR_PAD_LEFT);
    }
}