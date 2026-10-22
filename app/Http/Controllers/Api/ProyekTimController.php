<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekTim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekTimController extends Controller
{
    private function formatTim(ProyekTim $t): array
    {
        return [
            'id'      => $t->id,
            'user_id' => $t->user_id,
            'nama'    => $t->nama,
            'role'    => $t->role,
            'avatar'  => $t->user?->avatar ?? null,
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/tim
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $tim = $proyek->tim()->with('user')->get();
        return response()->json(['data' => $tim->map(fn($t) => $this->formatTim($t))->values()]);
    }

    /**
     * POST /api/proyeks/{proyek}/tim
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'nama'    => 'required|string|max:255',
            'role'    => 'nullable|string|max:100',
        ]);

        $tim = $proyek->tim()->create($validated);
        $tim->load('user');

        return response()->json([
            'message' => 'Anggota tim ditambahkan',
            'data'    => $this->formatTim($tim),
        ], 201);
    }

    /**
     * DELETE /api/proyeks/{proyek}/tim/{tim}
     */
    public function destroy(Proyek $proyek, ProyekTim $tim): JsonResponse
    {
        abort_if($tim->proyek_id !== $proyek->id, 404);
        $tim->delete();
        return response()->json(['message' => 'Anggota tim dihapus']);
    }
}
