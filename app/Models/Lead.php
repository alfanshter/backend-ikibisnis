<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Lead extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'kode_lead',
        'tanggal',
        'nama',
        'perusahaan',
        'pic_id',
        'kebutuhan',
        'sumber',
        'estimasi',
        'status',
        'phone',
        'email',
        'lokasi',
        'catatan',
        'tipe_proyek',
        'skala',
        'intensitas',
        'created_by',
    ];

    protected $casts = [
        'tanggal'  => 'date:Y-m-d',
        'estimasi' => 'integer',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_PROSPEK   = 'Prospek';
    const STATUS_SURVEY    = 'Survey';
    const STATUS_PENAWARAN = 'Penawaran';
    const STATUS_DEAL      = 'Deal';
    const STATUS_PENDING   = 'Pending';
    const STATUS_BATAL     = 'Batal';

    const VALID_STATUSES = [
        self::STATUS_PROSPEK,
        self::STATUS_SURVEY,
        self::STATUS_PENAWARAN,
        self::STATUS_DEAL,
        self::STATUS_PENDING,
        self::STATUS_BATAL,
    ];

    const VALID_SUMBER = [
        'WhatsApp', 'Referensi', 'Website', 'Instagram', 'Langsung', 'Telepon',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('date', 'asc')->orderBy('id', 'asc');
    }

    public function surveis(): HasMany
    {
        return $this->hasMany(Survei::class);
    }

    public function penawarans(): HasMany
    {
        return $this->hasMany(Penawaran::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function proyek()
    {
        return $this->hasOne(Proyek::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;

        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function ($sub) use ($q, $like) {
            $sub->where('nama', $like, "%{$q}%")
                ->orWhere('kode_lead', $like, "%{$q}%")
                ->orWhere('perusahaan', $like, "%{$q}%")
                ->orWhere('kebutuhan', $like, "%{$q}%")
                ->orWhere('sumber', $like, "%{$q}%")
                ->orWhere('phone', $like, "%{$q}%")
                ->orWhere('email', $like, "%{$q}%")
                ->orWhere('lokasi', $like, "%{$q}%")
                ->orWhereHas('picUser', function ($userQuery) use ($q, $like) {
                    $userQuery->where('name', $like, "%{$q}%");
                });
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'semua') return $query;
        return $query->where('status', $status);
    }
}
