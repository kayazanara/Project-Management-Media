<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Meeting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'meeting_date' => 'date',
        'attendees' => 'array',
        'decisions' => 'array',
        'action_items' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Relasi ke User (Pembuat notulen)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessor: list user yang hadir (dari array attendees)
     */
    public function getAttendeeUsersAttribute()
    {
        if (empty($this->attendees)) {
            return collect();
        }
        return User::whereIn('id', $this->attendees)->get();
    }

    /**
     * Accessor: total action items
     */
    public function getTotalActionItemsAttribute(): int
    {
        return count($this->action_items ?? []);
    }

    /**
     * Accessor: action items yang sudah selesai
     */
    public function getCompletedActionItemsAttribute(): int
    {
        $items = $this->action_items ?? [];
        return count(array_filter($items, fn($item) => ($item['status'] ?? 'pending') === 'done'));
    }

    /**
     * Scope: hanya yang published
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope: rapat yang akan datang (7 hari ke depan)
     */
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('meeting_date', '>=', now()->toDateString())
            ->where('meeting_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('meeting_date', 'asc');
    }

    /**
     * Format tanggal untuk display
     */
    public function getDateFormattedAttribute(): string
    {
        return Carbon::parse($this->meeting_date)->translatedFormat('l, d F Y');
    }
}