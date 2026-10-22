<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekInvoiceController extends Controller
{
    private function formatInvoice(ProyekInvoice $inv, bool $withItems = true): array
    {
        $data = [
            'id'            => $inv->id,
            'proyek_id'     => $inv->proyek_id,
            'nomorInvoice'  => $inv->nomor_invoice,
            'nomor_invoice' => $inv->nomor_invoice,
            'tanggal'       => $inv->tanggal?->format('Y-m-d'),
            'jatuhTempo'    => $inv->jatuh_tempo?->format('Y-m-d'),
            'jatuh_tempo'   => $inv->jatuh_tempo?->format('Y-m-d'),
            'perihal'       => $inv->perihal,
            'catatan'       => $inv->catatan,
            'ppn'           => $inv->ppn,
            'tipe'          => $inv->tipe,
            'nomorTermin'   => $inv->nomor_termin,
            'nomor_termin'  => $inv->nomor_termin,
            'status'        => $inv->status,
            'subtotal'      => $inv->subtotal,
            'total_harga'   => $inv->total_harga,
            'created_at'    => $inv->created_at?->format('Y-m-d H:i:s'),
        ];

        if ($withItems) {
            $data['items'] = $inv->items->map(fn($i) => [
                'id'          => $i->id,
                'nama'        => $i->nama,
                'satuan'      => $i->satuan,
                'qty'         => $i->qty,
                'hargaSatuan' => $i->harga_satuan,
                'harga_satuan'=> $i->harga_satuan,
                'diskon'      => $i->diskon,
                'line_total'  => $i->line_total,
            ])->values();
        }

        return $data;
    }

    private function generateNomor(Proyek $proyek): string
    {
        return \App\Services\DocumentNumberingService::generateNumber('invoice');
    }

    /**
     * GET /api/proyeks/{proyek}/invoices
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $invoices = $proyek->invoices()->with('items')->get();
        return response()->json(['data' => $invoices->map(fn($i) => $this->formatInvoice($i))->values()]);
    }

    /**
     * POST /api/proyeks/{proyek}/invoices
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'       => 'required|date',
            'jatuh_tempo'   => 'nullable|date',
            'perihal'       => 'nullable|string|max:255',
            'catatan'       => 'nullable|string',
            'ppn'           => 'nullable|integer|min:0|max:100',
            'tipe'          => 'required|in:' . implode(',', ProyekInvoice::VALID_TIPE),
            'nomor_termin'  => 'nullable|integer|min:1',
            'status'        => 'nullable|in:' . implode(',', ProyekInvoice::VALID_STATUSES),
            'items'         => 'nullable|array',
            'items.*.nama'  => 'required|string|max:255',
            'items.*.satuan'=> 'nullable|string|max:50',
            'items.*.qty'   => 'required|numeric|min:0',
            'items.*.harga_satuan' => 'required|integer|min:0',
            'items.*.diskon'       => 'nullable|integer|min:0|max:100',
        ]);

        $invoice = $proyek->invoices()->create([
            ...$validated,
            'nomor_invoice' => $this->generateNomor($proyek),
            'created_by'    => $request->user()?->id,
        ]);

        foreach ($validated['items'] ?? [] as $item) {
            $invoice->items()->create([
                'nama'         => $item['nama'],
                'satuan'       => $item['satuan'] ?? 'unit',
                'qty'          => $item['qty'],
                'harga_satuan' => $item['harga_satuan'],
                'diskon'       => $item['diskon'] ?? 0,
            ]);
        }

        $invoice->load('items');
        $invoice->recalculate();

        return response()->json([
            'message' => 'Invoice berhasil dibuat',
            'data'    => $this->formatInvoice($invoice->fresh(['items'])),
        ], 201);
    }

    /**
     * GET /api/proyeks/{proyek}/invoices/{invoice}
     */
    public function show(Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);
        $invoice->load('items');
        return response()->json(['data' => $this->formatInvoice($invoice)]);
    }

    /**
     * PUT /api/proyeks/{proyek}/invoices/{invoice}
     */
    public function update(Request $request, Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal'       => 'sometimes|date',
            'jatuh_tempo'   => 'nullable|date',
            'perihal'       => 'nullable|string|max:255',
            'catatan'       => 'nullable|string',
            'ppn'           => 'nullable|integer|min:0|max:100',
            'tipe'          => 'sometimes|in:' . implode(',', ProyekInvoice::VALID_TIPE),
            'nomor_termin'  => 'nullable|integer|min:1',
            'status'        => 'nullable|in:' . implode(',', ProyekInvoice::VALID_STATUSES),
            'items'         => 'nullable|array',
            'items.*.nama'  => 'required|string|max:255',
            'items.*.satuan'=> 'nullable|string|max:50',
            'items.*.qty'   => 'required|numeric|min:0',
            'items.*.harga_satuan' => 'required|integer|min:0',
            'items.*.diskon'       => 'nullable|integer|min:0|max:100',
        ]);

        $invoice->update(collect($validated)->except('items')->toArray());

        if (array_key_exists('items', $validated)) {
            $invoice->items()->delete();
            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'nama'         => $item['nama'],
                    'satuan'       => $item['satuan'] ?? 'unit',
                    'qty'          => $item['qty'],
                    'harga_satuan' => $item['harga_satuan'],
                    'diskon'       => $item['diskon'] ?? 0,
                ]);
            }
            $invoice->load('items');
            $invoice->recalculate();
        }

        return response()->json([
            'message' => 'Invoice diperbarui',
            'data'    => $this->formatInvoice($invoice->fresh(['items'])),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/invoices/{invoice}/status
     */
    public function updateStatus(Request $request, Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', ProyekInvoice::VALID_STATUSES),
        ]);

        $invoice->update($validated);

        return response()->json([
            'message' => 'Status invoice diperbarui',
            'data'    => $this->formatInvoice($invoice->fresh(['items'])),
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/invoices/{invoice}
     */
    public function destroy(Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);
        $invoice->delete();
        return response()->json(['message' => 'Invoice dihapus']);
    }
}
