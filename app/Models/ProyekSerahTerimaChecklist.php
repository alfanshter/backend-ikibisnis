<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekSerahTerimaChecklist extends Model
{
    protected $table = 'proyek_st_checklists';
    
    protected $fillable = [
        'serah_terima_id', 'text', 'status', 'catatan', 'sort_order'
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(ProyekSerahTerima::class, 'serah_terima_id');
    }
}
