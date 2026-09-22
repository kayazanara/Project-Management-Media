<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // Menampilkan halaman login
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // Memproses data login
    public function login(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Cek kecocokan data dengan database
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard')->with('success', 'Berhasil login!');
        }

        // Jika gagal, kembalikan ke halaman login dengan pesan error
        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    // Memproses update dan crop foto profil (avatar)
    public function updateProfile(Request $request)
    {
        $request->validate([
            'cropped_avatar' => 'required|string',
        ]);

        $user = auth()->user();
        $imageData = $request->input('cropped_avatar');

        // Decode data base64 dari hasil crop JavaScript
        list($type, $imageData) = explode(';', $imageData);
        list(, $imageData) = explode(',', $imageData);
        $imageBinary = base64_decode($imageData);

        $filename = time() . '_' . uniqid() . '.jpg';
        
        // Simpan ke storage/app/public/avatars
        Storage::disk('public')->put('avatars/' . $filename, $imageBinary);

        // Hapus foto lama jika ada
        if ($user->avatar && Storage::exists('public/avatars/' . $user->avatar)) {
            Storage::delete('public/avatars/' . $user->avatar);
        }

        $user->avatar = $filename;
        $user->save();

        return back()->with('success', 'Foto profil berhasil diperbarui!');
    }

    // Proses logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}