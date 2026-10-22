<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class LeadActivity extends Model
{
    use HasFactory, Auditable;

    protected $table = 'lead_activities';

    protected $fillable = [
        'lead_id',
        'title',
        'description',
        'date',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
