<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogPekerjaan extends Model
{
    use HasFactory;

    const STATUS_MENUNGGU_REVIEW = 'Menunggu Review';
    const STATUS_SESUAI = 'Sesuai';
    const STATUS_PERLU_REVISI = 'Perlu Revisi';
    const VALID_STATUS_VERIFIKASI = [
        self::STATUS_MENUNGGU_REVIEW,
        self::STATUS_SESUAI,
        self::STATUS_PERLU_REVISI,
    ];

    protected $fillable = [
        'proyek_id',
        'tanggal',
        'aktivitas',
        'tim',
        'durasi',
        'kendala',
        'status_verifikasi',
        'catatan_revisi',
        'verified_by',
        'verified_at',
        'created_by',
    ];

    protected $casts = [
        'tanggal'     => 'date:Y-m-d',
        'tim'         => 'array',
        'verified_at' => 'datetime',
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
