<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekFeeRiwayat extends Model
{
    protected $fillable = [
        'proyek_fee_tim_id',
        'tanggal',
        'nominal',
        'metode',
        'keterangan',
        'bukti',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nominal' => 'integer',
    ];

    public function feeTim(): BelongsTo
    {
        return $this->belongsTo(ProyekFeeTim::class, 'proyek_fee_tim_id');
    }
}
