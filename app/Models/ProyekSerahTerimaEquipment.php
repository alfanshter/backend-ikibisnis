<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekSerahTerimaEquipment extends Model
{
    protected $table = 'proyek_st_equipments';

    protected $fillable = [
        'serah_terima_id', 'nama_item', 'qty', 'unit', 'sn', 'kondisi', 'catatan', 'sort_order'
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(ProyekSerahTerima::class, 'serah_terima_id');
    }
}
