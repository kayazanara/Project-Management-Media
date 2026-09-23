<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalisasi nomor WA ke format 628xxx
     *  - 08123456789     → 628123456789
     *  - +628123456789   → 628123456789
     *  - 62812-345-6789  → 628123456789
     *  - 8123456789      → 628123456789
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Format untuk WAHA chatId
     */
    public static function toChatId(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        return $normalized ? $normalized . '@c.us' : null;
    }

    /**
     * Validasi nomor Indonesia
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return false;
        }
        return preg_match('/^62[0-9]{9,13}$/', $normalized) === 1;
    }
}