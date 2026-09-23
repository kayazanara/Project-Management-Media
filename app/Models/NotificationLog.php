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
    public static function alreadySent(string $type, int $projectId, int $userId): bool
    {
        return static::where('type', $type)
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->exists();
    }
}