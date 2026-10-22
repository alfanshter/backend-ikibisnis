<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekSerahTerimaDocument extends Model
{
    protected $table = 'proyek_st_documents';

    protected $fillable = [
        'serah_terima_id', 'nama_dokumen', 'file_url', 'created_by'
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
