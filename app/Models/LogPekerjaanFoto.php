<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogPekerjaanFoto extends Model
{
    protected $fillable = ['log_pekerjaan_id', 'url', 'label'];

    public function log(): BelongsTo
    {
        return $this->belongsTo(LogPekerjaan::class, 'log_pekerjaan_id');
    }
}
