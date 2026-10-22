<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Survei extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'lead_id',
        'client',
        'perusahaan',
        'surveyor',
        'lokasi',
        'tanggal',
        'waktu',
        'kebutuhan',
        'estimasi',
        'status',
        'catatan',
        'rekomendasi',
        'created_by',
    ];

    protected $casts = [
        'tanggal'  => 'date:Y-m-d',
        'estimasi' => 'integer',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_DIJADWALKAN = 'Dijadwalkan';
    const STATUS_SELESAI     = 'Selesai';
    const STATUS_BATAL       = 'Batal';

    const VALID_STATUSES = [
        self::STATUS_DIJADWALKAN,
        self::STATUS_SELESAI,
        self::STATUS_BATAL,
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(SurveiCategory::class)->orderBy('urutan')->orderBy('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;
        return $query->where(function ($sub) use ($q) {
            $sub->where('client', 'like', "%{$q}%")
                ->orWhere('perusahaan', 'like', "%{$q}%")
                ->orWhere('lokasi', 'like', "%{$q}%")
                ->orWhere('surveyor', 'like', "%{$q}%");
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'semua') return $query;
        return $query->where('status', $status);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getProgressAttribute(): array
    {
        $total    = 0;
        $answered = 0;

        foreach ($this->categories as $cat) {
            foreach ($cat->questions as $q) {
                $total++;
                if (!empty($q->answer)) $answered++;
            }
        }

        return [
            'total'    => $total,
            'answered' => $answered,
            'percent'  => $total > 0 ? round(($answered / $total) * 100) : 0,
        ];
    }
}
