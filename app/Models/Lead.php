<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Lead extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'kode_lead',
        'tanggal',
        'nama',
        'perusahaan',
        'pic_id',
        'kebutuhan',
        'sumber',
        'estimasi',
        'status',
        'phone',
        'email',
        'lokasi',
        'catatan',
        'tipe_proyek',
        'skala',
        'intensitas',
        'tipe_kontrak',
        'sewa_durasi_bulan',
        'skema_pembayaran',
        'sewa_mulai',
        'sewa_selesai',
        'nominal_per_bulan',
        'akses_tipe',
        'allowed_roles',
        'assigned_users',
        'created_by',
    ];

    protected $casts = [
        'tanggal'           => 'date:Y-m-d',
        'estimasi'          => 'integer',
        'sewa_durasi_bulan' => 'integer',
        'nominal_per_bulan' => 'integer',
        'sewa_mulai'        => 'date:Y-m-d',
        'sewa_selesai'      => 'date:Y-m-d',
        'allowed_roles'     => 'array',
        'assigned_users'    => 'array',
    ];

    // ── Akses constants ───────────────────────────────────────────────────────
    const VALID_AKSES_TIPE = ['personal', 'role', 'semua'];

    // ── Kontrak constants ─────────────────────────────────────────────────────
    const VALID_TIPE_KONTRAK = ['Langsung', 'Sewa'];
    const VALID_SKEMA_PEMBAYARAN = ['Bulanan', 'Sekaligus'];

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_PROSPEK   = 'Prospek';
    const STATUS_SURVEY    = 'Survey';
    const STATUS_PENAWARAN = 'Penawaran';
    const STATUS_DEAL      = 'Deal';
    const STATUS_PENDING   = 'Pending';
    const STATUS_BATAL     = 'Batal';

    const VALID_STATUSES = [
        self::STATUS_PROSPEK,
        self::STATUS_SURVEY,
        self::STATUS_PENAWARAN,
        self::STATUS_DEAL,
        self::STATUS_PENDING,
        self::STATUS_BATAL,
    ];

    const VALID_SUMBER = [
        'WhatsApp', 'Referensi', 'Website', 'Instagram', 'Langsung', 'Telepon',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('date', 'asc')->orderBy('id', 'asc');
    }

    public function surveis(): HasMany
    {
        return $this->hasMany(Survei::class);
    }

    public function penawarans(): HasMany
    {
        return $this->hasMany(Penawaran::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function proyek()
    {
        return $this->hasOne(Proyek::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;

        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function ($sub) use ($q, $like) {
            $sub->where('nama', $like, "%{$q}%")
                ->orWhere('kode_lead', $like, "%{$q}%")
                ->orWhere('perusahaan', $like, "%{$q}%")
                ->orWhere('kebutuhan', $like, "%{$q}%")
                ->orWhere('sumber', $like, "%{$q}%")
                ->orWhere('phone', $like, "%{$q}%")
                ->orWhere('email', $like, "%{$q}%")
                ->orWhere('lokasi', $like, "%{$q}%")
                ->orWhereHas('picUser', function ($userQuery) use ($q, $like) {
                    $userQuery->where('name', $like, "%{$q}%");
                });
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status || $status === 'semua') return $query;
        return $query->where('status', $status);
    }

    public function scopeByTipeKontrak($query, ?string $tipe)
    {
        if (!$tipe || $tipe === 'semua') return $query;
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
              ->orWhereJsonContains('assigned_users', (int) $userId)
              ->orWhereJsonContains('assigned_users', (string) $userId);

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
        if (!empty($this->assigned_users)) {
            $assigned = (array) $this->assigned_users;
            if (in_array($user->id, $assigned) || in_array((string)$user->id, $assigned)) {
                return true;
            }
        }
        if ($this->akses_tipe === 'role' && !empty($this->allowed_roles)) {
            $allowed = (array) $this->allowed_roles;
            if (in_array($user->role_id, $allowed) || in_array((string)$user->role_id, $allowed) || ($user->role && in_array($user->role->slug, $allowed))) {
                return true;
            }
        }
        return false;
    }
}
