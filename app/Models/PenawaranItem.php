<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenawaranItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'penawaran_id',
        'part_id',
        'jenis_item',
        'pajak_type',
        'pajak_persen',
        'nama_item',
        'deskripsi',
        'qty',
        'harga_satuan',
        'total_harga',
    ];

    protected $casts = [
        'qty'          => 'integer',
        'pajak_persen' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'total_harga'  => 'decimal:2',
    ];

    public function penawaran(): BelongsTo
    {
        return $this->belongsTo(Penawaran::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
