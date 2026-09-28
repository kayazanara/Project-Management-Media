<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast "tanggal saja" (tanpa jam).
 *
 * Kenapa perlu cast sendiri?
 * Cast bawaan Laravel 'date' menyimpan nilai ke database dengan format model
 * (default 'Y-m-d H:i:s'), mis. task_date '2026-09-30' tersimpan jadi
 * '2026-09-30 00:00:00'. Akibatnya filter periode yang membandingkan dengan
 * string tanggal — whereBetween('task_date', ['2026-09-30','2026-10-04']) —
 * ikut gagal, karena secara teks '2026-09-30 00:00:00' > '2026-09-30'.
 *
 * Cast ini menyelesaikan keduanya:
 *   - get(): atribut dikembalikan sebagai objek tanggal (Carbon)
 *   - set(): disimpan sebagai 'Y-m-d' sehingga filter tetap akurat
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value ? Carbon::parse($value)->startOfDay() : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
}
