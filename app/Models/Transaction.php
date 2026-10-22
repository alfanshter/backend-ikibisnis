<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Transaction extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'type',
        'name',
        'date',
        'category',
        'proyek_id',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'integer',
    ];

    /**
     * Scope a query to only include masuk transactions.
     */
    public function scopeMasuk($query)
    {
        return $query->where('type', 'masuk');
    }

    /**
     * Scope a query to only include keluar transactions.
     */
    public function scopeKeluar($query)
    {
        return $query->where('type', 'keluar');
    }

    /**
     * Get the user that created the transaction.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
