<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        // ⚡ FIX: task_date di-cast ke objek tanggal (DateOnly) supaya tidak lagi berupa
        // string mentah, TAPI tetap disimpan di DB sebagai 'Y-m-d' (lihat App\Casts\DateOnly)
        // agar filter periode/whereBetween dengan string tanggal tetap akurat.
        'task_date' => DateOnly::class,
        'completed_at' => 'datetime',
        'scored_at' => 'datetime',
        'is_approved' => 'boolean',
    ];

    /**
     * Relasi ke Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relasi ke User (PIC / yang ditugaskan)
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

    /**
     * Scope: sudah di-scoring Pembina
     */
    public function scopeScored($query)
    {
        return $query->whereNotNull('scored_at');
    }

    /**
     * Scope: sudah di-approve
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}