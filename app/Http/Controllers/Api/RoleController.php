<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with(['permissions', 'users'])
            ->orderByRaw("CASE WHEN slug = 'super-admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return response()->json(['data' => RoleResource::collection($roles)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
            'color'       => 'nullable|string|max:20',
        ]);

        $slug = \Str::slug($data['name']);
        if (Role::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(100, 999);
        }

        $role = Role::create([
            ...$data,
            'slug' => $slug,
            'color' => $data['color'] ?? '#6366f1',
        ]);

        // Create default permissions (all false)
        $this->seedDefaultPermissions($role);

        return response()->json(
            ['role' => new RoleResource($role->load('permissions'))],
            201
        );
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            $data = $request->validate([
                'description' => 'nullable|string|max:200',
                'color'       => 'nullable|string|max:20',
            ]);
        } else {
            $data = $request->validate([
                'name'        => 'sometimes|string|max:50',
                'description' => 'nullable|string|max:200',
                'color'       => 'nullable|string|max:20',
            ]);
        }

        $role->update($data);

        return response()->json(['role' => new RoleResource($role->load(['permissions', 'users']))]);
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->slug === 'super-admin') {
            return response()->json(['message' => 'Super Admin tidak bisa diubah.'], 403);
        }

        $request->validate([
            'permissions'              => 'required|array',
            'permissions.*.module'     => 'required|string',
            'permissions.*.can_view'   => 'boolean',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_edit'   => 'boolean',
            'permissions.*.can_delete' => 'boolean',
        ]);

        // Build a lookup map from config so we can fill module_label & section
        $modulesMeta = collect(config('permissions.modules', []))
            ->keyBy('key')
            ->all();

        foreach ($request->permissions as $perm) {
            $meta = $modulesMeta[$perm['module']] ?? null;

            Permission::updateOrCreate(
                ['role_id' => $role->id, 'module' => $perm['module']],
                [
                    'module_label' => $meta['label']   ?? $perm['module'],
                    'section'      => $meta['section'] ?? 'Lainnya',
                    'can_view'     => $perm['can_view']   ?? false,
                    'can_create'   => $perm['can_create'] ?? false,
                    'can_edit'     => $perm['can_edit']   ?? false,
                    'can_delete'   => $perm['can_delete'] ?? false,
                ]
            );
        }

        return response()->json(['role' => new RoleResource($role->load(['permissions', 'users']))]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Role sistem tidak bisa dihapus.'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Role masih digunakan oleh user.'], 422);
        }

        $role->permissions()->delete();
        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }

    private function seedDefaultPermissions(Role $role): void
    {
        $modules = config('permissions.modules', []);
        foreach ($modules as $module) {
            Permission::create([
                'role_id'      => $role->id,
                'module'       => $module['key'],
                'module_label' => $module['label'],
                'section'      => $module['section'],
                'can_view'     => false,
                'can_create'   => false,
                'can_edit'     => false,
                'can_delete'   => false,
            ]);
        }
    }
}
