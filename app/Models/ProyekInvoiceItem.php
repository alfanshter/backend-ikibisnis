<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekInvoiceItem extends Model
{
    protected $fillable = [
        'proyek_invoice_id',
        'nama',
        'satuan',
        'qty',
        'harga_satuan',
        'diskon',
    ];

    protected $casts = [
        'qty'          => 'float',
        'harga_satuan' => 'integer',
        'diskon'       => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProyekInvoice::class, 'proyek_invoice_id');
    }

    // ── Computed line total (after discount) ──────────────────────────────────
    public function getLineTotalAttribute(): int
    {
        $base = $this->qty * $this->harga_satuan;
        return (int) ($base - ($base * $this->diskon / 100));
    }
}
