<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckDivisionAccess
{
    /**
     * Mapping parameter role → validasi
     * Format: 'param' => callback($user) : bool
     */
    protected function checkAccess($user, string $role): bool
    {
        $divisionRole = $user->division_role ?? 'anggota';
        $division = strtolower($user->division ?? '');

        // Support multi-role dengan tanda | (OR)
        // Contoh: 'pembina|ketua_media'
        if (str_contains($role, '|')) {
            foreach (explode('|', $role) as $r) {
                if ($this->checkAccess($user, trim($r))) {
                    return true;
                }
            }
            return false;
        }

        return match ($role) {
            // ── Role-based (division_role) ──
            'pembina'       => $divisionRole === 'pembina',
            'ketua_media'   => $divisionRole === 'ketua_media',
            'kepala_divisi' => $divisionRole === 'kepala_divisi',
            'anggota'       => $divisionRole === 'anggota',
            'manager'       => in_array($divisionRole, ['pembina', 'ketua_media']),

            // ── Division-based (backward compat) ──
            'broadcast'     => str_contains($division, 'broadcast'),
            'lapangan'      => str_contains($division, 'lapangan'),
            'editing'       => str_contains($division, 'editing'),
            'ai_research'   => str_contains($division, 'ai') || str_contains($division, 'research'),

            default         => false,
        };
    }

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!$this->checkAccess($user, $role)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}