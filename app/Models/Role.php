<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;

class Role extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getUserCountAttribute(): int
    {
        return $this->users()->where('status', 'aktif')->count();
    }

    public function getActivePermissionsCountAttribute(): int
    {
        return $this->permissions()
            ->where(function ($q) {
                $q->where('can_view', true)
                    ->orWhere('can_create', true)
                    ->orWhere('can_edit', true)
                    ->orWhere('can_delete', true);
            })
            ->count();
    }

    public function getTotalPermissionsCountAttribute(): int
    {
        return $this->permissions()->count() * 4; // 4 actions per module
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function can(string $module, string $action): bool
    {
        if ($this->slug === 'super-admin') {
            return true;
        }

        $permission = $this->permissions()->where('module', $module)->first();
        if (!$permission) return false;

        return match ($action) {
            'view'   => $permission->can_view,
            'create' => $permission->can_create,
            'edit'   => $permission->can_edit,
            'delete' => $permission->can_delete,
            default  => false,
        };
    }
}
