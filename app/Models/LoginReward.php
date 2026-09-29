<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ⚡ FITUR: Daily Login Reward — satu baris = satu bonus login harian.
 */
class LoginReward extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        // ⚡ PENTING: pakai cast khusus DateOnly supaya tersimpan sebagai 'Y-m-d'
        // (kalau pakai 'date' biasa, tersimpan 'Y-m-d 00:00:00' -> filter periode
        //  dengan batas tanggal jadi TIDAK COCOK. Ini bug yang sama seperti task_date dulu.)
        'reward_date' => \App\Casts\DateOnly::class,
        'points'      => 'integer',
        'streak'      => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
