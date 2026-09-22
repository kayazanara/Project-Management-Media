<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\Project;

class ContentController extends Controller
{
    // Menampilkan halaman Stock Management (Ready vs Uploaded/Archived)
    public function index()
    {
        // Konten aktif yang dihitung sebagai stok (Ready)
        $readyContents = Content::where('status', 'Ready')->with('project')->get();
        
        // Riwayat konten yang sudah di-upload atau diarsipkan
        $historyContents = Content::whereIn('status', ['Uploaded', 'Archived'])->with('project')->get();

        // Hitung total stok konten aktif (Ready)
        $totalReadyStock = $readyContents->count();

        $data = [
            'title' => 'Stock Management',
            'readyContents' => $readyContents,
            'historyContents' => $historyContents,
            'totalReadyStock' => $totalReadyStock,
        ];

        return view('contents.index', $data);
    }

    // Menampilkan form tambah konten
    public function create()
    {
        $projects = Project::all();
        $data = [
            'title' => 'Tambah Konten Baru',
            'projects' => $projects,
        ];

        return view('contents.create', $data);
    }

    // Menyimpan data konten baru dengan kode ID otomatis (DAK, DOK, MEM, PSB)
    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'category_prefix' => 'required|string|in:DAK,DOK,MEM,PSB',
            'type' => 'required|string',
            'platform' => 'required|array|min:1', // Validasi array multi-select
            'platform.*' => 'string',
            'file_url' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        // Generate ID Otomatis berdasarkan prefix (Contoh: DAK01, DAK02)
        $prefix = $request->category_prefix;
        $lastContent = Content::where('content_code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        
        $nextNumber = 1;
        if ($lastContent) {
            $lastNumber = (int) str_replace($prefix, '', $lastContent->content_code);
            $nextNumber = $lastNumber + 1;
        }

        $contentCode = $prefix . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

        Content::create([
            'content_code' => $contentCode,
            'project_id' => $request->project_id,
            'title' => $request->title,
            'type' => $request->type,
            'platform' => $request->platform, // Disimpan sebagai array (casts otomatis ke JSON)
            'file_url' => $request->file_url,
            'caption' => $request->caption,
            'status' => 'Ready',
        ]);

        return redirect()->route('contents.index')->with('success', 'Konten baru berhasil ditambahkan ke Stock Management!');
    }

    // Mengubah status konten (Ready -> Uploaded / Archived)
    public function updateStatus(Request $request, $id)
    {
        $content = Content::findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:Ready,Uploaded,Archived',
        ]);

        $updateData = ['status' => $request->status];

        // Jika diubah menjadi Uploaded, catat tanggal upload-nya secara otomatis
        if ($request->status == 'Uploaded' && !$content->uploaded_at) {
            $updateData['uploaded_at'] = now();
        } elseif ($request->status == 'Ready') {
            $updateData['uploaded_at'] = null;
        }

        $content->update($updateData);

        return redirect()->back()->with('success', 'Status konten berhasil diperbarui!');
    }

    // Menghapus konten
    public function destroy($id)
    {
        $content = Content::findOrFail($id);
        $content->delete();

        return redirect()->route('contents.index')->with('success', 'Konten berhasil dihapus dari sistem!');
    }

    // Menampilkan form edit konten
    public function edit($id)
    {
        $content = Content::findOrFail($id);
        $projects = Project::all();
        
        $data = [
            'title' => 'Edit Konten: ' . $content->content_code,
            'content' => $content,
            'projects' => $projects,
        ];

        return view('contents.edit', $data);
    }

    // Memperbarui data konten
    public function update(Request $request, $id)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string',
            'platform' => 'required|array|min:1',
            'platform.*' => 'string',
            'file_url' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);

        $content->update([
            'project_id' => $request->project_id,
            'title' => $request->title,
            'type' => $request->type,
            'platform' => $request->platform,
            'file_url' => $request->file_url,
            'caption' => $request->caption,
        ]);

        return redirect()->route('contents.index')->with('success', 'Data konten berhasil diperbarui!');
    }
}