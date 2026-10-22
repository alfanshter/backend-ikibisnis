<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'label',
        'amount',
        'date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date'   => 'date:Y-m-d',
        'amount' => 'integer',
    ];

    // ── Type constants ─────────────────────────────────────────────────────────
    const TYPE_ASET_LANCAR        = 'aset_lancar';
    const TYPE_ASET_TETAP         = 'aset_tetap';
    const TYPE_LIABILITAS_PENDEK  = 'liabilitas_pendek';
    const TYPE_LIABILITAS_PANJANG = 'liabilitas_panjang';
    const TYPE_EKUITAS            = 'ekuitas';

    const VALID_TYPES = [
        self::TYPE_ASET_LANCAR,
        self::TYPE_ASET_TETAP,
        self::TYPE_LIABILITAS_PENDEK,
        self::TYPE_LIABILITAS_PANJANG,
        self::TYPE_EKUITAS,
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────
    public function scopeAsetLancar($query)
    {
        return $query->where('type', self::TYPE_ASET_LANCAR);
    }

    public function scopeAsetTetap($query)
    {
        return $query->where('type', self::TYPE_ASET_TETAP);
    }

    public function scopeLiabilitasPendek($query)
    {
        return $query->where('type', self::TYPE_LIABILITAS_PENDEK);
    }

    public function scopeLiabilitasPanjang($query)
    {
        return $query->where('type', self::TYPE_LIABILITAS_PANJANG);
    }

    public function scopeEkuitas($query)
    {
        return $query->where('type', self::TYPE_EKUITAS);
    }
}
