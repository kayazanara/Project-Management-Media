<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
        'completed_at' => 'datetime',
        'scored_at' => 'datetime',
        'released_at' => 'datetime',
        'approved_by_pembina_at' => 'datetime',
        'approved_by_ketua_at' => 'datetime',
        'is_overdue' => 'boolean',
        // ⚡ FITUR: penanda untuk mengukur kecepatan alur Ketua Media
        'task_submitted_at' => 'datetime',
        'review_requested_at' => 'datetime',
    ];

    // ============================================================
    // STATUS WORKFLOW CONSTANTS
    // ============================================================

    const STATUS_PLANNING = 'Planning';
    const STATUS_TASK_REVIEW = 'Task Review';
    const STATUS_SCORING = 'Scoring';
    const STATUS_ON_PROGRESS = 'On Progress';
    const STATUS_REVIEW = 'Review';
    const STATUS_REVISION = 'Revision';
    const STATUS_APPROVED = 'Approved';
    const STATUS_UPLOAD = 'Upload';
    const STATUS_COMPLETED = 'Completed';

    /**
     * Semua status valid
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_PLANNING,
            self::STATUS_TASK_REVIEW,
            self::STATUS_SCORING,
            self::STATUS_ON_PROGRESS,
            self::STATUS_REVIEW,
            self::STATUS_REVISION,
            self::STATUS_APPROVED,
            self::STATUS_UPLOAD,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Aturan transisi status
     * Key = status sekarang, Value = array status yang diperbolehkan
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_PLANNING      => [self::STATUS_TASK_REVIEW],
            self::STATUS_TASK_REVIEW   => [self::STATUS_SCORING],
            self::STATUS_SCORING       => [self::STATUS_ON_PROGRESS],
            self::STATUS_ON_PROGRESS   => [self::STATUS_REVIEW],
            self::STATUS_REVIEW        => [self::STATUS_REVISION, self::STATUS_APPROVED],
            self::STATUS_REVISION      => [self::STATUS_REVIEW],
            self::STATUS_APPROVED      => [self::STATUS_UPLOAD],
            self::STATUS_UPLOAD        => [self::STATUS_COMPLETED],
            self::STATUS_COMPLETED     => [],
        ];
    }

    /**
     * Cek apakah bisa transisi dari status sekarang ke $newStatus
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::allowedTransitions()[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    // ============================================================
    // RELATIONS
    // ============================================================

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function revisions()
    {
        return $this->hasMany(ProjectRevision::class)->orderBy('created_at', 'desc');
    }

    public function openRevisions()
    {
        return $this->hasMany(ProjectRevision::class)->whereNull('resolved_at');
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    public function getProgressAttribute(): int
    {
        if (isset($this->attributes['total_tasks_count'])) {
            $total = (int) $this->attributes['total_tasks_count'];
            $completed = (int) ($this->attributes['completed_tasks_count'] ?? 0);
        } else {
            $total = $this->tasks()->count();
            $completed = $this->tasks()->where('status', 'Completed')->count();
        }

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    public function getProgressColorAttribute(): string
    {
        $p = $this->progress;
        if ($p >= 75) return 'success';
        if ($p >= 50) return 'info';
        if ($p >= 25) return 'warning';
        return 'danger';
    }

    /**
     * Cek apakah deadline lewat & belum completed
     */
    public function getIsActuallyOverdueAttribute(): bool
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }
        return $this->deadline && $this->deadline->isPast();
    }

    /**
     * Accessor: badge color status
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNING     => 'secondary',
            self::STATUS_TASK_REVIEW  => 'info',
            self::STATUS_SCORING      => 'primary',
            self::STATUS_ON_PROGRESS  => 'warning',
            self::STATUS_REVIEW       => 'info',
            self::STATUS_REVISION     => 'danger',
            self::STATUS_APPROVED     => 'success',
            self::STATUS_UPLOAD       => 'primary',
            self::STATUS_COMPLETED    => 'success',
            default                   => 'secondary',
        };
    }

    /**
     * Accessor: apakah sudah di-ACC kedua pihak
     */
    public function getIsFullyApprovedAttribute(): bool
    {
        return $this->approved_by_pembina_at && $this->approved_by_ketua_at;
    }
}