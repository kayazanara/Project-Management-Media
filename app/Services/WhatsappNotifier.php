<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNotifier
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $session;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.waha.url'), '/');
        $this->apiKey = config('services.waha.api_key');
        $this->session = config('services.waha.session', 'default');
    }

    /**
     * Apakah fitur notifikasi WhatsApp (WAHA) aktif?
     * Default true supaya perilaku lama tidak berubah.
     */
    public function enabled(): bool
    {
        return (bool) config('services.waha.enabled', true);
    }

    public function send(string $phone, string $message): array
    {
        if (! $this->enabled()) {
            return ['success' => false, 'message' => 'WAHA nonaktif (WAHA_ENABLED=false)', 'response' => []];
        }

        $chatId = PhoneHelper::toChatId($phone);

        if (!$chatId) {
            return ['success' => false, 'message' => 'Nomor tidak valid', 'response' => []];
        }

        try {
            $response = Http::timeout(config('services.waha.timeout', 15))
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/sendText", [
                    'session' => $this->session,
                    'chatId' => $chatId,
                    'text' => $message,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Terkirim', 'response' => $response->json() ?? []];
            }

            return [
                'success' => false,
                'message' => "HTTP {$response->status()}: {$response->body()}",
                'response' => $response->json() ?? [],
            ];
        } catch (\Throwable $e) {
            // Service WAHA mati itu hal wajar (mis. belum dijalankan) -> warning, bukan error,
            // supaya tidak menutupi error asli di log.
            Log::warning("WAHA tidak bisa dihubungi ({$this->baseUrl}): {$e->getMessage()}", ['phone' => $phone]);

            return ['success' => false, 'message' => $e->getMessage(), 'response' => []];
        }
    }

    // ============================================================
    // ATURAN ANTI-DOBEL
    // ============================================================

    /**
     * Jenis notifikasi yang cukup dikirim SEKALI untuk selamanya.
     * Reminder (H-3/H-1/overdue) & pemberitahuan project baru tidak perlu diulang.
     *
     * ⚡ Ditambah 2 Okt 2026:
     *  - `project_approved`    → pesan ACC cukup SEKALI per project (permintaan Zev).
     *    Dikirim hanya saat KEDUA pihak ACC; kalau project dibuka ulang (reopen),
     *    catatan ini dihapus supaya ACC berikutnya tetap dikabari.
     *  - `meeting_action_item` → satu tugas cukup dikabari sekali, walau rapatnya
     *    disimpan/diubah berkali-kali (kuncinya memuat identitas tugasnya).
     */
    public const TIPE_SEKALI = [
        'reminder_h3',
        'reminder_h1',
        'overdue',
        'project_created',
        'project_approved',
        'meeting_action_item',
    ];

    /**
     * Jendela anti-dobel (menit) untuk jenis KEJADIAN (rilis, ACC, revisi, reopen, dst).
     * ⚡ PENTING: dulu semua jenis di-skip SELAMANYA kalau user itu sudah pernah menerima
     * jenis yang sama untuk project yang sama — akibatnya revisi KEDUA, pengajuan review
     * ulang, atau reopen kedua TIDAK PERNAH terkirim (pesan yang seharusnya terkirim malah
     * dibatalkan diam-diam). Sekarang jenis kejadian hanya anti-dobel dalam jendela pendek
     * (menahan klik dobel), sedangkan yang berulang tetap boleh terkirim.
     */
    public const ANTI_DOBEL_MENIT = 30;

    /**
     * 🔁 Berapa lama percobaan yang GAGAL dianggap "bekas pengiriman" (menit).
     *
     * Sebabnya: gagal menurut kita (timeout / error HTTP) BELUM TENTU tidak terkirim —
     * WhatsApp bisa saja sudah menerima pesannya. Kalau langsung dicoba lagi, orangnya
     * menerima pesan yang sama dua kali. Jadi setelah gagal, kita tahan dulu 1 jam.
     */
    public const TAHAN_GAGAL_MENIT = 60;

    /** Status baris log saat pesan sudah dicadangkan tapi belum dikirim. */
    public const STATUS_CADANGAN = 'pending';

    /**
     * Berapa menit jendela anti-dobel untuk sebuah jenis? null = permanen (sekali saja).
     */
    public static function jendelaDedupe(string $type): ?int
    {
        return in_array($type, self::TIPE_SEKALI, true) ? null : self::ANTI_DOBEL_MENIT;
    }

    // ============================================================
    // KIRIM SETELAH RESPONSE (biar halaman tidak menunggu WA)
    // ============================================================

    /**
     * ⚡ PERF: jalankan pengiriman notifikasi SETELAH halaman dikirim ke pengguna.
     *
     * Kenapa: notifikasi WA dikirim sinkron di dalam request, satu per satu per penerima.
     * Hasil ukur sebelum perbaikan (WAHA 1,5 detik/pesan):
     *   - Rilis project (6 penerima) → halaman baru pindah setelah ~9,4 detik
     *   - Ajukan review                → ~6,2 detik
     * Setelah ditunda: respons instan (±10-30 ms), WA tetap terkirim setelahnya.
     *
     * Catatan teknis: memakai terminating callback (bukan queue/afterResponse job),
     * karena QUEUE_CONNECTION aplikasi ini 'database' — kalau pakai job, tanpa worker
     * yang jalan pesannya malah TIDAK PERNAH terkirim. Terminating callback tetap
     * berjalan di proses yang sama setelah respons dikirim.
     */
    public static function kirimSetelahResponse(callable $kerja): void
    {
        if (! config('services.waha.after_response', true)) {
            $kerja();

            return;
        }

        app()->terminating(function () use ($kerja) {
            try {
                $kerja();
            } catch (\Throwable $e) {
                Log::warning('Notifikasi setelah response gagal: ' . $e->getMessage());
            }
        });
    }

    /**
     * 🔁 SIAPKAN pengiriman: cek anti-dobel + CADANGKAN baris log SEBELUM dikirim.
     *
     * Kenapa dicadangkan lebih dulu: keputusan anti-dobel harus terjadi saat aksi
     * dijalankan, bukan saat pesannya benar-benar dikirim (pengiriman ditunda sampai
     * halaman pindah). Kalau tidak, dua permintaan cepat (klik dobel / browser
     * mengirim ulang) bisa sama-sama lolos dan mengirim pesan yang sama dua kali.
     *
     * @return array{user:User,message:string,log:NotificationLog}|null  null = dilewati
     */
    public function siapkan(
        User $user,
        ?int $projectId,
        string $type,
        string $message,
        ?string $kunci = null
    ): ?array {
        if (! $this->enabled()) {
            Log::warning("Notifikasi WA dilewati (WAHA nonaktif): {$type} user={$user->id}");

            return null;
        }

        if (empty($user->phone)) {
            Log::warning("User {$user->id} tidak punya nomor WA");

            return null;
        }

        $jendela = self::jendelaDedupe($type);

        // Notifikasi ber-project ATAU berkunci: dicek per (jenis, project/kunci, NOMOR)
        if ($kunci !== null || $projectId) {
            if (NotificationLog::bekasPengiriman($type, $projectId, $user->phone, $jendela, $kunci, self::TAHAN_GAGAL_MENIT)) {
                Log::info("Skip duplikat notif: {$type} project=" . ($projectId ?? '-') . " nomor={$user->phone}"
                    . ($jendela === null ? ' (sekali saja)' : " (dalam {$jendela} menit)"));

                return null;
            }
        } elseif (NotificationLog::pesanSamaSudahTerkirim($type, $user->id, $message)) {
            // Notifikasi tanpa project & tanpa kunci (cadangan lama): dicek dari PESAN
            // yang persis sama supaya simpan-ulang tidak mengirim pesan yang sama lagi.
            Log::info("Skip notif identik (tanpa project): {$type} user={$user->id}");

            return null;
        }

        $log = NotificationLog::create([
            'user_id'    => $user->id,
            'project_id' => $projectId,
            'type'       => $type,
            'dedupe_key' => $kunci,
            'phone'      => $user->phone,
            'message'    => $message,
            'status'     => self::STATUS_CADANGAN,
            'sent_at'    => null,
        ]);

        return ['user' => $user, 'message' => $message, 'log' => $log];
    }

    /**
     * Kirim pesan yang sudah dicadangkan `siapkan()`, lalu perbarui status barisnya.
     */
    public function kirim(array $siap): bool
    {
        /** @var NotificationLog $log */
        $log    = $siap['log'];
        $user   = $siap['user'];
        $result = $this->send($user->phone, $siap['message']);

        $log->update([
            'status'   => $result['success'] ? 'sent' : 'failed',
            'response' => json_encode($result['response']),
            'sent_at'  => $result['success'] ? now() : null,
        ]);

        if ($result['success']) {
            Log::info("✅ WA terkirim: {$log->type} ke {$user->name} ({$user->phone})");
        } else {
            Log::warning("❌ WA gagal: {$log->type} ke {$user->name} ({$user->phone}): {$result['message']}");
        }

        return $result['success'];
    }

    /**
     * Siapkan + kirim sekarang juga.
     * Dipakai perintah/command (mis. `reminders:send`) dan sebagai jalur cadangan.
     */
    public function sendAndLog(User $user, ?int $projectId, string $type, string $message, ?string $kunci = null): bool
    {
        $siap = $this->siapkan($user, $projectId, $type, $message, $kunci);

        return $siap ? $this->kirim($siap) : false;
    }

    // ============================================================
    // 1. PROJECT DIBUAT
    // ============================================================

    public function projectCreatedTemplate(
        string $nama,
        string $namaProject,
        string $startDate,
        string $deadline,
        string $listAnggota,
        string $brief,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "🎯 *Ada Project Baru Nih!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Ada project baru dari *Mas Khoir* nih yang perlu segera dipersiapkan.\n\n"
            . "📋 *{$namaProject}*\n"
            . "🗓️ Mulai: {$startDate}\n"
            . "⏰ Deadline: {$deadline}\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📝 *Brief*\n{$brief}\n\n"
            . "Untuk tahap awal, coba cek dulu detail project-nya dan mulai susun *task planning*-nya bersama Ketua Divisi terkait. Tentukan apa saja yang perlu dikerjakan, pembagian tugasnya, dan target waktunya.\n\n"
            . "🔗 *Cek Detail Project*\n{$url}\n\n"
            . "Kalau planning-nya sudah siap, lanjut ajukan ke Pembina untuk dicek dan di-approve.\n\n"
            . "*— Pembina Media*";
    }

    // ============================================================
    // 2. TASK DISUSUN
    // ============================================================

    public function taskReviewSubmittedTemplate(
        string $nama,
        string $namaProject,
        int $taskCount,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "📋 *Task Sudah Disusun Nih*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Ketua Media sudah selesai menyusun task untuk project:\n\n"
            . "📌 *{$namaProject}*\n"
            . "📊 Jumlah task: *{$taskCount}*\n\n"
            . "Task-nya sudah siap untuk dicek. Mohon dibantu memberikan bobot dan approve setiap task supaya project bisa segera dirilis ke tim.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "Kalau sudah sesuai, project bisa langsung dilanjutkan ke tahap berikutnya.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 3. PROJECT DIRILIS
    // ============================================================

    public function projectReleasedTemplate(
        string $nama,
        string $namaProject,
        string $deadline,
        string $listAnggota,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "🚀 *Project Baru Sudah Bisa Dikerjakan!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Project ini sudah resmi dirilis dan siap kita kerjakan bersama:\n\n"
            . "📌 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "Task masing-masing sudah tersedia. Coba cek bagianmu dan mulai kerjakan sesuai target yang sudah ditentukan.\n\n"
            . "Jangan lupa update progress selama pengerjaan dan upload raw/bahan yang perlu diteruskan ke tim berikutnya.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "Yuk, kita mulai dari bagian masing-masing.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 4. AJUKAN REVIEW — ⚡ UPDATE: tambah $note
    // ============================================================

    public function requestReviewTemplate(
        string $nama,
        string $namaProject,
        string $divisi,
        string $note,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        // Potong note kalau kepanjangan (>150 char)
        $noteSnippet = mb_strlen($note) > 150
            ? mb_substr($note, 0, 150) . '...'
            : $note;

        return "🔍 *Project Siap Dicek Nih*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Kepala Divisi *{$divisi}* sudah mengajukan project berikut untuk direview:\n\n"
            . "📌 *{$namaProject}*\n\n"
            . "📝 *Catatan pengantar:*\n{$noteSnippet}\n\n"
            . "Hasil pengerjaannya sudah siap untuk dicek. Mohon dibantu review apakah sudah sesuai, atau berikan catatan kalau masih ada bagian yang perlu diperbaiki.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 5. REVISI DIMINTA
    // ============================================================

    public function revisionRequestedTemplate(
        string $nama,
        string $namaProject,
        string $note,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "🔄 *Ada Catatan yang Perlu Diperbaiki*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Project berikut sudah direview dan masih ada beberapa bagian yang perlu diperbaiki:\n\n"
            . "📌 *{$namaProject}*\n\n"
            . "📝 *Catatan Revisi*\n{$note}\n\n"
            . "Coba dicek kembali catatannya dan koordinasikan dengan tim untuk perbaikannya.\n\n"
            . "Kalau sudah selesai, jangan lupa ajukan review ulang ya.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "Semoga revisinya lancar dan bisa segera kita selesaikan.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 6. PROJECT DISETUJUI
    // ============================================================

    public function projectApprovedTemplate(
        string $nama,
        string $namaProject,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "✅ *Project Sudah Di-ACC!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Alhamdulillah, project berikut sudah disetujui oleh Pembina dan Ketua Media:\n\n"
            . "📌 *{$namaProject}*\n\n"
            . "Berarti pengerjaan utamanya sudah selesai dan project sekarang masuk tahap *Upload*.\n\n"
            . "Silakan koordinasikan dengan tim untuk memastikan konten yang diperlukan sudah di-upload, baik raw, stock, maupun activity.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "Tinggal selangkah lagi untuk menyelesaikan project ini.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 7. PROJECT COMPLETE
    // ============================================================

    public function projectCompletedTemplate(
        string $nama,
        string $namaProject,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "🎉 *Project Selesai!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Alhamdulillah, project berikut sudah selesai:\n\n"
            . "📌 *{$namaProject}*\n"
            . "✅ Status: *COMPLETED*\n\n"
            . "Terima kasih untuk semua yang sudah ikut mengerjakan dan menyelesaikan project ini.\n\n"
            . "Semoga hasilnya bermanfaat, menjadi amal jariyah, dan bisa menjadi bahan evaluasi untuk project-project berikutnya.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "Satu project selesai, kita lanjut ke pekerjaan berikutnya.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 8. NOTULEN RAPAT DIPUBLIKASIKAN
    // ============================================================

    public function meetingPublishedTemplate(
        string $nama,
        string $judulRapat,
        string $tanggal,
        string $waktu,
        string $lokasi,
        int $meetingId
    ): string {
        $url = url("/meetings/{$meetingId}");

        return "📝 *Ada Notulen Rapat Baru Nih*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Notulen dari rapat berikut sudah selesai dan sudah bisa dicek:\n\n"
            . "📌 *{$judulRapat}*\n"
            . "🗓️ {$tanggal}\n"
            . "⏰ {$waktu}\n"
            . "📍 {$lokasi}\n\n"
            . "Silakan cek notulen lengkapnya, terutama bagian *kesimpulan dan action items* yang perlu ditindaklanjuti.\n\n"
            . "🔗 *Buka Notulen*\n{$url}\n\n"
            . "Supaya hasil rapatnya tidak berhenti di pembahasan saja, yuk dicek bagian masing-masing.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 9. ACTION ITEM RAPAT
    // ============================================================

    public function meetingActionItemTemplate(
        string $nama,
        string $judulRapat,
        string $task,
        string $deadline,
        int $meetingId
    ): string {
        $url = url("/meetings/{$meetingId}");

        return "✅ *Ada Tugas dari Hasil Rapat Nih*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Dari hasil rapat *{$judulRapat}*, ada satu action item yang perlu kamu tindak lanjuti:\n\n"
            . "📝 *Tugas*\n{$task}\n\n"
            . "⏰ *Deadline*\n{$deadline}\n\n"
            . "Detail tugas dan konteks pembahasannya bisa kamu cek di sini:\n\n"
            . "🔗 *Cek Detail*\n{$url}\n\n"
            . "Kalau ada kendala dalam pengerjaannya, jangan ragu untuk disampaikan ya.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 10. REMINDER H-3
    // ============================================================

    public function reminderH3Template(
        string $nama,
        string $namaProject,
        string $deadline,
        string $listAnggota,
        int $progress,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "⏳ *Wah, Tinggal 3 Hari!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Mengingatkan sedikit untuk project:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📊 Progress sekarang: *{$progress}%*\n\n"
            . "Waktu kita tinggal *3 hari* lagi. Kalau masih ada task yang belum selesai, coba mulai dibereskan dari sekarang supaya nanti tidak menumpuk di akhir.\n\n"
            . "🔗 *Cek Project*\n{$url}\n\n"
            . "Yuk, dicek lagi bagian masing-masing.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 11. REMINDER H-1
    // ============================================================

    public function reminderH1Template(
        string $nama,
        string $namaProject,
        string $deadline,
        string $listAnggota,
        int $progress,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "⚠️ *Besok Sudah Deadline!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Wah, project ini besok sudah masuk deadline:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📊 Progress sekarang: *{$progress}%*\n\n"
            . "Kalau masih ada yang belum selesai, hari ini waktunya dibereskan. Coba cek lagi task masing-masing dan pastikan tidak ada bagian yang terlewat.\n\n"
            . "🔗 *Cek Project*\n{$url}\n\n"
            . "Semoga hari ini bisa kita tuntaskan dengan baik.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 12. OVERDUE
    // ============================================================

    public function overdueTemplate(
        string $nama,
        string $namaProject,
        string $deadline,
        int $progress,
        string $listAnggota,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "🚨 *Project Belum Selesai*\n\n"
            . "Assalamu'alaikum, {$nama}.\n\n"
            . "Project ini ternyata sudah melewati deadline:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "📊 Progress: *{$progress}%*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "Kalau masih ada task yang belum selesai, yuk segera dicek dan dibereskan. Jangan sampai project-nya terlalu lama tertahan.\n\n"
            . "🔗 *Cek Project*\n{$url}\n\n"
            . "Kalau ada kendala dalam pengerjaannya, jangan dipendam sendiri. Sampaikan supaya bisa kita cari solusinya.\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 13. UPLOAD RAW
    // ============================================================

    public function rawUploadedTemplate(
        string $nama,
        string $namaProject,
        string $uploaderName,
        string $fileName,
        string $targetDivision,
        int $contentId
    ): string {
        $url = url("/contents/{$contentId}");

        return "📤 *Ada Bahan Baru untuk {$targetDivision}*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Ada upload bahan mentah (*raw*) yang perlu ditindaklanjuti:\n\n"
            . "📌 Project: *{$namaProject}*\n"
            . "📁 File: {$fileName}\n"
            . "👤 Dari: {$uploaderName}\n\n"
            . "Silakan cek detailnya dan lanjutkan ke proses berikutnya.\n\n"
            . "🔗 *Cek Detail*\n{$url}\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 14. STOK KONTEN BARU
    // ============================================================

    public function stockUploadedTemplate(
        string $nama,
        string $contentCode,
        string $contentTitle,
        string $namaProject,
        int $contentId
    ): string {
        $url = url("/contents/{$contentId}");

        return "🎬 *Stok Konten Baru Nih!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Ada konten baru yang siap di-publish:\n\n"
            . "📌 *{$contentCode}* — {$contentTitle}\n"
            . "🎯 Project: {$namaProject}\n\n"
            . "Mohon dibantu isi *metadata*-nya (kategori, content type, platform, caption), supaya kontennya bisa langsung ditandai selesai.\n\n"
            . "🔗 *Cek Konten*\n{$url}\n\n"
            . "*— Mas Khoir*";
    }

    // ============================================================
    // 15. PROJECT DILANJUTKAN TANPA ACC KETUA MEDIA (override Pembina)
    // ============================================================

    public function projectOverrideApprovedTemplate(
        string $nama,
        string $namaProject,
        string $namaPembina,
        string $alasan,
        int $projectId
    ): string {
        $url = url("/projects/{$projectId}");

        return "⚡ *Project Dilanjutkan (Tanpa Menunggu ACC Ketua Media)*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Project berikut sudah disetujui dan LANGSUNG dilanjutkan ke tahap *Upload*:\n\n"
            . "📌 *{$namaProject}*\n"
            . "✍️ Dilanjutkan oleh: *{$namaPembina}* (Pembina Media)\n\n"
            . "📝 *Alasan:*\n{$alasan}\n\n"
            . "Catatan: keputusan ini diambil supaya pengerjaan tidak tertahan, jadi ACC Ketua Media\n"
            . "belum tercatat untuk project ini (tertulis \"Belum ACC\" di halaman project).\n\n"
            . "Silakan lanjutkan proses upload konten (raw/stock/activity) supaya project bisa ditutup.\n\n"
            . "🔗 *Buka Project*\n{$url}\n\n"
            . "*— Pembina Media*";
    }
}