<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyekFeeTim extends Model
{
    protected $fillable = [
        'proyek_id',
        'user_id',
        'nama',
        'tipe',
        'role',
        'no_rekening',
        'nama_bank',
        'fee_total',
        'fee_dibayar',
        'status_bayar',
        'metode_bayar',
        'tanggal_bayar',
        'keterangan',
        'bukti_bayar',
        'created_by',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date:Y-m-d',
        'fee_total'     => 'integer',
        'fee_dibayar'   => 'integer',
    ];

    const VALID_TIPE        = ['Karyawan Tetap', 'Freelance'];
    const VALID_STATUS_BAYAR = ['Belum Dibayar', 'Sebagian', 'Lunas'];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(ProyekFeeRiwayat::class)->orderByDesc('tanggal');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Recalculate fee_dibayar and status_bayar from riwayat ─────────────────
    public function recalculate(): void
    {
        $dibayar = $this->riwayat()->sum('nominal');
        $status  = match (true) {
            $dibayar <= 0              => 'Belum Dibayar',
            $dibayar >= $this->fee_total => 'Lunas',
            default                    => 'Sebagian',
        };
        $this->update(['fee_dibayar' => $dibayar, 'status_bayar' => $status]);
    }
}
