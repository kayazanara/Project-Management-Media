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
        'title',
        'type',
        'platform',
        'file_path',
        'file_url',
        'caption',
        'status',
        'uploaded_at',
    ];

    protected $casts = [
        'platform' => 'array', // Otomatis mengubah array menjadi JSON di database dan sebaliknya
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}