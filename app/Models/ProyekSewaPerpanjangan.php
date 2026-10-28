<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class ProyekSewaPerpanjangan extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'proyek_id',
        'tanggal_perpanjangan',
        'sewa_mulai_lama',
        'sewa_selesai_lama',
        'sewa_mulai_baru',
        'sewa_selesai_baru',
        'durasi_bulan',
        'nominal',
        'skema_pembayaran',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_perpanjangan' => 'date:Y-m-d',
        'sewa_mulai_lama'      => 'date:Y-m-d',
        'sewa_selesai_lama'     => 'date:Y-m-d',
        'sewa_mulai_baru'      => 'date:Y-m-d',
        'sewa_selesai_baru'     => 'date:Y-m-d',
        'durasi_bulan'         => 'integer',
        'nominal'              => 'integer',
    ];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
