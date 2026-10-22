<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penawaran;
use App\Models\PenawaranItem;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenawaranController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Penawaran::with(['lead', 'survei', 'createdBy']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $q = $request->input('search');
            $query->where(function ($sub) use ($q) {
                $sub->where('nomor_penawaran', 'like', "%{$q}%")
                    ->orWhereHas('lead', function ($l) use ($q) {
                        $l->where('nama', 'like', "%{$q}%")
                          ->orWhere('perusahaan', 'like', "%{$q}%");
                    });
            });
        }

        $data = $query->latest()->get();

        return response()->json([
            'data' => $data->map(fn($p) => $this->formatPenawaran($p))
        ]);
    }

    public function show($id): JsonResponse
    {
        $penawaran = Penawaran::with(['lead', 'survei', 'items', 'createdBy'])->findOrFail($id);
        return response()->json([
            'data' => $this->formatPenawaran($penawaran, true)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_id'         => 'nullable|exists:leads,id',
            'survei_id'       => 'nullable|exists:surveis,id',
            'nomor_penawaran' => 'nullable|string',
            'tanggal'         => 'required|date',
            'valid_until'     => 'nullable|date',
            'status'          => 'nullable|string',
            'catatan'         => 'nullable|string',
            'subtotal'        => 'required|numeric|min:0',
            'pajak'           => 'required|numeric|min:0',
            'diskon'          => 'required|numeric|min:0',
            'total_harga'     => 'required|numeric|min:0',
            'items'           => 'required|array|min:1',
            'items.*.jenis_item' => 'nullable|string|in:barang,jasa',
            'items.*.pajak_type' => 'nullable|string|in:tanpa_pajak,dengan_pajak,termasuk_pajak',
            'items.*.pajak_persen' => 'nullable|numeric|min:0|max:100',
            'items.*.part_id' => 'nullable|exists:parts,id',
            'items.*.nama_item' => 'required|string|max:255',
            'items.*.deskripsi' => 'nullable|string',
            'items.*.qty'       => 'required|integer|min:1',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.total_harga'  => 'required|numeric|min:0',
        ]);

        $penawaran = DB::transaction(function () use ($validated, $request) {
            $nomor = $validated['nomor_penawaran'] ?? 'AUTO';
            if ($nomor === 'AUTO' || empty($nomor)) {
                $nomor = \App\Services\DocumentNumberingService::generateNumber('penawaran');
            } else {
                // Ensure unique if provided manually
                $exists = \App\Models\Penawaran::where('nomor_penawaran', $nomor)->exists();
                if ($exists) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nomor_penawaran' => ['Nomor penawaran sudah digunakan.']
                    ]);
                }
            }

            $p = Penawaran::create([
                'lead_id'         => $validated['lead_id'] ?? null,
                'survei_id'       => $validated['survei_id'] ?? null,
                'nomor_penawaran' => $nomor,
                'tanggal'         => $validated['tanggal'],
                'valid_until'     => $validated['valid_until'] ?? null,
                'status'          => $validated['status'] ?? 'Draft',
                'catatan'         => $validated['catatan'] ?? null,
                'subtotal'        => $validated['subtotal'],
                'pajak'           => $validated['pajak'],
                'diskon'          => $validated['diskon'],
                'total_harga'     => $validated['total_harga'],
                'created_by'      => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $item) {
                PenawaranItem::create([
                    'penawaran_id' => $p->id,
                    'part_id'      => $item['part_id'] ?? null,
                    'jenis_item'   => $item['jenis_item'] ?? 'barang',
                    'pajak_type'   => $item['pajak_type'] ?? 'tanpa_pajak',
                    'pajak_persen' => $item['pajak_persen'] ?? 11.00,
                    'nama_item'    => $item['nama_item'],
                    'deskripsi'    => $item['deskripsi'] ?? null,
                    'qty'          => $item['qty'],
                    'harga_satuan' => $item['harga_satuan'],
                    'total_harga'  => $item['total_harga'],
                ]);
            }

            // Log activity to lead if lead_id is present
            if ($p->lead_id) {
                LeadActivity::create([
                    'lead_id'     => $p->lead_id,
                    'title'       => 'Penawaran',
                    'description' => "Penawaran {$p->nomor_penawaran} dibuat.",
                    'date'        => now()->format('Y-m-d'),
                    'created_by'  => $request->user()?->id,
                ]);

                // Update lead status if it's currently Prospek or Survey
                $lead = Lead::find($p->lead_id);
                if ($lead && in_array($lead->status, ['Prospek', 'Survey'])) {
                    $lead->update(['status' => 'Penawaran']);
                }
            }

            return $p;
        });

        return response()->json([
            'message' => 'Penawaran berhasil dibuat',
            'data'    => $this->formatPenawaran($penawaran->fresh(['lead', 'survei', 'items']), true)
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $penawaran = Penawaran::findOrFail($id);

        $validated = $request->validate([
            'lead_id'         => 'nullable|exists:leads,id',
            'survei_id'       => 'nullable|exists:surveis,id',
            'nomor_penawaran' => 'required|string|unique:penawarans,nomor_penawaran,' . $penawaran->id,
            'tanggal'         => 'required|date',
            'valid_until'     => 'nullable|date',
            'status'          => 'nullable|string',
            'catatan'         => 'nullable|string',
            'subtotal'        => 'required|numeric|min:0',
            'pajak'           => 'required|numeric|min:0',
            'diskon'          => 'required|numeric|min:0',
            'total_harga'     => 'required|numeric|min:0',
            'items'           => 'required|array|min:1',
            'items.*.jenis_item' => 'nullable|string|in:barang,jasa',
            'items.*.pajak_type' => 'nullable|string|in:tanpa_pajak,dengan_pajak,termasuk_pajak',
            'items.*.pajak_persen' => 'nullable|numeric|min:0|max:100',
            'items.*.part_id' => 'nullable|exists:parts,id',
            'items.*.nama_item' => 'required|string|max:255',
            'items.*.deskripsi' => 'nullable|string',
            'items.*.qty'       => 'required|integer|min:1',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.total_harga'  => 'required|numeric|min:0',
        ]);

        $penawaran = DB::transaction(function () use ($penawaran, $validated) {
            $penawaran->update([
                'lead_id'         => $validated['lead_id'] ?? null,
                'survei_id'       => $validated['survei_id'] ?? null,
                'nomor_penawaran' => $validated['nomor_penawaran'],
                'tanggal'         => $validated['tanggal'],
                'valid_until'     => $validated['valid_until'] ?? null,
                'status'          => $validated['status'] ?? $penawaran->status,
                'catatan'         => $validated['catatan'] ?? null,
                'subtotal'        => $validated['subtotal'],
                'pajak'           => $validated['pajak'],
                'diskon'          => $validated['diskon'],
                'total_harga'     => $validated['total_harga'],
            ]);

            // Re-create items (simplest way to sync without complex matching)
            $penawaran->items()->delete();
            foreach ($validated['items'] as $item) {
                PenawaranItem::create([
                    'penawaran_id' => $penawaran->id,
                    'part_id'      => $item['part_id'] ?? null,
                    'jenis_item'   => $item['jenis_item'] ?? 'barang',
                    'pajak_type'   => $item['pajak_type'] ?? 'tanpa_pajak',
                    'pajak_persen' => $item['pajak_persen'] ?? 11.00,
                    'nama_item'    => $item['nama_item'],
                    'deskripsi'    => $item['deskripsi'] ?? null,
                    'qty'          => $item['qty'],
                    'harga_satuan' => $item['harga_satuan'],
                    'total_harga'  => $item['total_harga'],
                ]);
            }

            return $penawaran;
        });

        return response()->json([
            'message' => 'Penawaran berhasil diperbarui',
            'data'    => $this->formatPenawaran($penawaran->fresh(['lead', 'survei', 'items']), true)
        ]);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $penawaran = Penawaran::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string'
        ]);

        $oldStatus = $penawaran->status;
        $penawaran->update(['status' => $validated['status']]);

        // If changed to Deal, log it
        if ($penawaran->lead_id && $oldStatus !== $validated['status'] && $validated['status'] === 'Disetujui') {
            LeadActivity::create([
                'lead_id'     => $penawaran->lead_id,
                'title'       => 'Deal',
                'description' => "Penawaran {$penawaran->nomor_penawaran} telah disetujui.",
                'date'        => now()->format('Y-m-d'),
                'created_by'  => $request->user()?->id,
            ]);

            $lead = Lead::find($penawaran->lead_id);
            if ($lead) {
                $lead->update(['status' => 'Deal']);
            }
        }

        return response()->json([
            'message' => 'Status penawaran berhasil diperbarui',
            'data'    => $this->formatPenawaran($penawaran->fresh(['lead', 'survei', 'items']), true)
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $penawaran = Penawaran::findOrFail($id);
        $penawaran->delete();
        return response()->json(['message' => 'Penawaran berhasil dihapus']);
    }

    private function formatPenawaran(Penawaran $p, bool $includeItems = false): array
    {
        $data = [
            'id'              => $p->id,
            'lead_id'         => $p->lead_id,
            'client_name'     => $p->lead ? $p->lead->nama : '-',
            'perusahaan'      => $p->lead ? $p->lead->perusahaan : '-',
            'phone'           => $p->lead ? $p->lead->phone : null,
            'survei_id'       => $p->survei_id,
            'nomor_penawaran' => $p->nomor_penawaran,
            'tanggal'         => $p->tanggal?->format('Y-m-d'),
            'valid_until'     => $p->valid_until?->format('Y-m-d'),
            'status'          => $p->status,
            'catatan'         => $p->catatan,
            'subtotal'        => (float) $p->subtotal,
            'pajak'           => (float) $p->pajak,
            'diskon'          => (float) $p->diskon,
            'total_harga'     => (float) $p->total_harga,
            'created_by'      => $p->createdBy ? $p->createdBy->name : '-',
            'created_at'      => $p->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $p->updated_at?->format('Y-m-d H:i:s'),
        ];

        if ($includeItems) {
            $data['items'] = $p->items->map(fn($item) => [
                'id'           => $item->id,
                'part_id'      => $item->part_id,
                'jenis_item'   => $item->jenis_item ?? 'barang',
                'pajak_type'   => $item->pajak_type ?? 'tanpa_pajak',
                'pajak_persen' => (float) ($item->pajak_persen ?? 11.00),
                'nama_item'    => $item->nama_item,
                'deskripsi'    => $item->deskripsi,
                'qty'          => $item->qty,
                'harga_satuan' => (float) $item->harga_satuan,
                'total_harga'  => (float) $item->total_harga,
            ]);
        }

        return $data;
    }
}
