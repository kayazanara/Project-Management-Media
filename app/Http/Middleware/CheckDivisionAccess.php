<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDivisionAccess
{
    public function handle(Request $request, Closure $next, $allowedAccess): Response
    {
        // Jika belum login, tendang ke halaman login (tidak punya akun = tidak ada akses)
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Anda harus login terlebih dahulu.');
        }

        $user = auth()->user();
        $divisi = strtolower(trim($user->divisi ?? $user->division ?? ''));
        $role = strtolower(trim($user->role ?? ''));

        // 1. Pembina Media & Ketua / Administrator: Seluruh menu
        if (str_contains($divisi, 'pembina') || str_contains($divisi, 'ketua') || $role === 'administrator' || str_contains($role, 'ustadz')) {
            return $next($request);
        }

        // 2. Hak akses spesifik per divisi
        if ($allowedAccess === 'lapangan' && str_contains($divisi, 'lapangan')) {
            return $next($request);
        }
        if ($allowedAccess === 'editing' && str_contains($divisi, 'editing')) {
            return $next($request);
        }
        if ($allowedAccess === 'broadcast' && (str_contains($divisi, 'broadcast') || str_contains($divisi, 'stock'))) {
            return $next($request);
        }
        if ($allowedAccess === 'ai_research' && (str_contains($divisi, 'ai') || str_contains($divisi, 'research'))) {
            return $next($request);
        }

        // Jika tidak sesuai hak aksesnya, batasi
        abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk membuka halaman ini.');
    }
}