<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekSerahTerimaPunchList extends Model
{
    protected $table = 'proyek_st_punch_lists';

    protected $fillable = [
        'serah_terima_id', 'temuan', 'pic', 'deadline', 'status', 'catatan'
    ];

    protected $casts = [
        'deadline' => 'date:Y-m-d',
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(ProyekSerahTerima::class, 'serah_terima_id');
    }
}
