<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'phone',
        'message',
        'status',
        'response',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Helper: cek apakah notifikasi tipe X untuk project Y ke user Z sudah pernah dikirim
    //
    // $menitTerakhir:
    //   null  → cek seumur hidup (dipakai untuk reminder: sekali kirim saja)
    //   angka → hanya dianggap duplikat kalau terkirim dalam N menit terakhir
    //           (dipakai untuk jenis KEJADIAN supaya kejadian berulang tetap terkirim,
    //            tapi klik dobel / proses dobel tidak mengirim dua kali)
    public static function alreadySent(string $type, int $projectId, int $userId, ?int $menitTerakhir = null): bool
    {
        $query = static::where('type', $type)
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('status', 'sent');

        if ($menitTerakhir !== null) {
            $query->where('created_at', '>=', now()->subMinutes($menitTerakhir));
        }

        return $query->exists();
    }

    /**
     * Cek pesan yang PERSIS SAMA sudah pernah terkirim ke user ini?
     * Dipakai untuk notifikasi tanpa project (mis. rapat & action item) supaya
     * simpan-ulang rapat tidak mengirim ulang pesan yang sama, tapi pesan yang
     * isinya berubah tetap terkirim.
     */
    public static function pesanSamaSudahTerkirim(string $type, int $userId, string $message): bool
    {
        return static::where('type', $type)
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->where('message', $message)
            ->exists();
    }
}