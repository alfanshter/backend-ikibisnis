<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekInvoice;
use App\Models\ProyekPembayaran;
use App\Models\Transaction;
use App\Services\DocumentNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekInvoiceController extends Controller
{
    private function formatInvoice(ProyekInvoice $inv, bool $withItems = true): array
    {
        $latestPayment = $inv->pembayarans->first();

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
            'pembayaran'    => $latestPayment ? [
                'id'                => $latestPayment->id,
                'nomor_pembayaran'  => $latestPayment->nomor_pembayaran,
                'tanggal_bayar'     => $latestPayment->tanggal_bayar?->format('Y-m-d'),
                'nominal'           => $latestPayment->nominal,
                'metode_pembayaran' => $latestPayment->metode_pembayaran,
                'catatan'           => $latestPayment->catatan,
                'transaction_id'    => $latestPayment->transaction_id,
                'masuk_pembukuan'   => (bool) $latestPayment->masuk_pembukuan,
            ] : null,
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
        return DocumentNumberingService::generateNumber('invoice');
    }

    /**
     * GET /api/proyeks/{proyek}/invoices
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $invoices = $proyek->invoices()->with(['items', 'pembayarans'])->get();
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
            'data'    => $this->formatInvoice($invoice->fresh(['items', 'pembayarans'])),
        ], 201);
    }

    /**
     * GET /api/proyeks/{proyek}/invoices/{invoice}
     */
    public function show(Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);
        $invoice->load(['items', 'pembayarans']);
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
            'data'    => $this->formatInvoice($invoice->fresh(['items', 'pembayarans'])),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/invoices/{invoice}/status
     */
    public function updateStatus(Request $request, Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'status'            => 'required|in:' . implode(',', ProyekInvoice::VALID_STATUSES),
            'tanggal_bayar'     => 'nullable|date',
            'nominal'           => 'nullable|integer|min:1',
            'metode_pembayaran' => 'nullable|string|max:100',
            'catatan'           => 'nullable|string',
            'masuk_pembukuan'   => 'nullable|boolean',
        ]);

        $prevStatus = $invoice->status;
        $newStatus  = $validated['status'];
        $invoice->update(['status' => $newStatus]);

        if ($newStatus === 'Lunas' && $prevStatus !== 'Lunas') {
            if ($invoice->pembayarans()->count() === 0) {
                $masukPembukuan = $request->boolean('masuk_pembukuan', true);
                $tanggalBayar   = $validated['tanggal_bayar'] ?? now()->format('Y-m-d');
                $nominal        = $validated['nominal'] ?? $invoice->total_harga;
                $metode         = $validated['metode_pembayaran'] ?? 'Transfer Bank';
                $catatan        = $validated['catatan'] ?? null;

                $txId = null;
                if ($masukPembukuan) {
                    $desc = "Pembayaran Invoice {$invoice->nomor_invoice}" . ($invoice->perihal ? " - {$invoice->perihal}" : " - {$proyek->nama}");
                    if ($catatan) {
                        $desc .= " ({$catatan})";
                    }

                    $tx = Transaction::create([
                        'type'       => 'masuk',
                        'name'       => $desc,
                        'date'       => $tanggalBayar,
                        'category'   => 'Pembayaran Client',
                        'proyek_id'  => $proyek->kode,
                        'amount'     => $nominal,
                        'created_by' => $request->user()?->id,
                    ]);
                    $txId = $tx->id;
                }

                $invoice->pembayarans()->create([
                    'proyek_id'         => $proyek->id,
                    'transaction_id'    => $txId,
                    'nomor_pembayaran'  => DocumentNumberingService::generateNumber('pembayaran'),
                    'bulan_ke'          => $invoice->nomor_termin,
                    'tanggal_bayar'     => $tanggalBayar,
                    'nominal'           => $nominal,
                    'metode_pembayaran' => $metode,
                    'catatan'           => $catatan,
                    'masuk_pembukuan'   => $masukPembukuan,
                    'created_by'        => $request->user()?->id,
                ]);
            }
        } elseif ($newStatus !== 'Lunas' && $prevStatus === 'Lunas') {
            foreach ($invoice->pembayarans as $pembayaran) {
                if ($pembayaran->transaction) {
                    $pembayaran->transaction->delete();
                }
                $pembayaran->delete();
            }
        }

        $proyek->syncStatusPembayaran();

        return response()->json([
            'message' => 'Status invoice diperbarui' . ($newStatus === 'Lunas' ? ' dan otomatis masuk ke Pembukuan' : ''),
            'data'    => $this->formatInvoice($invoice->fresh(['items', 'pembayarans'])),
        ]);
    }

    /**
     * POST /api/proyeks/{proyek}/invoices/{invoice}/bayar
     */
    public function bayar(Request $request, Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal_bayar'     => 'required|date',
            'nominal'           => 'required|integer|min:1',
            'metode_pembayaran' => 'required|string|max:100',
            'catatan'           => 'nullable|string',
            'masuk_pembukuan'   => 'nullable|boolean',
        ]);

        $masukPembukuan = $request->boolean('masuk_pembukuan', true);
        $txId = null;

        if ($masukPembukuan) {
            $desc = "Pembayaran Invoice {$invoice->nomor_invoice}" . ($invoice->perihal ? " - {$invoice->perihal}" : " - {$proyek->nama}");
            if (!empty($validated['catatan'])) {
                $desc .= " (" . $validated['catatan'] . ")";
            }

            $tx = Transaction::create([
                'type'       => 'masuk',
                'name'       => $desc,
                'date'       => $validated['tanggal_bayar'],
                'category'   => 'Pembayaran Client',
                'proyek_id'  => $proyek->kode,
                'amount'     => $validated['nominal'],
                'created_by' => $request->user()?->id,
            ]);
            $txId = $tx->id;
        }

        $pembayaran = $invoice->pembayarans()->create([
            'proyek_id'         => $proyek->id,
            'transaction_id'    => $txId,
            'nomor_pembayaran'  => DocumentNumberingService::generateNumber('pembayaran'),
            'bulan_ke'          => $invoice->nomor_termin,
            'tanggal_bayar'     => $validated['tanggal_bayar'],
            'nominal'           => $validated['nominal'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'catatan'           => $validated['catatan'] ?? null,
            'masuk_pembukuan'   => $masukPembukuan,
            'created_by'        => $request->user()?->id,
        ]);

        $invoice->update(['status' => 'Lunas']);
        $proyek->syncStatusPembayaran();

        return response()->json([
            'message' => 'Pembayaran invoice berhasil dicatat dan otomatis masuk ke Pembukuan',
            'data'    => $this->formatInvoice($invoice->fresh(['items', 'pembayarans'])),
            'pembayaran' => $pembayaran,
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/invoices/{invoice}
     */
    public function destroy(Proyek $proyek, ProyekInvoice $invoice): JsonResponse
    {
        abort_if($invoice->proyek_id !== $proyek->id, 404);

        foreach ($invoice->pembayarans as $p) {
            if ($p->transaction) {
                $p->transaction->delete();
            }
            $p->delete();
        }

        $invoice->delete();
        $proyek->syncStatusPembayaran();
        return response()->json(['message' => 'Invoice dihapus']);
    }
}
