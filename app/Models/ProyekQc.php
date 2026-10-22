<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyekQc extends Model
{
    protected $fillable = ['proyek_id', 'qc_pic', 'qc_tanggal', 'status', 'created_by'];

    protected $casts = [
        'qc_tanggal' => 'date:Y-m-d',
    ];

    const VALID_STATUSES = ['Draft', 'Dalam Review', 'Lulus', 'Revisi'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ProyekQcSection::class)->orderBy('sort_order');
    }

    // ── Stats helper ──────────────────────────────────────────────────────────
    public function getProgressAttribute(): int
    {
        $total   = $this->sections->flatMap->items->count();
        $checked = $this->sections->flatMap->items->where('checked', true)->count();
        return $total > 0 ? (int) round(($checked / $total) * 100) : 0;
    }
}
