<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Penawaran extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'lead_id',
        'survei_id',
        'nomor_penawaran',
        'tanggal',
        'valid_until',
        'status',
        'catatan',
        'subtotal',
        'pajak',
        'diskon',
        'total_harga',
        'created_by',
    ];

    protected $casts = [
        'tanggal'     => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'subtotal'    => 'decimal:2',
        'pajak'       => 'decimal:2',
        'diskon'      => 'decimal:2',
        'total_harga' => 'decimal:2',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PenawaranItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
