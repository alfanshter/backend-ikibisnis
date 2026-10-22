<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyekBiayaLain extends Model
{
    protected $fillable = [
        'proyek_id',
        'tanggal',
        'nama',
        'kategori',
        'nominal',
        'dibayar_oleh',
        'status',
        'keterangan',
        'bukti',
        'bukti_tipe',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nominal' => 'integer',
    ];

    const VALID_KATEGORI = [
        'BBM / Transportasi', 'Konsumsi / Makan', 'Alat & Perkakas',
        'Bahan Pendukung', 'Pengiriman / Ekspedisi', 'Parkir & Tol',
        'Komunikasi', 'Lain-lain',
    ];

    const VALID_STATUSES = ['Belum Direimburse', 'Sudah Direimburse'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
