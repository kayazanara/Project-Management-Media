<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkdeskController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\KetuaMediaPerformanceController;

Route::get('/', function () {
    return redirect('/login');
});

// ==========================================
// AUTH
// ==========================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    // ==========================================
    // DASHBOARD
    // ==========================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ==========================================
    // WORKDESK (Pembina + Ketua Media only)
    // ==========================================
    Route::middleware(['divisi.access:manager'])->prefix('workdesk')->group(function () {
        Route::get('/', [WorkdeskController::class, 'index'])->name('workdesk');
        Route::get('/actions', [WorkdeskController::class, 'getActions'])->name('workdesk.actions');
        Route::get('/alerts', [WorkdeskController::class, 'getAlerts'])->name('workdesk.alerts');
        Route::get('/performance', [WorkdeskController::class, 'getPerformance'])->name('workdesk.performance');
        Route::post('/alerts/{id}/read', [WorkdeskController::class, 'markRead'])->name('workdesk.markRead');
    });

    // ==========================================
    // MONITORING TIM (Pembina Media only)
    // ==========================================
    Route::middleware(['divisi.access:pembina'])->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

        // ⚡ FASE 3: riwayat poin anggota (JSON, dipakai modal Team Directory)
        Route::get('/monitoring/point-history/{id}', [MonitoringController::class, 'pointHistory'])
            ->name('monitoring.pointHistory');

        // ⚡ FITUR BARU: Kinerja Ketua Media (khusus Pembina) — memantau apakah
        // Ketua Media benar-benar memastikan tugas & project tiap divisi lancar.
        Route::get('/kinerja-ketua-media', [KetuaMediaPerformanceController::class, 'index'])
            ->name('kinerja.ketuaMedia');

        Route::post('/kinerja-ketua-media/catatan', [KetuaMediaPerformanceController::class, 'storeEvaluation'])
            ->name('kinerja.evaluasi.store');

        Route::delete('/kinerja-ketua-media/catatan/{id}', [KetuaMediaPerformanceController::class, 'destroyEvaluation'])
            ->name('kinerja.evaluasi.destroy');
    });

    // ==========================================
    // MEETINGS / RAPAT & NOTULEN
    // ==========================================
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');

    Route::patch('/meetings/action-items/{itemId}/toggle', [MeetingController::class, 'toggleActionItem'])
        ->name('meetings.action-items.toggle');

    Route::middleware(['divisi.access:manager'])->group(function () {
        Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
        Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::get('/meetings/{id}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
        Route::put('/meetings/{id}', [MeetingController::class, 'update'])->name('meetings.update');
        Route::delete('/meetings/{id}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
        Route::patch('/meetings/{id}/publish', [MeetingController::class, 'publish'])->name('meetings.publish');
        Route::get('/meetings/{id}/export-pdf', [MeetingController::class, 'exportPdf'])->name('meetings.exportPdf');
        Route::get('/meetings/{id}/export-word', [MeetingController::class, 'exportWord'])->name('meetings.exportWord');
    });

    Route::get('/meetings/{id}', [MeetingController::class, 'show'])->name('meetings.show');

    // ==========================================
    // PROJECT & TASK
    // ==========================================
    Route::post('/projects/{id}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
    Route::patch('/tasks/{id}/toggle', [ProjectController::class, 'toggleTask'])->name('tasks.toggle');

    Route::patch('/projects/{id}/submit-task-review', [ProjectController::class, 'submitTaskReview'])
        ->name('projects.submitTaskReview');

    Route::patch('/projects/{id}/submit-scoring', [ProjectController::class, 'submitScoring'])
        ->name('projects.submitScoring');

    Route::patch('/projects/{id}/release', [ProjectController::class, 'release'])
        ->name('projects.release');

    Route::patch('/projects/{id}/request-review', [ProjectController::class, 'requestReview'])
        ->name('projects.requestReview');

    Route::patch('/projects/{id}/request-revision', [ProjectController::class, 'requestRevision'])
        ->name('projects.requestRevision');

    Route::patch('/projects/{id}/approve', [ProjectController::class, 'approve'])
        ->name('projects.approve');

    // ⚡ FITUR BARU: Pembina menyetujui & melanjutkan TANPA menunggu ACC Ketua Media
    Route::patch('/projects/{id}/approve-override', [ProjectController::class, 'approveOverride'])
        ->name('projects.approveOverride');

    Route::patch('/projects/{id}/mark-complete', [ProjectController::class, 'markComplete'])
        ->name('projects.markComplete');

    // ⚡ NEW: Batalkan Complete (Reopen)
    Route::patch('/projects/{id}/reopen', [ProjectController::class, 'reopen'])
        ->name('projects.reopen');

    Route::patch('/projects/{id}/status', [ProjectController::class, 'updateProjectStatus'])
        ->name('projects.updateStatus');

    // PROJECT MANAGEMENT (Pembina + Ketua Media)
    Route::middleware(['divisi.access:manager'])->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::delete('/tasks/{id}', [ProjectController::class, 'destroyTask'])->name('tasks.destroy');
        Route::get('/tasks/{id}/edit', [ProjectController::class, 'editTask'])->name('tasks.edit');
        Route::put('/tasks/{id}', [ProjectController::class, 'updateTask'])->name('tasks.update');
    });

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');

    // ==========================================
    // TEAM MANAGEMENT (Pembina + Ketua Media)
    // ==========================================
    Route::middleware(['divisi.access:manager'])->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::get('/team/{id}/edit', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('/team/{id}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{id}', [TeamController::class, 'destroy'])->name('team.destroy');
    });

    // ==========================================
    // UPLOAD 3 JENIS — HARUS DI ATAS RESOURCE
    // ==========================================
    Route::get('/contents/upload-raw', [ContentController::class, 'formRaw'])->name('contents.formRaw');
    Route::post('/contents/upload-raw', [ContentController::class, 'uploadRaw'])->name('contents.uploadRaw');

    Route::get('/contents/upload-stock', [ContentController::class, 'formStock'])->name('contents.formStock');
    Route::post('/contents/upload-stock', [ContentController::class, 'uploadStock'])->name('contents.uploadStock');

    // ⚡ FASE D: halaman Activity & Raw punya tampilan sendiri (akses semua user login,
    // sedangkan aksinya dibatasi di controller).
    Route::get('/contents/activity', [ContentController::class, 'activityIndex'])->name('contents.activity');
    Route::get('/contents/raw', [ContentController::class, 'rawIndex'])->name('contents.raw');
    Route::get('/contents/{id}/lihat', [ContentController::class, 'show'])->name('contents.show');
    Route::post('/contents/{id}/ambil', [ContentController::class, 'ambilRaw'])->name('contents.ambil');
    Route::post('/contents/{id}/lepas', [ContentController::class, 'lepasRaw'])->name('contents.lepas');

    Route::get('/contents/upload-activity', [ContentController::class, 'formActivity'])->name('contents.formActivity');
    Route::post('/contents/upload-activity', [ContentController::class, 'uploadActivity'])->name('contents.uploadActivity');

    Route::get('/contents/{id}/broadcast-metadata', [ContentController::class, 'formBroadcastMetadata'])
        ->name('contents.formBroadcastMetadata');
    Route::patch('/contents/{id}/broadcast-metadata', [ContentController::class, 'fillBroadcastMetadata'])
        ->name('contents.broadcastMetadata');

    // ==========================================
    // STOCK MANAGEMENT
    // ==========================================
    // ⚡ FASE A6: edit/update/destroy dipindah keluar grup Broadcast supaya pengunggah Activity
    // (ketua divisi mana pun) bisa mengelola miliknya sendiri. Hak akses tetap dijaga di controller.
    Route::get('/contents/{id}/edit', [ContentController::class, 'edit'])->name('contents.edit');
    Route::put('/contents/{id}', [ContentController::class, 'update'])->name('contents.update');
    Route::delete('/contents/{id}', [ContentController::class, 'destroy'])->name('contents.destroy');

    Route::middleware(['divisi.access:pembina|broadcast|manager'])->group(function () {
        Route::resource('contents', ContentController::class)->except(['show', 'edit', 'update', 'destroy']);
        Route::patch('/contents/{id}/status', [ContentController::class, 'updateStatus'])->name('contents.status');

        // ⚡ Alur baru: Ready -> Sudah Diposting (dan bisa dibatalkan)
        Route::post('/contents/{id}/post', [ContentController::class, 'post'])->name('contents.post');
        Route::post('/contents/{id}/unpost', [ContentController::class, 'unpost'])->name('contents.unpost');
    });

    // ==========================================
    // MONITORING DIVISI
    // ==========================================
    Route::get('/division/{divisionName}', [DivisionController::class, 'show'])->name('division.show');
    Route::post('/division/{divisionName}/reports', [DivisionController::class, 'storeReport'])->name('division.reports.store');
    Route::delete('/division/reports/{id}', [DivisionController::class, 'destroyReport'])->name('division.reports.destroy');

    // ==========================================
    // REPORT / CETAK LAPORAN (Pembina + Ketua Media)
    // ==========================================
    Route::middleware(['divisi.access:manager'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    });

    // ==========================================
    // PROFILE
    // ==========================================
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/my-profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/my-profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/my-profile/background', [ProfileController::class, 'updateBackground'])->name('profile.updateBackground');
    Route::patch('/profile/{id}/jabatan', [ProfileController::class, 'updateJabatan'])->name('profile.updateJabatan');
    Route::post('/profile/update-avatar', [AuthController::class, 'updateProfile'])->name('profile.updateAvatar');
});