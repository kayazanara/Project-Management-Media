<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 🔁 CEK PESAN WA DOBEL (hanya membaca — tidak mengubah data apa pun).
 *
 * Dipakai kalau ada keluhan "kok notifikasi WA-nya banyak yang dobel?". Perintah ini
 * menunjukkan DARI MANA dobelnya datang, supaya perbaikannya tepat sasaran.
 *
 * Jalankan di server:
 *   php artisan notif:cek-duplikat
 *   php artisan notif:cek-duplikat --hari=30
 */
class CekDuplikatNotifikasi extends Command
{
    protected $signature = 'notif:cek-duplikat {--hari=14 : Berapa hari terakhir yang diperiksa}';

    protected $description = 'Periksa kemungkinan pesan WA dobel (hanya baca, tidak mengubah data)';

    public function handle(): int
    {
        $hari  = (int) $this->option('hari');
        $sejak = now()->subDays($hari);
        $basis = DB::table('notification_logs')->where('created_at', '>=', $sejak);

        $total = (clone $basis)->count();

        if ($total === 0) {
            $this->info("Tidak ada catatan notifikasi dalam {$hari} hari terakhir.");

            return self::SUCCESS;
        }

        $this->info("Memeriksa {$total} catatan notifikasi dalam {$hari} hari terakhir.");
        $this->newLine();

        // ── 1. Ringkasan per jenis & status ────────────────────────────────
        $this->line('1) RINGKASAN PER JENIS & STATUS');
        $ringkas = (clone $basis)->selectRaw('type, status, count(*) c')
            ->groupBy('type', 'status')->orderByDesc('c')->get();

        $this->table(
            ['Jenis', 'Status', 'Jumlah'],
            $ringkas->map(fn ($r) => [$r->type, $r->status, $r->c])->all()
        );

        // ── 2. Dobel menurut (jenis + project + akun), tanpa melihat isi pesan ──
        $this->line('2) DIKIRIM LEBIH DARI SEKALI pada jenis + project + akun yang sama');
        $dobel = (clone $basis)->selectRaw('type, project_id, user_id, count(*) c, min(created_at) pertama, max(created_at) terakhir, group_concat(distinct status) st')
            ->groupBy('type', 'project_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('c')->limit(15)->get();

        if ($dobel->isEmpty()) {
            $this->line('   (tidak ada)');
        } else {
            $this->table(
                ['Jenis', 'Project', 'Akun', 'Kali', 'Jarak (menit)', 'Status'],
                $dobel->map(function ($d) {
                    $menit = (int) round((strtotime($d->terakhir) - strtotime($d->pertama)) / 60);

                    return [$d->type, $d->project_id ?? '-', $d->user_id, $d->c, $menit, $d->st];
                })->all()
            );
            $this->line('   → Jarak 0–1 menit  = dikirim dobel cepat (klik dobel / dua proses).');
            $this->line('   → Jarak puluhan menit sampai hari = kejadian berulang (perlu dilihat jenisnya).');
        }
        $this->newLine();

        // ── 3. Satu NOMOR menerima pesan identik lebih dari sekali ─────────
        $this->line('3) SATU NOMOR menerima pesan yang SAMA lebih dari sekali');
        $nomor = (clone $basis)->selectRaw('phone, type, count(*) c, count(distinct user_id) akun, min(created_at) pertama, max(created_at) terakhir')
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->groupBy('phone', 'type', 'message')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('c')->limit(15)->get();

        if ($nomor->isEmpty()) {
            $this->line('   (tidak ada)');
        } else {
            $this->table(
                ['Nomor', 'Jenis', 'Kali', 'Akun berbeda', 'Pertama', 'Terakhir'],
                $nomor->map(fn ($n) => [
                    $n->phone, $n->type, $n->c, $n->akun,
                    substr((string) $n->pertama, 0, 16), substr((string) $n->terakhir, 0, 16),
                ])->all()
            );
            $this->line('   → Kalau "Akun berbeda" > 1: satu orang punya beberapa akun dengan nomor sama');
            $this->line('     (sudah ditangani: anti-dobel sekarang memakai NOMOR, bukan akun).');
        }
        $this->newLine();

        // ── 4. Gagal, padahal isinya sama dengan yang pernah terkirim ──────
        $this->line('4) PERNAH "GAGAL" tapi isinya sama dengan yang SUDAH terkirim');
        $gagal = (clone $basis)->where('status', 'failed')
            ->selectRaw('type, phone, message, count(*) c')
            ->groupBy('type', 'phone', 'message')->limit(10)->get()
            ->filter(function ($g) use ($sejak) {
                return DB::table('notification_logs')
                    ->where('type', $g->type)->where('phone', $g->phone)
                    ->where('message', $g->message)->where('status', 'sent')
                    ->where('created_at', '>=', $sejak)->exists();
            })->values();

        if ($gagal->isEmpty()) {
            $this->line('   (tidak ada)');
        } else {
            $this->table(
                ['Jenis', 'Nomor', 'Kali gagal'],
                $gagal->map(fn ($g) => [$g->type, $g->phone, $g->c])->all()
            );
            $this->line('   → Ini tanda percobaan gagal (timeout) yang pesannya SUDAH sampai,');
            $this->line('     lalu dicoba lagi. Sudah ditahan 1 jam oleh aturan baru.');
        }
        $this->newLine();

        // ── 5. Kirim beruntun dalam 1 menit yang sama ─────────────────────
        $this->line('5) PENGIRIMAN BERUNTUN (2 kali atau lebih dalam menit yang sama)');
        $beruntun = (clone $basis)->selectRaw("substr(created_at, 1, 16) menit, type, phone, count(*) c")
            ->groupBy('menit', 'type', 'phone')->havingRaw('count(*) > 1')
            ->orderByDesc('c')->limit(10)->get();

        if ($beruntun->isEmpty()) {
            $this->line('   (tidak ada)');
        } else {
            $this->table(
                ['Menit', 'Jenis', 'Nomor', 'Kali'],
                $beruntun->map(fn ($b) => [$b->menit, $b->type, $b->phone, $b->c])->all()
            );
        }
        $this->newLine();

        // ── 6. Jumlah per hari (melihat lonjakan) ─────────────────────────
        $this->line('6) JUMLAH PENGIRIMAN PER HARI');
        $perHari = (clone $basis)->selectRaw('substr(created_at, 1, 10) tanggal, count(*) c')
            ->groupBy('tanggal')->orderByDesc('tanggal')->limit(10)->get();

        $this->table(
            ['Tanggal', 'Jumlah'],
            $perHari->map(fn ($h) => [$h->tanggal, $h->c])->all()
        );

        // ── 7. Baris yang menggantung (belum selesai dikirim) ─────────────
        $menggantung = DB::table('notification_logs')->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(10))->count();

        if ($menggantung > 0) {
            $this->warn("7) Ada {$menggantung} baris berstatus 'pending' lebih dari 10 menit — pengirimannya tidak selesai (server berhenti di tengah proses).");
        }

        $this->newLine();
        $this->info('Selesai. Tidak ada data yang diubah.');

        return self::SUCCESS;
    }
}
