<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    // Menampilkan daftar anggota tim
    public function index()
    {
        $users = User::all();
        
        $data = [
            'title' => 'Team Management',
            'users' => $users
        ];

        return view('team.index', $data);
    }

    // Menampilkan form tambah anggota
    public function create()
    {
        $data = [
            'title' => 'Tambah Anggota Tim'
        ];
        return view('team.create', $data);
    }

    // Menyimpan data anggota baru ke database
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required',
            'division' => 'nullable|string',
            'status' => 'required'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'division' => $request->division,
            'status' => $request->status,
        ]);

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil ditambahkan!');
    }

    // Menampilkan form edit anggota
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $data = [
            'title' => 'Edit Anggota Tim',
            'user' => $user
        ];
        return view('team.edit', $data);
    }

    // Mengupdate data anggota
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required',
            'division' => 'nullable|string',
            'status' => 'required'
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->role = $request->role;
        $user->division = $request->division;
        $user->status = $request->status;
        $user->save();

        return redirect()->route('team.index')->with('success', 'Data anggota tim berhasil diupdate!');
    }

    // Menghapus anggota tim
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('team.index')->with('success', 'Anggota tim berhasil dihapus!');
    }
}