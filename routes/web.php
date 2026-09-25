<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ==========================================
    // PROJECT & TASK
    // ==========================================
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->middleware('divisi.access:pembina')
        ->name('projects.create');

    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{id}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
    Route::patch('/tasks/{id}/toggle', [ProjectController::class, 'toggleTask'])->name('tasks.toggle');
    Route::patch('/projects/{id}/status', [ProjectController::class, 'updateProjectStatus'])->name('projects.updateStatus');

    // ==========================================
    // 1. PEMBINA MEDIA & KETUA
    // ==========================================
    Route::middleware(['divisi.access:pembina'])->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::get('/team/{id}/edit', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('/team/{id}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{id}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::delete('/tasks/{id}', [ProjectController::class, 'destroyTask'])->name('tasks.destroy');
        Route::get('/tasks/{id}/edit', [ProjectController::class, 'editTask'])->name('tasks.edit');
        Route::put('/tasks/{id}', [ProjectController::class, 'updateTask'])->name('tasks.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    });

    // ==========================================
    // 2. STOCK MANAGEMENT
    // ==========================================
    Route::middleware(['divisi.access:broadcast'])->group(function () {
        Route::resource('contents', ContentController::class);
        Route::patch('/contents/{id}/status', [ContentController::class, 'updateStatus'])->name('contents.status');
    });

    // ==========================================
    // 3. MONITORING DIVISI
    // ==========================================
    Route::get('/division/{divisionName}', [DivisionController::class, 'show'])->name('division.show');
    Route::post('/division/{divisionName}/reports', [DivisionController::class, 'storeReport'])->name('division.reports.store');
    Route::delete('/division/reports/{id}', [DivisionController::class, 'destroyReport'])->name('division.reports.destroy');

    Route::patch('/division/{divisionName}/projects/{id}/status', [DivisionController::class, 'updateProjectStatus'])
        ->name('division.projects.updateStatus');

    // ==========================================
    // 4. PROFILE
    // ==========================================
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/my-profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/my-profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/my-profile/background', [ProfileController::class, 'updateBackground'])->name('profile.updateBackground');
    Route::patch('/profile/{id}/jabatan', [ProfileController::class, 'updateJabatan'])->name('profile.updateJabatan');

    Route::post('/profile/update-avatar', [AuthController::class, 'updateProfile'])->name('profile.updateAvatar');
});