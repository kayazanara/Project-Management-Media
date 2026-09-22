<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\ReportController;

// Halaman utama diarahkan langsung ke halaman login
Route::get('/', function () {
    return redirect('/login');
});

// Rute Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rute yang hanya bisa diakses setelah login (menggunakan middleware 'auth' & pembatasan divisi)
Route::middleware(['auth'])->group(function () {
    
    // Dashboard dapat diakses oleh semua akun yang login
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ==========================================
    // PROJECT & TASK (Akses baca umum untuk anggota yang login)
    // ==========================================
    // PENTING: Route statis '/projects/create' HARUS didaftarkan SEBELUM
    // route dinamis '/projects/{id}', jika tidak maka Laravel akan
    // menangkap "create" sebagai {id} dan menampilkan 404.
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->middleware('divisi.access:pembina')
        ->name('projects.create');

    // Route dinamis setelahnya
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{id}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
    Route::patch('/tasks/{id}/toggle', [ProjectController::class, 'toggleTask'])->name('tasks.toggle');
    Route::patch('/projects/{id}/status', [ProjectController::class, 'updateProjectStatus'])->name('projects.updateStatus');

    // ==========================================
    // 1. PEMBINA MEDIA & KETUA (Seluruh Menu)
    // ==========================================
    Route::middleware(['divisi.access:pembina'])->group(function () {
        // Team Management
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::get('/team/{id}/edit', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('/team/{id}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{id}', [TeamController::class, 'destroy'])->name('team.destroy');

        // Project Management (Store / Edit / Update / Delete)
        // Catatan: '/projects/create' sudah dipindahkan ke atas agar tidak bentrok
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        // Task Admin Control
        Route::delete('/tasks/{id}', [ProjectController::class, 'destroyTask'])->name('tasks.destroy');
        Route::get('/tasks/{id}/edit', [ProjectController::class, 'editTask'])->name('tasks.edit');
        Route::put('/tasks/{id}', [ProjectController::class, 'updateTask'])->name('tasks.update');

        // Cetak Laporan & Monitoring Berkala
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    });

    // ==========================================
    // 2. STOCK MANAGEMENT (Akses: Pembina & Broadcast)
    // ==========================================
    Route::middleware(['divisi.access:broadcast'])->group(function () {
        Route::resource('contents', ContentController::class);
        Route::patch('/contents/{id}/status', [ContentController::class, 'updateStatus'])->name('contents.status');
    });

    // ==========================================
    // 3. MONITORING DIVISI (Tim & Laporan Dinamis)
    // ==========================================
    Route::get('/division/{divisionName}', [DivisionController::class, 'show'])->name('division.show');
    Route::post('/division/{divisionName}/reports', [DivisionController::class, 'storeReport'])->name('division.reports.store');
    Route::delete('/division/reports/{id}', [DivisionController::class, 'destroyReport'])->name('division.reports.destroy');

    // Rute untuk memperbarui foto profil (avatar)
    Route::post('/profile/update-avatar', [AuthController::class, 'updateProfile'])->name('profile.updateAvatar');
});