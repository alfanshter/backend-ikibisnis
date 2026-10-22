<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatLead(Lead $lead): array
    {
        return [
            'id'          => $lead->id,
            'kode_lead'   => $lead->kode_lead,
            'tanggal'     => $lead->tanggal?->format('Y-m-d'),
            'nama'        => $lead->nama,
            'perusahaan'  => $lead->perusahaan,
            'pic_id'      => $lead->pic_id,
            'pic'         => $lead->picUser?->name,
            'kebutuhan'   => $lead->kebutuhan,
            'sumber'      => $lead->sumber,
            'estimasi'    => $lead->estimasi,
            'status'      => $lead->status,
            'phone'       => $lead->phone,
            'email'       => $lead->email,
            'lokasi'      => $lead->lokasi,
            'catatan'     => $lead->catatan,
            'tipe_proyek' => $lead->tipe_proyek,
            'skala'       => $lead->skala,
            'intensitas'  => $lead->intensitas,
            'created_by'  => $lead->created_by,
            'created_at'  => $lead->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $lead->updated_at?->format('Y-m-d H:i:s'),
            'surveis_count' => $lead->surveis_count ?? 0,
            'proyek'      => $lead->relationLoaded('proyek') && $lead->proyek ? [
                'id'   => $lead->proyek->id,
                'nama' => $lead->proyek->nama,
            ] : null,
        ];
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/leads?search=&status=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()
            ->with(['picUser'])
            ->withCount('surveis')
            ->search($request->input('search'))
            ->byStatus($request->input('status'));

        $leads = $query->orderBy('tanggal', 'desc')
                       ->orderBy('id', 'desc')
                       ->paginate(20);

        return response()->json([
            'data' => $leads->map(fn($l) => $this->formatLead($l))->values(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page'    => $leads->lastPage(),
                'per_page'     => $leads->perPage(),
                'total'        => $leads->total(),
            ],
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/leads/stats
     */
    public function stats(): JsonResponse
    {
        $statuses = Lead::VALID_STATUSES;
        $counts   = Lead::selectRaw('status, count(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();

        $data = array_map(fn($s) => [
            'status' => $s,
            'count'  => $counts[$s] ?? 0,
        ], $statuses);

        $totalEstimasi = Lead::whereIn('status', ['Penawaran', 'Deal'])
                             ->whereNotNull('estimasi')
                             ->sum('estimasi');

        return response()->json([
            'by_status'       => $data,
            'total'           => Lead::count(),
            'total_estimasi'  => $totalEstimasi,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/leads
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'     => 'required|date',
            'nama'        => 'required|string|max:255',
            'perusahaan'  => 'required|string|max:255',
            'pic_id'      => 'nullable|exists:users,id',
            'kebutuhan'   => 'nullable|string',
            'sumber'      => 'required|in:' . implode(',', Lead::VALID_SUMBER),
            'estimasi'    => 'nullable|numeric|min:0',
            'status'      => 'required|in:' . implode(',', Lead::VALID_STATUSES),
            'phone'       => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:255',
            'lokasi'      => 'nullable|string|max:255',
            'catatan'     => 'nullable|string',
            'tipe_proyek' => 'nullable|string|max:100',
            'skala'       => 'nullable|string|max:100',
            'intensitas'  => 'nullable|string|max:100',
        ]);

        $validated['kode_lead'] = \App\Services\DocumentNumberingService::generateNumber('crm');

        $lead = Lead::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Lead berhasil ditambahkan',
            'data'    => $this->formatLead($lead),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/leads/{lead}
     */
    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['activities', 'surveis', 'picUser', 'penawarans', 'proyek']);

        return response()->json([
            'data'       => $this->formatLead($lead),
            'activities' => $lead->activities->map(fn($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'description' => $a->description,
                'date'        => $a->date?->format('Y-m-d'),
                'created_by'  => $a->created_by,
                'created_at'  => $a->created_at?->format('Y-m-d H:i:s'),
            ])->values(),
            'surveis'    => $lead->surveis->map(fn($s) => [
                'id'         => $s->id,
                'tanggal'    => $s->tanggal?->format('Y-m-d'),
                'waktu'      => $s->waktu,
                'status'     => $s->status,
                'lokasi'     => $s->lokasi,
                'estimasi'   => $s->estimasi,
                'surveyor'   => $s->surveyor,
            ])->values(),
            'penawarans' => $lead->penawarans->map(fn($p) => [
                'id'              => $p->id,
                'nomor_penawaran' => $p->nomor_penawaran,
                'tanggal'         => $p->tanggal?->format('Y-m-d'),
                'valid_until'     => $p->valid_until?->format('Y-m-d'),
                'status'          => $p->status,
                'total_harga'     => (float) $p->total_harga,
            ])->values(),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * PUT /api/leads/{lead}
     */
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'     => 'sometimes|date',
            'nama'        => 'sometimes|string|max:255',
            'perusahaan'  => 'sometimes|string|max:255',
            'pic_id'      => 'nullable|exists:users,id',
            'kebutuhan'   => 'nullable|string',
            'sumber'      => 'sometimes|in:' . implode(',', Lead::VALID_SUMBER),
            'estimasi'    => 'nullable|numeric|min:0',
            'status'      => 'sometimes|in:' . implode(',', Lead::VALID_STATUSES),
            'phone'       => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:255',
            'lokasi'      => 'nullable|string|max:255',
            'catatan'     => 'nullable|string',
            'tipe_proyek' => 'nullable|string|max:100',
            'skala'       => 'nullable|string|max:100',
            'intensitas'  => 'nullable|string|max:100',
        ]);

        $lead->update($validated);

        return response()->json([
            'message' => 'Lead berhasil diperbarui',
            'data'    => $this->formatLead($lead->fresh()),
        ]);
    }

    // ── Update Status Only ────────────────────────────────────────────────────

    /**
     * PATCH /api/leads/{lead}/status
     */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Lead::VALID_STATUSES),
        ]);

        $lead->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data'    => $this->formatLead($lead->fresh()),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    /**
     * DELETE /api/leads/{lead}
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(['message' => 'Lead berhasil dihapus']);
    }
}
