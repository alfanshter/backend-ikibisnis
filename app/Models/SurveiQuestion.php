<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class SurveiQuestion extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'survei_id',
        'category_id',
        'label',
        'answer',
        'notes',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(SurveiCategory::class, 'category_id');
    }

    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class);
    }
}
