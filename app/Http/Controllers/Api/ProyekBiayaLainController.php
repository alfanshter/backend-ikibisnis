<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekBiayaLain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekBiayaLainController extends Controller
{
    private function formatBiaya(ProyekBiayaLain $b): array
    {
        return [
            'id'          => $b->id,
            'proyek_id'   => $b->proyek_id,
            'tanggal'     => $b->tanggal?->format('Y-m-d'),
            'nama'        => $b->nama,
            'kategori'    => $b->kategori,
            'nominal'     => $b->nominal,
            'dibayarOleh' => $b->dibayar_oleh,
            'dibayar_oleh'=> $b->dibayar_oleh,
            'status'      => $b->status,
            'keterangan'  => $b->keterangan,
            'bukti'       => $b->bukti,
            'buktiTipe'   => $b->bukti_tipe,
            'bukti_tipe'  => $b->bukti_tipe,
            'created_by'  => $b->created_by,
            'created_at'  => $b->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/biaya-lain
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $items = $proyek->biayaLains()->get();
        $total = $items->sum('nominal');

        return response()->json([
            'data'  => $items->map(fn($b) => $this->formatBiaya($b))->values(),
            'total' => $total,
        ]);
    }

    /**
     * POST /api/proyeks/{proyek}/biaya-lain
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'     => 'required|date',
            'nama'        => 'required|string|max:255',
            'kategori'    => 'required|in:' . implode(',', ProyekBiayaLain::VALID_KATEGORI),
            'nominal'     => 'required|integer|min:0',
            'dibayar_oleh'=> 'nullable|string|max:255',
            'status'      => 'nullable|in:' . implode(',', ProyekBiayaLain::VALID_STATUSES),
            'keterangan'  => 'nullable|string',
            'bukti'       => 'nullable|string',
            'bukti_tipe'  => 'nullable|in:image,pdf',
        ]);

        $biaya = $proyek->biayaLains()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Biaya berhasil ditambahkan',
            'data'    => $this->formatBiaya($biaya),
        ], 201);
    }

    /**
     * PUT /api/proyeks/{proyek}/biaya-lain/{biaya}
     */
    public function update(Request $request, Proyek $proyek, ProyekBiayaLain $biaya): JsonResponse
    {
        abort_if($biaya->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal'     => 'sometimes|date',
            'nama'        => 'sometimes|string|max:255',
            'kategori'    => 'sometimes|in:' . implode(',', ProyekBiayaLain::VALID_KATEGORI),
            'nominal'     => 'sometimes|integer|min:0',
            'dibayar_oleh'=> 'nullable|string|max:255',
            'status'      => 'sometimes|in:' . implode(',', ProyekBiayaLain::VALID_STATUSES),
            'keterangan'  => 'nullable|string',
            'bukti'       => 'nullable|string',
            'bukti_tipe'  => 'nullable|in:image,pdf',
        ]);

        $biaya->update($validated);

        return response()->json([
            'message' => 'Biaya diperbarui',
            'data'    => $this->formatBiaya($biaya->fresh()),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/biaya-lain/{biaya}/status
     */
    public function updateStatus(Request $request, Proyek $proyek, ProyekBiayaLain $biaya): JsonResponse
    {
        abort_if($biaya->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', ProyekBiayaLain::VALID_STATUSES),
        ]);

        $biaya->update($validated);

        return response()->json([
            'message' => 'Status biaya diperbarui',
            'data'    => $this->formatBiaya($biaya->fresh()),
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/biaya-lain/{biaya}
     */
    public function destroy(Proyek $proyek, ProyekBiayaLain $biaya): JsonResponse
    {
        abort_if($biaya->proyek_id !== $proyek->id, 404);
        $biaya->delete();
        return response()->json(['message' => 'Biaya dihapus']);
    }
}
