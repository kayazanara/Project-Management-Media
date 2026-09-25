<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Tidak pakai "remember me" — sesi fixed sampai 7 pagi
        if (Auth::attempt($credentials, false)) {
            $request->session()->regenerate();

            // ⭐ Simpan waktu login untuk cek batas 7 pagi
            $request->session()->put('login_at', now()->toDateTimeString());

            // ⭐ Trigger welcome animation via flash session
            return redirect()->intended('/dashboard')
                ->with('success', 'Berhasil login!')
                ->with('show_welcome', true)
                ->with('welcome_user_name', Auth::user()->name);
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'cropped_avatar' => 'required|string',
        ]);

        $user = auth()->user();
        $imageData = $request->input('cropped_avatar');

        list($type, $imageData) = explode(';', $imageData);
        list(, $imageData) = explode(',', $imageData);
        $imageBinary = base64_decode($imageData);

        $filename = time() . '_' . uniqid() . '.jpg';

        Storage::disk('public')->put('avatars/' . $filename, $imageBinary);

        if ($user->avatar && Storage::exists('public/avatars/' . $user->avatar)) {
            Storage::delete('public/avatars/' . $user->avatar);
        }

        $user->avatar = $filename;
        $user->save();

        return back()->with('success', 'Foto profil berhasil diperbarui!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}