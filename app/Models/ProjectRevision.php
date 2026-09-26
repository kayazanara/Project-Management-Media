<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ProjectRevision extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Relasi ke Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relasi ke User (yang menulis catatan revisi)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: hanya yang belum di-resolve
     */
    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: sudah di-resolve
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Accessor: status
     */
    public function getStatusAttribute(): string
    {
        return $this->resolved_at ? 'Resolved' : 'Open';
    }

    /**
     * Accessor: waktu relatif
     */
    public function getTimeAgoAttribute(): string
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }
}