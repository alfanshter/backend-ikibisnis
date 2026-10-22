<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekFeeTim;
use App\Models\ProyekFeeRiwayat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekFeeTimController extends Controller
{
    private function formatFee(ProyekFeeTim $fee): array
    {
        return [
            'id'           => $fee->id,
            'proyek_id'    => $fee->proyek_id,
            'user_id'      => $fee->user_id,
            'nama'         => $fee->nama,
            'tipe'         => $fee->tipe,
            'role'         => $fee->role,
            'noRekening'   => $fee->no_rekening,
            'no_rekening'  => $fee->no_rekening,
            'namaBank'     => $fee->nama_bank,
            'nama_bank'    => $fee->nama_bank,
            'feeTotal'     => $fee->fee_total,
            'fee_total'    => $fee->fee_total,
            'feeDibayar'   => $fee->fee_dibayar,
            'fee_dibayar'  => $fee->fee_dibayar,
            'statusBayar'  => $fee->status_bayar,
            'status_bayar' => $fee->status_bayar,
            'metodeBayar'  => $fee->metode_bayar,
            'metode_bayar' => $fee->metode_bayar,
            'tanggalBayar' => $fee->tanggal_bayar?->format('Y-m-d'),
            'tanggal_bayar'=> $fee->tanggal_bayar?->format('Y-m-d'),
            'keterangan'   => $fee->keterangan,
            'buktiBayar'   => $fee->bukti_bayar,
            'bukti_bayar'  => $fee->bukti_bayar,
            'riwayat'      => $fee->riwayat->map(fn($r) => [
                'id'         => $r->id,
                'tanggal'    => $r->tanggal?->format('Y-m-d'),
                'nominal'    => $r->nominal,
                'metode'     => $r->metode,
                'keterangan' => $r->keterangan,
                'bukti'      => $r->bukti,
            ])->values(),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/fee-tim
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $fees = $proyek->feeTims()->with('riwayat')->get();
        return response()->json(['data' => $fees->map(fn($f) => $this->formatFee($f))->values()]);
    }

    /**
     * POST /api/proyeks/{proyek}/fee-tim
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => 'nullable|exists:users,id',
            'nama'        => 'required|string|max:255',
            'tipe'        => 'required|in:' . implode(',', ProyekFeeTim::VALID_TIPE),
            'role'        => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:50',
            'nama_bank'   => 'nullable|string|max:100',
            'fee_total'   => 'required|integer|min:0',
            'keterangan'  => 'nullable|string',
        ]);

        $fee = $proyek->feeTims()->create([
            ...$validated,
            'fee_dibayar'  => 0,
            'status_bayar' => 'Belum Dibayar',
            'created_by'   => $request->user()?->id,
        ]);

        $fee->load('riwayat');

        return response()->json([
            'message' => 'Fee tim ditambahkan',
            'data'    => $this->formatFee($fee),
        ], 201);
    }

    /**
     * PUT /api/proyeks/{proyek}/fee-tim/{fee}
     */
    public function update(Request $request, Proyek $proyek, ProyekFeeTim $fee): JsonResponse
    {
        abort_if($fee->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'nama'        => 'sometimes|string|max:255',
            'tipe'        => 'sometimes|in:' . implode(',', ProyekFeeTim::VALID_TIPE),
            'role'        => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:50',
            'nama_bank'   => 'nullable|string|max:100',
            'fee_total'   => 'sometimes|integer|min:0',
            'metode_bayar'=> 'nullable|string|max:100',
            'tanggal_bayar'=> 'nullable|date',
            'keterangan'  => 'nullable|string',
            'bukti_bayar' => 'nullable|string',
        ]);

        $fee->update($validated);
        $fee->recalculate();

        return response()->json([
            'message' => 'Fee tim diperbarui',
            'data'    => $this->formatFee($fee->fresh(['riwayat'])),
        ]);
    }

    /**
     * POST /api/proyeks/{proyek}/fee-tim/{fee}/riwayat
     */
    public function addRiwayat(Request $request, Proyek $proyek, ProyekFeeTim $fee): JsonResponse
    {
        abort_if($fee->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal'    => 'required|date',
            'nominal'    => 'required|integer|min:1',
            'metode'     => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
            'bukti'      => 'nullable|string',
        ]);

        $fee->riwayat()->create($validated);
        $fee->recalculate();

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat',
            'data'    => $this->formatFee($fee->fresh(['riwayat'])),
        ], 201);
    }

    /**
     * DELETE /api/proyeks/{proyek}/fee-tim/{fee}
     */
    public function destroy(Proyek $proyek, ProyekFeeTim $fee): JsonResponse
    {
        abort_if($fee->proyek_id !== $proyek->id, 404);
        $fee->delete();
        return response()->json(['message' => 'Fee tim dihapus']);
    }
}
