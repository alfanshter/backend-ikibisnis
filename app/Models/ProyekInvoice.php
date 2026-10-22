<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyekInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'proyek_id',
        'nomor_invoice',
        'tanggal',
        'jatuh_tempo',
        'perihal',
        'catatan',
        'ppn',
        'tipe',
        'nomor_termin',
        'status',
        'subtotal',
        'total_harga',
        'created_by',
    ];

    protected $casts = [
        'tanggal'     => 'date:Y-m-d',
        'jatuh_tempo' => 'date:Y-m-d',
        'subtotal'    => 'integer',
        'total_harga' => 'integer',
        'ppn'         => 'integer',
    ];

    const VALID_STATUSES = ['Draft', 'Terkirim', 'Lunas', 'Jatuh Tempo'];
    const VALID_TIPE     = ['DP', 'Termin', 'Pelunasan', 'Lainnya'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProyekInvoiceItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Recalculate totals from items ─────────────────────────────────────────
    public function recalculate(): void
    {
        $subtotal = $this->items->sum(function ($item) {
            $base = $item->qty * $item->harga_satuan;
            return $base - ($base * $item->diskon / 100);
        });
        $ppnAmount = $subtotal * ($this->ppn / 100);

        $this->subtotal    = (int) $subtotal;
        $this->total_harga = (int) ($subtotal + $ppnAmount);
        $this->saveQuietly();
    }
}
