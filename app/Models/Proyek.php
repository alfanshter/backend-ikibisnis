<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;

class Proyek extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'kode',
        'nama',
        'client_nama',
        'perusahaan',
        'phone',
        'email',
        'kategori',
        'divisi',
        'pic_id',
        'tanggal',
        'target_selesai',
        'deskripsi',
        'nilai',
        'progress',
        'status',
        'penawaran_id',
        'lead_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal'       => 'date:Y-m-d',
        'target_selesai'=> 'date:Y-m-d',
        'nilai'         => 'integer',
        'progress'      => 'integer',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const VALID_STATUSES = [
        'Belum Dimulai', 'Berjalan', 'QC', 'Serah Terima', 'Selesai', 'Hold',
    ];

    const VALID_DIVISI = [
        'CCTV', 'Access Control', 'Fire Alarm', 'PA System',
        'Networking', 'Security System', 'Lainnya',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function penawaran(): BelongsTo
    {
        return $this->belongsTo(Penawaran::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tim(): HasMany
    {
        return $this->hasMany(ProyekTim::class);
    }

    public function logPekerjaans(): HasMany
    {
        return $this->hasMany(LogPekerjaan::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function qc(): HasOne
    {
        return $this->hasOne(ProyekQc::class);
    }

    public function serahTerima(): HasOne
    {
        return $this->hasOne(ProyekSerahTerima::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ProyekInvoice::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function feeTims(): HasMany
    {
        return $this->hasMany(ProyekFeeTim::class);
    }

    public function biayaLains(): HasMany
    {
        return $this->hasMany(ProyekBiayaLain::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(ProyekProcurement::class)->orderByDesc('tanggal_request')->orderByDesc('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;
        return $query->where(function ($sub) use ($q) {
            $sub->where('nama', 'like', "%{$q}%")
                ->orWhere('kode', 'like', "%{$q}%")
                ->orWhere('client_nama', 'like', "%{$q}%")
                ->orWhere('perusahaan', 'like', "%{$q}%")
                ->orWhere('kategori', 'like', "%{$q}%");
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'Semua') return $query;
        return $query->where('status', $status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function generateKode(): string
    {
        return \App\Services\DocumentNumberingService::generateNumber('proyek');
    }
}
