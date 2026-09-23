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
     * Kirim pesan WA ke 1 nomor.
     * Return: ['success' => bool, 'message' => string, 'response' => array]
     */
    public function send(string $phone, string $message): array
    {
        $chatId = PhoneHelper::toChatId($phone);

        if (!$chatId) {
            return [
                'success' => false,
                'message' => 'Nomor tidak valid',
                'response' => [],
            ];
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
                return [
                    'success' => true,
                    'message' => 'Terkirim',
                    'response' => $response->json() ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => "HTTP {$response->status()}: {$response->body()}",
                'response' => $response->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error("WAHA send failed: {$e->getMessage()}", [
                'phone' => $phone,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response' => [],
            ];
        }
    }

    /**
     * Kirim & log otomatis
     */
    public function sendAndLog(User $user, ?int $projectId, string $type, string $message): bool
    {
        if (empty($user->phone)) {
            Log::warning("User {$user->id} tidak punya nomor WA");
            return false;
        }

        // Cek duplikat
        if ($projectId && NotificationLog::alreadySent($type, $projectId, $user->id)) {
            Log::info("Skip duplikat notif: {$type} project={$projectId} user={$user->id}");
            return true;
        }

        $result = $this->send($user->phone, $message);

        NotificationLog::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'type' => $type,
            'phone' => $user->phone,
            'message' => $message,
            'status' => $result['success'] ? 'sent' : 'failed',
            'response' => json_encode($result['response']),
            'sent_at' => $result['success'] ? now() : null,
        ]);

        return $result['success'];
    }

    // ============================================================
    // TEMPLATE PESAN
    // ============================================================

    /**
     * Template: Project Baru
     */
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
            . "Ada project baru dari *Mas Khoir* nih. Kamu termasuk dalam tim yang akan mengerjakannya.\n\n"
            . "📋 *{$namaProject}*\n"
            . "🗓️ Mulai: {$startDate}\n"
            . "⏰ Deadline: {$deadline}\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📝 *Brief*\n{$brief}\n\n"
            . "Sebelum mulai, coba cek dulu detail project-nya ya. Di sana sudah ada pembagian tugas, brief, dan hal-hal yang perlu diperhatikan.\n\n"
            . "🔗 *Cek Detail Project*\n{$url}\n\n"
            . "Semoga pengerjaannya lancar dan hasilnya sesuai yang kita harapkan.\n\n"
            . "*— Mas Khoir*";
    }

    /**
     * Template: Reminder H-3
     */
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

    /**
     * Template: Reminder H-1
     */
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

    /**
     * Template: Overdue
     */
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
}