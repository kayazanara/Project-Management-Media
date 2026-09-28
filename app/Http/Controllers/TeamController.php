<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $users = User::orderBy('division_role')->orderBy('name')->get();

        // Statistik per division role
        $stats = [
            'total'         => $users->count(),
            'pembina'       => $users->where('division_role', 'pembina')->count(),
            'ketua_media'   => $users->where('division_role', 'ketua_media')->count(),
            'kepala_divisi' => $users->where('division_role', 'kepala_divisi')->count(),
            'anggota'       => $users->where('division_role', 'anggota')->count(),
        ];

        return view('team.index', [
            'title' => 'Team Management',
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('team.create', [
            'title' => 'Tambah Anggota Tim',
            'divisions' => $this->availableDivisions(),
            'divisionRoles' => $this->availableDivisionRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6',
            'role'          => 'required|in:Ustadz,Santri',
            'division'      => 'nullable|string',
            'division_role' => 'required|in:pembina,ketua_media,kepala_divisi,anggota',
            'status'        => 'required|in:Aktif,Non-Aktif',
            'phone'         => 'nullable|string|max:20',
        ], [
            'phone.max' => 'Nomor WhatsApp terlalu panjang (maks 20 karakter).',
        ]);

        // Validasi: hanya 1 kepala divisi per divisi
        if ($request->division_role === 'kepala_divisi' && $request->division) {
            $existing = User::where('division', $request->division)
                ->where('division_role', 'kepala_divisi')
                ->exists();

            if ($existing) {
                return back()
                    ->withInput()
                    ->withErrors(['division_role' => "Divisi {$request->division} sudah punya Kepala Divisi. Hanya boleh 1."]);
            }
        }

        User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'role'          => $request->role,
            'division'      => $request->division,
            'division_role' => $request->division_role,
            'status'        => $request->status,
            'phone'         => PhoneHelper::normalize($request->phone),
        ]);

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        return view('team.edit', [
            'title' => 'Edit Anggota Tim',
            'user' => $user,
            'divisions' => $this->availableDivisions(),
            'divisionRoles' => $this->availableDivisionRoles(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $id,
            // ⚡ AUDIT (D1): dulu kolom password saat EDIT tidak divalidasi sama sekali ->
            // password anggota bisa diganti jadi 1 karakter. Sekarang minimal 6 (sama seperti tambah).
            'password'      => 'nullable|min:6',
            'role'          => 'required|in:Ustadz,Santri',
            'division'      => 'nullable|string',
            'division_role' => 'required|in:pembina,ketua_media,kepala_divisi,anggota',
            'status'        => 'required|in:Aktif,Non-Aktif',
            'phone'         => 'nullable|string|max:20',
        ]);

        // Validasi: hanya 1 kepala divisi per divisi
        if ($request->division_role === 'kepala_divisi' && $request->division) {
            $existing = User::where('division', $request->division)
                ->where('division_role', 'kepala_divisi')
                ->where('id', '!=', $id)
                ->exists();

            if ($existing) {
                return back()
                    ->withInput()
                    ->withErrors(['division_role' => "Divisi {$request->division} sudah punya Kepala Divisi lain."]);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->role = $request->role;
        $user->division = $request->division;
        $user->division_role = $request->division_role;
        $user->status = $request->status;
        $user->phone = PhoneHelper::normalize($request->phone);
        $user->save();

        return redirect()->route('team.index')->with('success', 'Data anggota tim berhasil diupdate!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // ⚡ AUDIT (D1): dulu siapa pun dengan akses manager bisa menghapus AKUNNYA SENDIRI
        // (atau Pembina terakhir) -> langsung terkunci dari aplikasi. Sekarang dicegah.
        if ((int) auth()->id() === (int) $user->id) {
            return back()->with('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        }

        if ($user->division_role === 'pembina' && User::where('division_role', 'pembina')->count() <= 1) {
            return back()->with('error', 'Ini akun Pembina terakhir — tidak boleh dihapus.');
        }

        $user->delete();

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil dihapus!');
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function availableDivisions(): array
    {
        return [
            'Pembina Media',
            'Ketua Media',
            'Lapangan',
            'Editing',
            'Broadcast',
            'AI Research',
        ];
    }

    protected function availableDivisionRoles(): array
    {
        return [
            'pembina'       => 'Pembina Media',
            'ketua_media'   => 'Ketua Media',
            'kepala_divisi' => 'Kepala Divisi',
            'anggota'       => 'Anggota',
        ];
    }
}