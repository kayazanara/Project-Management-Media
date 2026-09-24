<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi many-to-many ke User (Orang yang terlibat)
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Relasi ke task
     */
    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    /**
     * Accessor: Hitung progress otomatis
     * Formula: Completed Task / Total Task * 100
     * 
     * Prioritas:
     * 1. Kalau withCount sudah di-load (total_tasks_count & completed_tasks_count) → pakai itu (efisien)
     * 2. Kalau tidak → hitung manual dari relasi tasks
     */
    public function getProgressAttribute(): int
    {
        // Kalau sudah di-load via withCount (dari controller)
        if (isset($this->attributes['total_tasks_count'])) {
            $total = (int) $this->attributes['total_tasks_count'];
            $completed = (int) ($this->attributes['completed_tasks_count'] ?? 0);
        } else {
            // Fallback: hitung dari relasi
            $total = $this->tasks()->count();
            $completed = $this->tasks()->where('status', 'Completed')->count();
        }

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    /**
     * Accessor: warna progress bar
     */
    public function getProgressColorAttribute(): string
    {
        $p = $this->progress;
        if ($p >= 75) return 'success';
        if ($p >= 50) return 'info';
        if ($p >= 25) return 'warning';
        return 'danger';
    }
}