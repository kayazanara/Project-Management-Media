<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--keep=10 : Jumlah backup terbaru yang disimpan (0 = simpan semua)}
                            {--dir= : Folder tujuan backup (default: database/backups)}';

    protected $description = 'Backup database SQLite pakai VACUUM INTO (aman walau DB sedang dipakai aplikasi)';

    public function handle(): int
    {
        $connection = config('database.default');

        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            $this->error("Perintah ini hanya untuk driver sqlite (driver aktif: {$connection}).");

            return self::FAILURE;
        }

        $source = DB::connection()->getDatabaseName();

        if (! File::exists($source)) {
            $this->error("File database tidak ditemukan: {$source}");

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: database_path('backups');
        File::ensureDirectoryExists($dir);

        $target = rtrim(str_replace('\\', '/', $dir), '/')
            . '/project_media-' . now()->format('Ymd-His') . '.sqlite';

        if (File::exists($target)) {
            $this->error("File tujuan sudah ada: {$target}");

            return self::FAILURE;
        }

        try {
            // VACUUM INTO = snapshot konsisten, tidak mengunci DB yang sedang dipakai
            DB::statement("VACUUM INTO '" . str_replace("'", "''", $target) . "'");
        } catch (Throwable $e) {
            $this->error('Backup gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        // Verifikasi hasil backup: harus bisa dibuka & lolos PRAGMA integrity_check
        try {
            $pdo    = new PDO('sqlite:' . $target);
            $check  = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
        } catch (Throwable $e) {
            $this->error('Backup dibuat tapi GAGAL diverifikasi: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($check !== 'ok') {
            $this->error("Verifikasi gagal (integrity_check: {$check}) — file: {$target}");

            return self::FAILURE;
        }

        $this->info("✅ Backup OK: {$target}");
        $this->line('   ukuran: ' . round(File::size($target) / 1024) . " KB | tabel: {$tables} | integrity: {$check}");

        $this->pruneOldBackups($dir);

        return self::SUCCESS;
    }

    /**
     * Simpan hanya N backup terbaru (default 10).
     */
    protected function pruneOldBackups(string $dir): void
    {
        $keep = (int) $this->option('keep');

        if ($keep <= 0) {
            return;
        }

        $files = collect(File::glob(rtrim($dir, '/\\') . '/project_media-*.sqlite'))
            ->sortDesc()
            ->values();

        if ($files->count() <= $keep) {
            return;
        }

        $files->slice($keep)->each(function (string $old) {
            File::delete($old);
            $this->line('   pruning: hapus ' . basename($old));
        });
    }
}
