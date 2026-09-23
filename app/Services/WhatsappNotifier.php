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
    public function projectCreatedTemplate(string $nama, string $namaProject, string $startDate, string $deadline, string $listAnggota, string $brief, int $projectId): string
    {
        $url = url("/projects/{$projectId}");

        return "🎯 *Project Baru*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Ada project baru yang perlu kamu kerjakan bersama tim:\n\n"
            . "📋 *{$namaProject}*\n"
            . "🗓️ Mulai: {$startDate}\n"
            . "⏰ Deadline: {$deadline}\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📝 *Brief*\n{$brief}\n\n"
            . "Yuk, cek detail project untuk melihat *task, pembagian tugas, dan instruksi pengerjaan*:\n\n"
            . "🔗 {$url}\n\n"
            . "Jangan lupa cek project-nya sebelum mulai mengerjakan. Semangat! 🚀";
    }

    /**
     * Template: Reminder H-3
     */
    public function reminderH3Template(string $nama, string $namaProject, string $deadline, string $listAnggota, int $progress, int $projectId): string
    {
        $url = url("/projects/{$projectId}");

        return "⏳ *Reminder Project — H-3*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Mengingatkan bahwa project berikut akan segera memasuki deadline:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📝 *Progress saat ini*\n{$progress}%\n\n"
            . "Masih ada waktu *3 hari* untuk menyelesaikannya.\n\n"
            . "Yuk, cek kembali task dan progress project agar tidak ada yang tertinggal.\n\n"
            . "🔗 {$url}";
    }

    /**
     * Template: Reminder H-1
     */
    public function reminderH1Template(string $nama, string $namaProject, string $deadline, string $listAnggota, int $progress, int $projectId): string
    {
        $url = url("/projects/{$projectId}");

        return "⚠️ *Deadline Besok!*\n\n"
            . "Assalamu'alaikum, {$nama} 👋\n\n"
            . "Project ini akan memasuki deadline *besok*:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "📊 *Progress saat ini*\n{$progress}%\n\n"
            . "Pastikan task kamu sudah selesai dan tidak ada pekerjaan yang tertinggal.\n\n"
            . "🔗 Cek project:\n{$url}\n\n"
            . "*Jangan tunggu sampai deadline lewat.*";
    }

    /**
     * Template: Overdue
     */
    public function overdueTemplate(string $nama, string $namaProject, string $deadline, int $progress, string $listAnggota, int $projectId): string
    {
        $url = url("/projects/{$projectId}");

        return "🚨 *Project Overdue*\n\n"
            . "Assalamu'alaikum, {$nama}.\n\n"
            . "Project berikut sudah melewati deadline:\n\n"
            . "📋 *{$namaProject}*\n"
            . "⏰ Deadline: *{$deadline}*\n"
            . "📊 Progress: *{$progress}%*\n"
            . "👥 Tim: {$listAnggota}\n\n"
            . "Project masih belum selesai. Mohon segera cek task yang masih terbuka dan lanjutkan pengerjaannya.\n\n"
            . "🔗 *Buka Project:*\n{$url}\n\n"
            . "Mohon segera ditindaklanjuti.";
    }
}