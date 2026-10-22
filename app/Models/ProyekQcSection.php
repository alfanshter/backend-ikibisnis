<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyekQcSection extends Model
{
    protected $fillable = ['proyek_qc_id', 'title', 'sort_order'];

    public function qc(): BelongsTo
    {
        return $this->belongsTo(ProyekQc::class, 'proyek_qc_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProyekQcItem::class, 'proyek_qc_section_id')->orderBy('sort_order');
    }
}
