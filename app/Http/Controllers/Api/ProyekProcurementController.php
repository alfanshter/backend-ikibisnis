<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekProcurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekProcurementController extends Controller
{
    private function formatItem(ProyekProcurement $p): array
    {
        return [
            'id'               => $p->id,
            'proyek_id'        => $p->proyek_id,
            'namaItem'         => $p->nama_item,
            'nama_item'        => $p->nama_item,
            'kategori'         => $p->kategori,
            'qty'              => $p->qty,
            'satuan'           => $p->satuan,
            'hargaSatuan'      => $p->harga_satuan,
            'harga_satuan'     => $p->harga_satuan,
            'total'            => $p->total,
            'supplier'         => $p->supplier,
            'tanggalRequest'   => $p->tanggal_request?->format('Y-m-d'),
            'tanggal_request'  => $p->tanggal_request?->format('Y-m-d'),
            'tanggalDiterima'  => $p->tanggal_diterima?->format('Y-m-d'),
            'tanggal_diterima' => $p->tanggal_diterima?->format('Y-m-d'),
            'status'           => $p->status,
            'catatan'          => $p->catatan,
            'requestBy'        => $p->request_by,
            'request_by'       => $p->request_by,
            'buktiTransfer'    => $p->bukti_transfer,
            'bukti_transfer'   => $p->bukti_transfer,
            'buktiDiterima'    => $p->bukti_diterima,
            'bukti_diterima'   => $p->bukti_diterima,
            'created_at'       => $p->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/procurement
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $items = $proyek->procurements()->get();
        $total = $items->sum('total');

        return response()->json([
            'data'  => $items->map(fn($i) => $this->formatItem($i))->values(),
            'total' => $total,
        ]);
    }

    /**
     * POST /api/proyeks/{proyek}/procurement
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'nama_item'       => 'required|string|max:255',
            'kategori'        => 'required|in:' . implode(',', ProyekProcurement::VALID_KATEGORI),
            'qty'             => 'required|numeric|min:0',
            'satuan'          => 'nullable|string|max:50',
            'harga_satuan'    => 'required|integer|min:0',
            'supplier'        => 'nullable|string|max:255',
            'tanggal_request' => 'required|date',
            'tanggal_diterima'=> 'nullable|date',
            'status'          => 'nullable|in:' . implode(',', ProyekProcurement::VALID_STATUSES),
            'catatan'         => 'nullable|string',
            'request_by'      => 'nullable|string|max:255',
            'bukti_transfer'  => 'nullable|string',
            'bukti_diterima'  => 'nullable|string',
        ]);

        $item = $proyek->procurements()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Item procurement ditambahkan',
            'data'    => $this->formatItem($item),
        ], 201);
    }

    /**
     * PUT /api/proyeks/{proyek}/procurement/{item}
     */
    public function update(Request $request, Proyek $proyek, ProyekProcurement $item): JsonResponse
    {
        abort_if($item->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'nama_item'       => 'sometimes|string|max:255',
            'kategori'        => 'sometimes|in:' . implode(',', ProyekProcurement::VALID_KATEGORI),
            'qty'             => 'sometimes|numeric|min:0',
            'satuan'          => 'nullable|string|max:50',
            'harga_satuan'    => 'sometimes|integer|min:0',
            'supplier'        => 'nullable|string|max:255',
            'tanggal_request' => 'sometimes|date',
            'tanggal_diterima'=> 'nullable|date',
            'status'          => 'sometimes|in:' . implode(',', ProyekProcurement::VALID_STATUSES),
            'catatan'         => 'nullable|string',
            'request_by'      => 'nullable|string|max:255',
            'bukti_transfer'  => 'nullable|string',
            'bukti_diterima'  => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item procurement diperbarui',
            'data'    => $this->formatItem($item->fresh()),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/procurement/{item}/status
     */
    public function updateStatus(Request $request, Proyek $proyek, ProyekProcurement $item): JsonResponse
    {
        abort_if($item->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', ProyekProcurement::VALID_STATUSES),
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Status procurement diperbarui',
            'data'    => $this->formatItem($item->fresh()),
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/procurement/{item}
     */
    public function destroy(Proyek $proyek, ProyekProcurement $item): JsonResponse
    {
        abort_if($item->proyek_id !== $proyek->id, 404);
        $item->delete();
        return response()->json(['message' => 'Item procurement dihapus']);
    }
}
