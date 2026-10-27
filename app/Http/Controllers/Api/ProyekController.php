<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatProyek(Proyek $p, bool $withRelations = false): array
    {
        $data = [
            'id'            => $p->id,
            'kode'          => $p->kode,
            'nama'          => $p->nama,
            'client'        => $p->client_nama,       // alias for frontend compat
            'client_nama'   => $p->client_nama,
            'perusahaan'    => $p->perusahaan,
            'phone'         => $p->phone,
            'email'         => $p->email,
            'kategori'      => $p->kategori,
            'divisi'        => $p->divisi,
            'pic_id'        => $p->pic_id,
            'pic'           => $p->picUser?->name,
            'tanggal'       => $p->tanggal?->format('Y-m-d'),
            'targetSelesai' => $p->target_selesai?->format('Y-m-d'),  // camelCase for frontend
            'target_selesai'=> $p->target_selesai?->format('Y-m-d'),
            'deskripsi'     => $p->deskripsi,
            'nilai'         => $p->nilai,
            'nilai_format'  => 'Rp' . number_format($p->nilai, 0, ',', '.'),
            'progress'      => $p->progress,
            'status'        => $p->status,
            'penawaran_id'  => $p->penawaran_id,
            'created_by'    => $p->created_by,
            'created_at'    => $p->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $p->updated_at?->format('Y-m-d H:i:s'),
        ];

        if ($withRelations) {
            $data['tim'] = $p->tim->map(fn($t) => [
                'id'      => $t->id,
                'user_id' => $t->user_id,
                'nama'    => $t->nama,
                'role'    => $t->role,
            ])->values();
        }

        return $data;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks?search=&status=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proyek::query()
            ->with(['picUser'])
            ->search($request->input('search'))
            ->byStatus($request->input('status'));

        $proyeks = $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(12);

        return response()->json([
            'data' => $proyeks->map(fn($p) => $this->formatProyek($p))->values(),
            'meta' => [
                'current_page' => $proyeks->currentPage(),
                'last_page'    => $proyeks->lastPage(),
                'per_page'     => $proyeks->perPage(),
                'total'        => $proyeks->total(),
            ],
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks/stats
     */
    public function stats(): JsonResponse
    {
        $counts = Proyek::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byStatus = array_map(fn($s) => [
            'status' => $s,
            'count'  => $counts[$s] ?? 0,
        ], Proyek::VALID_STATUSES);

        $nilaiTotal = Proyek::whereIn('status', ['Berjalan', 'QC', 'Serah Terima'])->sum('nilai');

        return response()->json([
            'by_status'   => $byStatus,
            'total'       => Proyek::count(),
            'nilai_total' => $nilaiTotal,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/proyeks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama'          => 'required|string|max:255',
            'client_nama'   => 'nullable|string|max:255',
            'perusahaan'    => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'kategori'      => 'nullable|string|max:100',
            'divisi'        => 'nullable|string|max:100',
            'pic_id'        => 'nullable|exists:users,id',
            'tanggal'       => 'nullable|date',
            'target_selesai'=> 'nullable|date',
            'deskripsi'     => 'nullable|string',
            'nilai'         => 'nullable|integer|min:0',
            'progress'      => 'nullable|integer|min:0|max:100',
            'status'        => 'nullable|in:' . implode(',', Proyek::VALID_STATUSES),
            'penawaran_id'  => 'nullable|exists:penawarans,id',
            'lead_id'       => 'nullable|exists:leads,id',
        ]);

        // Auto-generate kode & force default status
        $proyek = new Proyek($validated);
        $proyek->kode       = $proyek->generateKode();
        $proyek->status     = 'Belum Dimulai';
        $proyek->nilai      = $validated['nilai'] ?? 0;  // ensure NOT NULL on PostgreSQL
        $proyek->progress   = $validated['progress'] ?? 0;
        $proyek->created_by = $request->user()?->id;
        $proyek->save();

        $proyek->load('picUser');

        return response()->json([
            'message' => 'Proyek berhasil ditambahkan',
            'data'    => $this->formatProyek($proyek),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks/{proyek}
     */
    public function show(Proyek $proyek): JsonResponse
    {
        $proyek->load(['picUser', 'tim.user', 'penawaran']);

        return response()->json([
            'data' => $this->formatProyek($proyek, true),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * PUT /api/proyeks/{proyek}
     */
    public function update(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'nama'          => 'sometimes|string|max:255',
            'client_nama'   => 'nullable|string|max:255',
            'perusahaan'    => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'kategori'      => 'nullable|string|max:100',
            'divisi'        => 'nullable|string|max:100',
            'pic_id'        => 'nullable|exists:users,id',
            'tanggal'       => 'nullable|date',
            'target_selesai'=> 'nullable|date',
            'deskripsi'     => 'nullable|string',
            'nilai'         => 'nullable|integer|min:0',
            'progress'      => 'nullable|integer|min:0|max:100',
            'status'        => 'sometimes|in:' . implode(',', Proyek::VALID_STATUSES),
            'penawaran_id'  => 'nullable|exists:penawarans,id',
            'lead_id'       => 'nullable|exists:leads,id',
        ]);

        $proyek->update($validated);

        return response()->json([
            'message' => 'Proyek berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh(['picUser'])),
        ]);
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    /**
     * PATCH /api/proyeks/{proyek}/status
     */
    public function updateStatus(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Proyek::VALID_STATUSES),
        ]);

        $proyek->update($validated);

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh(['picUser'])),
        ]);
    }

    // ── Update Progress ───────────────────────────────────────────────────────

    /**
     * PATCH /api/proyeks/{proyek}/progress
     */
    public function updateProgress(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
        ]);

        $proyek->update($validated);

        return response()->json([
            'message' => 'Progress berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh()),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    /**
     * DELETE /api/proyeks/{proyek}
     */
    public function destroy(Proyek $proyek): JsonResponse
    {
        $proyek->delete();
        return response()->json(['message' => 'Proyek berhasil dihapus']);
    }
}
