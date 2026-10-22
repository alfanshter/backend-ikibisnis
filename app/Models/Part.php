<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Part extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'nama',
        'sku',
        'kategori',
        'merek',
        'spesifikasi',
        'satuan',
        'stok_min',
        'created_by',
    ];

    public function suppliers(): HasMany
    {
        return $this->hasMany(PartSupplier::class)->orderByDesc('is_primary')->orderBy('nama');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
