<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'division',
        'status',
        'avatar',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Task yang ditugaskan ke user ini (PIC / assigned_to)
     */
    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'assigned_to');
    }

    /**
     * Task di mana user ini menjadi KONTRIBUTOR (untuk hitung poin)
     * Relasi many-to-many via tabel pivot project_task_contributors
     */
    public function contributedTasks()
    {
        return $this->belongsToMany(
            ProjectTask::class,
            'project_task_contributors',
            'user_id',
            'project_task_id'
        )->withTimestamps();
    }

    /**
     * Project yang diikuti user ini (many-to-many)
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    /**
     * Accessor: nomor WA siap pakai untuk WAHA
     */
    public function getWhatsappChatIdAttribute(): ?string
    {
        return \App\Helpers\PhoneHelper::toChatId($this->phone);
    }
}