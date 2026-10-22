<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekProcurement extends Model
{
    protected $fillable = [
        'proyek_id',
        'nama_item',
        'kategori',
        'qty',
        'satuan',
        'harga_satuan',
        'supplier',
        'tanggal_request',
        'tanggal_diterima',
        'status',
        'catatan',
        'request_by',
        'bukti_transfer',
        'bukti_diterima',
        'created_by',
    ];

    protected $casts = [
        'tanggal_request'  => 'date:Y-m-d',
        'tanggal_diterima' => 'date:Y-m-d',
        'qty'              => 'float',
        'harga_satuan'     => 'integer',
    ];

    const VALID_KATEGORI = ['Hardware', 'Kabel', 'Aksesoris', 'Tools', 'Lainnya'];
    const VALID_STATUSES = ['Pending', 'Disetujui', 'Dipesan', 'Diterima', 'Dibatalkan'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Computed total ────────────────────────────────────────────────────────
    public function getTotalAttribute(): int
    {
        return (int) ($this->qty * $this->harga_satuan);
    }
}
