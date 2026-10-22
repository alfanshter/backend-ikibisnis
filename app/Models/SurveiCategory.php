<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class SurveiCategory extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'survei_id',
        'name',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveiQuestion::class, 'category_id')
                    ->orderBy('urutan')
                    ->orderBy('id');
    }
}
