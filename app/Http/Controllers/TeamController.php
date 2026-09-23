<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    public function index()
    {
        $users = User::all();
        
        $data = [
            'title' => 'Team Management',
            'users' => $users
        ];

        return view('team.index', $data);
    }

    public function create()
    {
        $data = [
            'title' => 'Tambah Anggota Tim'
        ];
        return view('team.create', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:Ustadz,Santri',
            'division' => 'nullable|string',
            'status' => 'required|in:Aktif,Non-Aktif',
            'phone' => 'nullable|string|max:20',
        ], [
            'phone.max' => 'Nomor WhatsApp terlalu panjang (maks 20 karakter).',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'division' => $request->division,
            'status' => $request->status,
            'phone' => PhoneHelper::normalize($request->phone),
        ]);

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $data = [
            'title' => 'Edit Anggota Tim',
            'user' => $user
        ];
        return view('team.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:Ustadz,Santri',
            'division' => 'nullable|string',
            'status' => 'required|in:Aktif,Non-Aktif',
            'phone' => 'nullable|string|max:20',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->role = $request->role;
        $user->division = $request->division;
        $user->status = $request->status;
        $user->phone = PhoneHelper::normalize($request->phone);
        $user->save();

        return redirect()->route('team.index')->with('success', 'Data anggota tim berhasil diupdate!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil dihapus!');
    }
}