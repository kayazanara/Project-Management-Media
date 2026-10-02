<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'dedupe_key',
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

    /**
     * 🔁 INTI ANTI-DOBEL (diperbaiki 2 Okt 2026).
     *
     * Dianggap "sudah pernah dikirim" kalau ada baris yang cocok pada salah satu kondisi:
     *   1. status `sent`   (sudah benar-benar terkirim) — dalam jendela waktu bila ditentukan
     *   2. status `pending` (sedang dalam proses) — mencegah klik dobel / dua permintaan cepat
     *   3. status `failed` yang MASIH BARU (< $tahanGagalMenit) — karena gagal (timeout)
     *      belum tentu tidak terkirim: WhatsApp bisa saja sudah menerima pesannya
     *
     * Penocokan memakai NOMOR (`phone`), bukan akun, supaya dua akun dengan nomor yang
     * sama tidak menerima pesan yang sama dua kali.
     *
     * @param string|null $kunci  kunci khusus (mis. "rapat:12:tugas:abc") — kalau diisi,
     *                            dipakai menggantikan project_id
     */
    public static function bekasPengiriman(
        string $type,
        ?int $projectId,
        string $phone,
        ?int $menitTerakhir = null,
        ?string $kunci = null,
        int $tahanGagalMenit = 60
    ): bool {
        $query = static::where('type', $type)->where('phone', $phone);

        if ($kunci !== null) {
            $query->where('dedupe_key', $kunci);
        } else {
            $query->where('project_id', $projectId);
        }

        return $query->where(function ($w) use ($menitTerakhir, $tahanGagalMenit) {
            $w->where(function ($s) use ($menitTerakhir) {
                $s->where('status', 'sent');

                if ($menitTerakhir !== null) {
                    $s->where('created_at', '>=', now()->subMinutes($menitTerakhir));
                }
            })
            ->orWhere('status', 'pending')
            ->orWhere(function ($f) use ($tahanGagalMenit) {
                $f->where('status', 'failed')
                  ->where('created_at', '>=', now()->subMinutes($tahanGagalMenit));
            });
        })->exists();
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