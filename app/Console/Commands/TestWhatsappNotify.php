<?php

namespace App\Console\Commands;

use App\Helpers\PhoneHelper;
use App\Services\WhatsappNotifier;
use Illuminate\Console\Command;

class TestWhatsappNotify extends Command
{
    protected $signature = 'wa:test {phone : Nomor WA tujuan (mis 08123456789)} {--message= : Pesan custom}';
    protected $description = 'Test kirim pesan WhatsApp via WAHA';

    public function handle(WhatsappNotifier $notifier): int
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?: "✅ Test dari Laravel — " . now()->format('d M Y H:i:s');

        $normalized = PhoneHelper::normalize($phone);
        $this->info("📱 Nomor asli: {$phone}");
        $this->info("📱 Setelah normalisasi: {$normalized}");
        $this->info("💬 Pesan: {$message}");
        $this->newLine();

        if (!$normalized) {
            $this->error('❌ Nomor tidak valid');
            return self::FAILURE;
        }

        $this->info('⏳ Mengirim...');
        $result = $notifier->send($normalized, $message);

        if ($result['success']) {
            $this->info('✅ Berhasil terkirim!');
            $this->line('Response: ' . json_encode($result['response'], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->error('❌ Gagal: ' . $result['message']);
        return self::FAILURE;
    }
}