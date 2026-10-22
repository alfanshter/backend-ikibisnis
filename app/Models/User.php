<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'avatar_color',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function can($abilities, $arguments = []): bool
    {
        // Super admin bypass
        if ($this->role && $this->role->slug === 'super-admin') {
            return true;
        }

        if (is_string($abilities) && str_contains($abilities, '.')) {
            [$module, $action] = explode('.', $abilities, 2);
            return $this->role?->can($module, $action) ?? false;
        }

        return parent::can($abilities, $arguments);
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = array_map(fn($w) => strtoupper($w[0] ?? ''), $words);
        return implode('', array_slice($initials, 0, 2));
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'aktif';
    }
}
