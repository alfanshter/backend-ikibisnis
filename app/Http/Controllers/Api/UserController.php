<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const AVATAR_COLORS = [
        '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6',
        '#ef4444', '#06b6d4', '#14b8a6', '#f97316',
        '#ec4899', '#84cc16',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = User::with('role')
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q2) =>
                    $q2->where('name', 'ilike', "%{$s}%")
                        ->orWhere('email', 'ilike', "%{$s}%")
                )
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->role_id, fn($q, $r) => $q->where('role_id', $r))
            ->orderBy('created_at', 'desc');

        $users = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data'  => UserResource::collection($users->items()),
            'meta'  => [
                'total'       => $users->total(),
                'aktif'       => User::where('status', 'aktif')->count(),
                'nonaktif'    => User::where('status', 'nonaktif')->count(),
                'current_page'=> $users->currentPage(),
                'last_page'   => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|exists:roles,id',
            'status'   => 'in:aktif,nonaktif',
        ]);

        $colorIndex = User::count() % count(self::AVATAR_COLORS);
        $user = User::create([
            ...$data,
            'avatar_color' => self::AVATAR_COLORS[$colorIndex],
            'created_by'   => $request->user()->id,
        ]);

        return response()->json(
            ['user' => new UserResource($user->load('role'))],
            201
        );
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => new UserResource($user->load('role'))]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:6',
            'role_id'  => 'sometimes|exists:roles,id',
            'status'   => 'sometimes|in:aktif,nonaktif',
        ]);

        // Only Super Admin can edit Super Admin
        if ($user->role?->slug === 'super-admin' && $request->user()->role?->slug !== 'super-admin') {
            return response()->json(['message' => 'Tidak ada akses.'], 403);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['user' => new UserResource($user->load('role'))]);
    }

    public function toggleStatus(User $user, Request $request): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa menonaktifkan diri sendiri.'], 422);
        }

        $user->update([
            'status' => $user->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return response()->json(['user' => new UserResource($user->load('role'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        if ($user->role?->slug === 'super-admin') {
            return response()->json(['message' => 'Tidak bisa menghapus Super Admin.'], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
