<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ⚡ FITUR: preferensi tampilan pengguna (mulai dari mode gelap/terang).
 *
 * Dipanggil dari tombol switch di topbar (fetch/AJAX), jadi balasannya JSON —
 * tapi tetap aman kalau dipanggil lewat form biasa (redirect balik).
 */
class PreferenceController extends Controller
{
    /** Nilai tema yang sah. */
    public const TEMA_VALID = ['light', 'dark'];

    public function setTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|string|in:light,dark',
        ], [
            'theme.in' => 'Pilihan tema tidak dikenal.',
        ]);

        $user = Auth::user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Belum login.'], 401);
            }

            return redirect()->route('login');
        }

        $user->update(['theme_mode' => $validated['theme']]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'theme'   => $user->theme_mode,
            ]);
        }

        return back();
    }
}
