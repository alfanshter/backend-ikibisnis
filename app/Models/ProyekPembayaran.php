<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class ProyekPembayaran extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'proyek_id',
        'proyek_invoice_id',
        'transaction_id',
        'nomor_pembayaran',
        'bulan_ke',
        'periode_bulan',
        'tanggal_bayar',
        'nominal',
        'metode_pembayaran',
        'catatan',
        'bukti_bayar',
        'masuk_pembukuan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_bayar'   => 'date:Y-m-d',
        'nominal'         => 'integer',
        'bulan_ke'        => 'integer',
        'masuk_pembukuan' => 'boolean',
    ];

    const VALID_METODE = [
        'Transfer Bank BCA',
        'Transfer Bank Mandiri',
        'Transfer Bank BRI',
        'Transfer Bank BNI',
        'Transfer Bank Lainnya',
        'Tunai / Cash',
        'QRIS',
        'Lainnya',
    ];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProyekInvoice::class, 'proyek_invoice_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
