<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekTim extends Model
{
    protected $fillable = ['proyek_id', 'user_id', 'nama', 'role'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
