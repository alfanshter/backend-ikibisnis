<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekSerahTerimaPhoto extends Model
{
    protected $table = 'proyek_st_photos';

    protected $fillable = [
        'serah_terima_id', 'kategori', 'file_url', 'caption', 'created_by'
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(ProyekSerahTerima::class, 'serah_terima_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
