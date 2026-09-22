<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke User (Pembuat Laporan / Kepala Divisi)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Project (Opsional)
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}