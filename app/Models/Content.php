<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_code',
        'project_id',
        'upload_type',
        'target_division',
        'title',
        'type',
        'category',
        'content_type',
        'platform',
        'file_path',
        'file_url',
        'caption',
        'status',
        'uploaded_at',
        'broadcast_filled_at',
        // ⚡ FASE D: penanda bahan raw sudah diambil tim Editing
        'taken_at',
        'taken_by',
        // ⚡ FASE A6: pengunggah konten (supaya ketua divisi bisa edit activity-nya sendiri)
        'created_by',
    ];

    protected $casts = [
        'platform' => 'array',
        'uploaded_at' => 'datetime',
        'broadcast_filled_at' => 'datetime',
        'taken_at' => 'datetime',
    ];

    /**
     * ⚡ FASE A6: otomatis catat pengunggah konten.
     * Dipasang di model supaya SEMUA jalur upload (stok, raw, activity, tambah manual)
     * sekaligus tercatat — tanpa perlu diulang di tiap method controller.
     */
    protected static function booted(): void
    {
        static::creating(function (Content $content) {
            if (empty($content->created_by) && auth()->check()) {
                $content->created_by = auth()->id();
            }
        });
    }

    // ============================================================
    // UPLOAD TYPE CONSTANTS
    // ============================================================

    const TYPE_RAW = 'raw';
    const TYPE_CONTENT_STOCK = 'content_stock';
    const TYPE_ACTIVITY = 'activity';

    public static function allUploadTypes(): array
    {
        return [
            self::TYPE_RAW,
            self::TYPE_CONTENT_STOCK,
            self::TYPE_ACTIVITY,
        ];
    }

    // ============================================================
    // RELATIONS
    // ============================================================

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopeRaw($query)
    {
        return $query->where('upload_type', self::TYPE_RAW);
    }

    public function scopeContentStock($query)
    {
        return $query->where('upload_type', self::TYPE_CONTENT_STOCK);
    }

    public function scopeActivity($query)
    {
        return $query->where('upload_type', self::TYPE_ACTIVITY);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'Ready');
    }

    public function scopeUploaded($query)
    {
        return $query->where('status', 'Uploaded');
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    public function getIsBroadcastFilledAttribute(): bool
    {
        return !is_null($this->broadcast_filled_at);
    }

    public function getUploadTypeBadgeAttribute(): string
    {
        return match ($this->upload_type) {
            self::TYPE_RAW => 'warning',
            self::TYPE_CONTENT_STOCK => 'primary',
            self::TYPE_ACTIVITY => 'info',
            default => 'secondary',
        };
    }

    public function getUploadTypeLabelAttribute(): string
    {
        return match ($this->upload_type) {
            self::TYPE_RAW => 'Bahan Mentah',
            self::TYPE_CONTENT_STOCK => 'Stok Konten',
            self::TYPE_ACTIVITY => 'Kegiatan',
            default => 'Lain-lain',
        };
    }
}