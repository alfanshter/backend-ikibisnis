<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;

class Proyek extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'kode',
        'nama',
        'client_nama',
        'perusahaan',
        'phone',
        'email',
        'kategori',
        'divisi',
        'tipe_kontrak',
        'sewa_mulai',
        'sewa_selesai',
        'sewa_durasi_bulan',
        'skema_pembayaran',
        'nominal_per_bulan',
        'status_sewa',
        'pic_id',
        'tanggal',
        'target_selesai',
        'deskripsi',
        'nilai',
        'progress',
        'status',
        'status_pembayaran',
        'akses_tipe',
        'allowed_roles',
        'penawaran_id',
        'lead_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal'           => 'date:Y-m-d',
        'target_selesai'    => 'date:Y-m-d',
        'sewa_mulai'        => 'date:Y-m-d',
        'sewa_selesai'      => 'date:Y-m-d',
        'sewa_durasi_bulan' => 'integer',
        'nominal_per_bulan' => 'integer',
        'nilai'             => 'integer',
        'progress'          => 'integer',
        'allowed_roles'     => 'array',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const VALID_STATUSES = [
        'Belum Dimulai', 'Berjalan', 'QC', 'Serah Terima', 'Selesai', 'Hold',
    ];

    const VALID_DIVISI = [
        'CCTV', 'Access Control', 'Fire Alarm', 'PA System',
        'Networking', 'Security System', 'Lainnya',
    ];

    const VALID_TIPE_KONTRAK = ['Reguler', 'Sewa'];
    const VALID_SKEMA_PEMBAYARAN = ['Bulanan', 'Sekaligus', 'Termin'];
    const VALID_STATUS_SEWA = ['Aktif', 'Mendekati Jatuh Tempo', 'Kadaluarsa', 'Selesai', 'Diperpanjang'];
    const VALID_STATUS_PEMBAYARAN = ['Belum Dibayar', 'Sebagian', 'Lunas'];
    const VALID_AKSES_TIPE = ['personal', 'role', 'semua'];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function penawaran(): BelongsTo
    {
        return $this->belongsTo(Penawaran::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tim(): HasMany
    {
        return $this->hasMany(ProyekTim::class);
    }

    public function logPekerjaans(): HasMany
    {
        return $this->hasMany(LogPekerjaan::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function qc(): HasOne
    {
        return $this->hasOne(ProyekQc::class);
    }

    public function serahTerima(): HasOne
    {
        return $this->hasOne(ProyekSerahTerima::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ProyekInvoice::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function feeTims(): HasMany
    {
        return $this->hasMany(ProyekFeeTim::class);
    }

    public function biayaLains(): HasMany
    {
        return $this->hasMany(ProyekBiayaLain::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function perpanjanganSewas(): HasMany
    {
        return $this->hasMany(ProyekSewaPerpanjangan::class)->orderByDesc('tanggal_perpanjangan')->orderByDesc('id');
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(ProyekProcurement::class)->orderByDesc('tanggal_request')->orderByDesc('id');
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(ProyekPembayaran::class)->orderByDesc('tanggal_bayar')->orderByDesc('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;
        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function ($sub) use ($q, $like) {
            $sub->where('nama', $like, "%{$q}%")
                ->orWhere('kode', $like, "%{$q}%")
                ->orWhere('client_nama', $like, "%{$q}%")
                ->orWhere('perusahaan', $like, "%{$q}%")
                ->orWhere('kategori', $like, "%{$q}%")
                ->orWhere('tipe_kontrak', $like, "%{$q}%");
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'Semua') return $query;
        return $query->where('status', $status);
    }

    public function scopeByTipeKontrak($query, ?string $tipe)
    {
        if (!$tipe || $tipe === 'Semua') return $query;
        return $query->where('tipe_kontrak', $tipe);
    }

    public function scopeAccessibleBy($query, ?User $user)
    {
        if (!$user) return $query;

        // Super Admin and Admin bypass all restrictions
        if ($user->role && in_array($user->role->slug, ['super-admin', 'admin'])) {
            return $query;
        }

        $userId = $user->id;
        $roleId = $user->role_id;
        $roleSlug = $user->role?->slug;

        return $query->where(function ($q) use ($userId, $roleId, $roleSlug) {
            $q->where('akses_tipe', 'semua')
              ->orWhereNull('akses_tipe')
              ->orWhere('pic_id', $userId)
              ->orWhere('created_by', $userId)
              ->orWhereHas('tim', function ($timQ) use ($userId) {
                  $timQ->where('user_id', $userId);
              });

            if ($roleId || $roleSlug) {
                $q->orWhere(function ($roleQ) use ($roleId, $roleSlug) {
                    $roleQ->where('akses_tipe', 'role')
                          ->where(function ($sub) use ($roleId, $roleSlug) {
                              if ($roleId) {
                                  $sub->whereJsonContains('allowed_roles', (int) $roleId)
                                      ->orWhereJsonContains('allowed_roles', (string) $roleId);
                              }
                              if ($roleSlug) {
                                  $sub->orWhereJsonContains('allowed_roles', $roleSlug);
                              }
                          });
                });
            }
        });
    }

    public function isAccessibleBy(?User $user): bool
    {
        if (!$user) return false;
        if ($user->role && in_array($user->role->slug, ['super-admin', 'admin'])) {
            return true;
        }
        if ($this->akses_tipe === 'semua' || empty($this->akses_tipe)) {
            return true;
        }
        if ($this->pic_id === $user->id || $this->created_by === $user->id) {
            return true;
        }
        if ($this->tim()->where('user_id', $user->id)->exists()) {
            return true;
        }
        if ($this->akses_tipe === 'role' && !empty($this->allowed_roles)) {
            $allowed = (array) $this->allowed_roles;
            if (in_array($user->role_id, $allowed) || in_array((string)$user->role_id, $allowed) || ($user->role && in_array($user->role->slug, $allowed))) {
                return true;
            }
        }
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function generateKode(): string
    {
        return \App\Services\DocumentNumberingService::generateNumber('proyek');
    }

    public function syncStatusPembayaran(): string
    {
        $totalPaid = (int) $this->pembayarans()->sum('nominal');
        $nilai = (int) ($this->nilai ?? 0);

        if ($nilai > 0 && $totalPaid >= $nilai) {
            $status = 'Lunas';
        } elseif ($totalPaid > 0) {
            $status = 'Sebagian';
        } else {
            $status = 'Belum Dibayar';
        }

        $this->update(['status_pembayaran' => $status]);
        return $status;
    }
}
