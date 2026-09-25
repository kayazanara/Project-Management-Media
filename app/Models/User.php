<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;

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
        'bio',
        'jabatan',
        'background_image',
        'theme_color',
        'social_links',
        'total_login_days',
        'last_login_date',
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
            'social_links' => 'array',
            'last_login_date' => 'date',
        ];
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'assigned_to');
    }

    public function contributedTasks()
    {
        return $this->belongsToMany(
            ProjectTask::class,
            'project_task_contributors',
            'user_id',
            'project_task_id'
        )->withTimestamps();
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    public function getWhatsappChatIdAttribute(): ?string
    {
        return \App\Helpers\PhoneHelper::toChatId($this->phone);
    }

    public function getCompletedProjectsCountAttribute(): int
    {
        return $this->projects()->where('status', 'Completed')->count();
    }

    public function getActiveProjectsCountAttribute(): int
    {
        return $this->projects()->where('status', '!=', 'Completed')->count();
    }

    public function getCompletedTasksCountAttribute(): int
    {
        return $this->contributedTasks()->where('status', 'Completed')->count();
    }

    public function getMemberSinceDaysAttribute(): int
    {
        return (int) Carbon::parse($this->created_at)->diffInDays(now());
    }

    public function getThemeGradientAttribute(): string
    {
        $themes = [
            'blue-purple' => 'linear-gradient(135deg, #4e73df 0%, #8b5cf6 100%)',
            'ocean'       => 'linear-gradient(135deg, #06b6d4 0%, #4e73df 100%)',
            'sunset'      => 'linear-gradient(135deg, #f59f0b 0%, #d9480f 100%)',
            'emerald'     => 'linear-gradient(135deg, #1cc88a 0%, #16a34a 100%)',
            'rose'        => 'linear-gradient(135deg, #f43f5e 0%, #a855f7 100%)',
            'dark'        => 'linear-gradient(135deg, #2d3748 0%, #1a202c 100%)',
        ];

        return $themes[$this->theme_color] ?? $themes['blue-purple'];
    }

    public function trackDailyLogin(): void
    {
        $today = now()->toDateString();

        if (!$this->last_login_date || $this->last_login_date->toDateString() !== $today) {
            $this->increment('total_login_days');
            $this->update(['last_login_date' => $today]);
        }
    }
}