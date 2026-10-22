<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekQcItem extends Model
{
    protected $fillable = ['proyek_qc_section_id', 'text', 'checked', 'note', 'sort_order'];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProyekQcSection::class, 'proyek_qc_section_id');
    }
}
