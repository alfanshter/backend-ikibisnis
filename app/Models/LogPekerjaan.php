<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogPekerjaan extends Model
{
    use HasFactory;

    protected $fillable = [
        'proyek_id',
        'tanggal',
        'aktivitas',
        'tim',
        'durasi',
        'kendala',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'tim'     => 'array',
    ];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(LogPekerjaanFoto::class, 'log_pekerjaan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
