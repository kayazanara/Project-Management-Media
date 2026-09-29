<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show($id)
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();
        $isOwnProfile = $currentUser->id === $user->id;

        $userDiv = strtolower(trim($currentUser->division ?? ''));
        $userRole = strtolower(trim($currentUser->role ?? ''));
        $isManager = str_contains($userDiv, 'pembina')
            || str_contains($userDiv, 'ketua')
            || $userRole === 'administrator'
            || str_contains($userRole, 'ustadz');

        $stats = [
            'completed_projects' => $user->projects()->where('status', 'Completed')->count(),
            'active_projects'    => $user->projects()->where('status', '!=', 'Completed')->count(),
            'completed_tasks'    => $user->contributedTasks()->where('status', 'Completed')->count(),
            'member_since_days'  => (int) \Carbon\Carbon::parse($user->created_at)->diffInDays(now()),
            'login_days'         => $user->total_login_days ?? 0,
            // ⚡ FITUR BARU: streak login (hari berturut-turut) untuk ditampilkan di profil
            'login_streak'       => $user->login_streak ?? 0,
        ];

        $recentTasks = $user->contributedTasks()
            ->with('project')
            ->where('status', 'Completed')
            ->orderBy('completed_at', 'desc')
            ->take(10)
            ->get();

        return view('profile.show', [
            'title' => 'Profil: ' . $user->name,
            'user' => $user,
            'isOwnProfile' => $isOwnProfile,
            'isManager' => $isManager,
            'stats' => $stats,
            'recentTasks' => $recentTasks,
        ]);
    }

    public function edit()
    {
        $user = auth()->user();

        return view('profile.edit', [
            'title' => 'Edit Profil',
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'theme_color' => 'nullable|string|in:blue-purple,ocean,sunset,emerald,rose,dark',
            'social_links.instagram' => 'nullable|string|max:255',
            'social_links.github' => 'nullable|string|max:255',
            'social_links.facebook' => 'nullable|string|max:255',
            'social_links.tiktok' => 'nullable|string|max:255',
        ]);

        $socialLinks = array_filter([
            'instagram' => $request->input('social_links.instagram'),
            'github'    => $request->input('social_links.github'),
            'facebook'  => $request->input('social_links.facebook'),
            'tiktok'    => $request->input('social_links.tiktok'),
        ]);

        $user->update([
            'name' => $request->name,
            'bio' => $request->bio,
            'phone' => $request->phone ? \App\Helpers\PhoneHelper::normalize($request->phone) : $user->phone,
            'theme_color' => $request->theme_color ?? 'blue-purple',
            'social_links' => !empty($socialLinks) ? $socialLinks : null,
        ]);

        return redirect()->route('profile.show', $user->id)
            ->with('success', 'Profil berhasil diperbarui!');
    }

    public function updateBackground(Request $request)
    {
        $request->validate([
            'cropped_background' => 'required|string',
        ]);

        $user = auth()->user();
        $imageData = $request->input('cropped_background');

        list($type, $imageData) = explode(';', $imageData);
        list(, $imageData) = explode(',', $imageData);
        $imageBinary = base64_decode($imageData);

        $filename = 'bg_' . time() . '_' . uniqid() . '.jpg';

        Storage::disk('public')->put('backgrounds/' . $filename, $imageBinary);

        // ⚡ AUDIT: path salah ('public/...' padahal disk-nya sudah 'public') ->
        // background LAMA tidak pernah terhapus dan menumpuk di storage.
        if ($user->background_image && Storage::disk('public')->exists('backgrounds/' . $user->background_image)) {
            Storage::disk('public')->delete('backgrounds/' . $user->background_image);
        }

        $user->background_image = $filename;
        $user->save();

        return back()->with('success', 'Background profil berhasil diperbarui!');
    }

    public function updateJabatan(Request $request, $id)
    {
        // ⚡ AUDIT (D5): endpoint ini tidak punya cek hak akses sama sekali -> siapa pun yang
        // login bisa mengubah jabatan user mana pun lewat PATCH /profile/{id}/jabatan.
        if (! auth()->user()->isManager()) {
            abort(403, 'Hanya Pembina atau Ketua Media yang bisa mengubah jabatan anggota.');
        }

        $currentUser = auth()->user();
        $userDiv = strtolower(trim($currentUser->division ?? ''));
        $userRole = strtolower(trim($currentUser->role ?? ''));
        $isManager = str_contains($userDiv, 'pembina')
            || str_contains($userDiv, 'ketua')
            || $userRole === 'administrator'
            || str_contains($userRole, 'ustadz');

        if (!$isManager) {
            return back()->with('error', 'Hanya Pembina/Ketua Media yang bisa mengubah jabatan.');
        }

        $request->validate([
            'jabatan' => 'nullable|string|max:100',
        ]);

        $user = User::findOrFail($id);
        $user->update(['jabatan' => $request->jabatan]);

        return back()->with('success', 'Jabatan berhasil diperbarui!');
    }
}