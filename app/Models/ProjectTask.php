<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Relasi ke Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relasi ke User (PIC / yang ditugaskan) — untuk backward compatibility
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relasi many-to-many ke User (Kontributor yang dapat poin)
     */
    public function contributors()
    {
        return $this->belongsToMany(User::class, 'project_task_contributors', 'project_task_id', 'user_id')
            ->withTimestamps();
    }
}