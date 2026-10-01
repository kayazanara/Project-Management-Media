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

        if (Auth::attempt($credentials, false)) {
            $request->session()->regenerate();
            $request->session()->put('login_at', now()->toDateTimeString());

            // ⚡ PERBAIKAN BUG STREAK (2 Okt 2026): urutan dibalik.
            // Bonus login diklaim LEBIH DULU, baru pencatatan login harian.
            // Sebelumnya trackDailyLogin() menulis last_login_date = hari ini lebih
            // dulu, sehingga pemeriksaan "login terakhir kemarin?" di claim() selalu
            // gagal dan streak di-reset ke 1 setiap hari.
            // (Sekarang claim() membaca riwayat, jadi urutan tidak lagi berpengaruh —
            //  ini lapis kedua supaya tetap benar kalau nanti ada yang mengubahnya.)
            $reward = app(\App\Services\DailyLoginReward::class)->claim(Auth::user());

            Auth::user()->trackDailyLogin();

            return redirect()->intended('/dashboard')
                ->with('success', 'Berhasil login!')
                ->with('show_welcome', $reward !== null)
                ->with('welcome_user_name', Auth::user()->name)
                ->with('login_reward', $reward);
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

        list($type, $imageData) = array_pad(explode(';', $imageData, 2), 2, '');
        list(, $imageData) = array_pad(explode(',', $imageData, 2), 2, '');

        if ($imageData === '') {
            return back()->with('error', 'Format gambar tidak dikenali. Coba ulangi crop fotonya.');
        }

        $imageBinary = base64_decode($imageData);

        $filename = time() . '_' . uniqid() . '.jpg';

        Storage::disk('public')->put('avatars/' . $filename, $imageBinary);

        // ⚡ AUDIT: dulu path-nya salah ('public/avatars/...' padahal disk-nya sudah 'public')
        // sehingga foto LAMA tidak pernah terhapus -> menumpuk di storage.
        if ($user->avatar && Storage::disk('public')->exists('avatars/' . $user->avatar)) {
            Storage::disk('public')->delete('avatars/' . $user->avatar);
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