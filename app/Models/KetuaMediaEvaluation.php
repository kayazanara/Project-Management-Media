<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ⚡ FITUR BARU: Catatan Evaluasi Pembina untuk Ketua Media.
 *
 * Satu baris = satu catatan penilaian yang ditulis Pembina pada satu periode.
 * `score` & `rating` adalah SNAPSHOT skor kinerja saat catatan dibuat, supaya
 * isi catatan tetap konsisten walaupun data di belakangnya bergerak.
 */
class KetuaMediaEvaluation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        // ⚡ WAJIB DateOnly ('Y-m-d'), BUKAN 'date' — kalau 'date', nilainya tersimpan
        // sebagai 'Y-m-d 00:00:00' dan filter periode jadi tidak cocok
        // (bug kelas yang sama dengan task_date & reward_date sebelumnya).
        'period_start' => DateOnly::class,
        'period_end'   => DateOnly::class,
        'score'        => 'integer',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /** Ketua Media yang dinilai */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Pembina yang menulis catatan */
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
