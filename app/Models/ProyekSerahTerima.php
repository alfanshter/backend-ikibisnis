<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyekSerahTerima extends Model
{
    protected $fillable = [
        'proyek_id', 'nomor_bast', 'tanggal', 'waktu', 'pic_pws_id',
        'pic_client_nama', 'pic_client_jabatan', 'status', 'hasil_pekerjaan',
        'catatan_serah_terima', 'ttd_pws', 'ttd_client', 'ttd_pws_tgl',
        'ttd_client_tgl', 'created_by'
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'ttd_pws_tgl' => 'datetime',
        'ttd_client_tgl' => 'datetime',
    ];

    public function proyek(): BelongsTo
    {
        return $this->belongsTo(Proyek::class);
    }

    public function picPws(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_pws_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ProyekSerahTerimaChecklist::class, 'serah_terima_id')->orderBy('sort_order');
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(ProyekSerahTerimaEquipment::class, 'serah_terima_id')->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProyekSerahTerimaDocument::class, 'serah_terima_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProyekSerahTerimaPhoto::class, 'serah_terima_id');
    }

    public function punchLists(): HasMany
    {
        return $this->hasMany(ProyekSerahTerimaPunchList::class, 'serah_terima_id');
    }
}
