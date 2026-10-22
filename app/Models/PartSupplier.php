<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class PartSupplier extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'part_id',
        'nama',
        'kontak',
        'harga',
        'lead_time',
        'catatan',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'harga'      => 'integer',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
