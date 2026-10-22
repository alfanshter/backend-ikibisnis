<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

class PartController extends Controller
{
    public function template()
    {
        $path = storage_path('app/temp/template_master_part.xlsx');
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0777, true);
        }

        SimpleExcelWriter::create($path)
            ->addRow([
                'Nama Part' => 'Kamera Outdoor 2MP',
                'SKU' => 'CAM-OUT-001',
                'Kategori' => 'Hardware Keamanan',
                'Merek' => 'Hikvision',
                'Spesifikasi' => 'Resolusi 1080p IR 30m',
                'Satuan' => 'pcs',
                'Stok Min' => 5,
                'Supplier Nama' => 'Toko Sentral',
                'Supplier Kontak' => '08123456789',
                'Supplier Harga' => 250000,
                'Supplier Lead Time' => '2 hari',
                'Is Primary' => 'Y'
            ]);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt', // some csv can be parsed as txt
        ]);

        $file = $request->file('file');
        $path = $file->store('temp');
        $fullPath = storage_path('app/' . $path);

        $rows = SimpleExcelReader::create($fullPath)->getRows();

        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $row) {
                if (empty(trim($row['SKU'] ?? ''))) continue;

                $part = Part::updateOrCreate(
                    ['sku' => trim($row['SKU'])],
                    [
                        'nama'        => $row['Nama Part'] ?? 'Unknown Part',
                        'kategori'    => $row['Kategori'] ?? 'Lainnya',
                        'merek'       => $row['Merek'] ?? null,
                        'spesifikasi' => $row['Spesifikasi'] ?? null,
                        'satuan'      => $row['Satuan'] ?? 'pcs',
                        'stok_min'    => (int) ($row['Stok Min'] ?? 1),
                        'created_by'  => $request->user()->id ?? 1,
                    ]
                );

                if (!empty(trim($row['Supplier Nama'] ?? ''))) {
                    $part->suppliers()->updateOrCreate(
                        ['nama' => trim($row['Supplier Nama'])],
                        [
                            'kontak'     => $row['Supplier Kontak'] ?? null,
                            'harga'      => (float) ($row['Supplier Harga'] ?? 0),
                            'lead_time'  => $row['Supplier Lead Time'] ?? null,
                            'is_primary' => (strtoupper(trim($row['Is Primary'] ?? 'N')) === 'Y'),
                        ]
                    );
                }
            }
        });

        // Hapus file temp
        @unlink($fullPath);

        return response()->json([
            'message' => 'Berhasil mengimpor data part.'
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $parts = Part::with('suppliers')->latest()->get();

        return response()->json([
            'data' => $parts->map(fn ($p) => $this->formatPart($p)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama'        => 'required|string|max:255',
            'sku'         => 'required|string|max:255|unique:parts,sku',
            'kategori'    => 'required|string|max:255',
            'merek'       => 'nullable|string|max:255',
            'spesifikasi' => 'nullable|string',
            'satuan'      => 'required|string|max:50',
            'stokMin'     => 'integer|min:0',
            'suppliers'   => 'array',
            'suppliers.*.nama'       => 'required|string|max:255',
            'suppliers.*.kontak'     => 'nullable|string|max:255',
            'suppliers.*.harga'      => 'numeric|min:0',
            'suppliers.*.leadTime'   => 'nullable|string|max:255',
            'suppliers.*.catatan'    => 'nullable|string',
            'suppliers.*.isPrimary'  => 'boolean',
        ]);

        $part = DB::transaction(function () use ($validated, $request) {
            $part = Part::create([
                'nama'        => $validated['nama'],
                'sku'         => $validated['sku'],
                'kategori'    => $validated['kategori'],
                'merek'       => $validated['merek'] ?? null,
                'spesifikasi' => $validated['spesifikasi'] ?? null,
                'satuan'      => $validated['satuan'] ?? 'pcs',
                'stok_min'    => $validated['stokMin'] ?? 1,
                'created_by'  => $request->user()->id ?? 1,
            ]);

            if (!empty($validated['suppliers'])) {
                foreach ($validated['suppliers'] as $sup) {
                    $part->suppliers()->create([
                        'nama'       => $sup['nama'],
                        'kontak'     => $sup['kontak'] ?? null,
                        'harga'      => $sup['harga'] ?? 0,
                        'lead_time'  => $sup['leadTime'] ?? null,
                        'catatan'    => $sup['catatan'] ?? null,
                        'is_primary' => $sup['isPrimary'] ?? false,
                    ]);
                }
            }

            return $part;
        });

        $part->load('suppliers');

        return response()->json([
            'message' => 'Part berhasil ditambahkan.',
            'data'    => $this->formatPart($part),
        ], 201);
    }

    public function show(Part $part): JsonResponse
    {
        $part->load('suppliers');
        return response()->json([
            'data' => $this->formatPart($part),
        ]);
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        $validated = $request->validate([
            'nama'        => 'required|string|max:255',
            'sku'         => ['required', 'string', 'max:255', Rule::unique('parts')->ignore($part->id)],
            'kategori'    => 'required|string|max:255',
            'merek'       => 'nullable|string|max:255',
            'spesifikasi' => 'nullable|string',
            'satuan'      => 'required|string|max:50',
            'stokMin'     => 'integer|min:0',
            'suppliers'   => 'array',
            'suppliers.*.nama'       => 'required|string|max:255',
            'suppliers.*.kontak'     => 'nullable|string|max:255',
            'suppliers.*.harga'      => 'numeric|min:0',
            'suppliers.*.leadTime'   => 'nullable|string|max:255',
            'suppliers.*.catatan'    => 'nullable|string',
            'suppliers.*.isPrimary'  => 'boolean',
        ]);

        DB::transaction(function () use ($part, $validated) {
            $part->update([
                'nama'        => $validated['nama'],
                'sku'         => $validated['sku'],
                'kategori'    => $validated['kategori'],
                'merek'       => $validated['merek'] ?? null,
                'spesifikasi' => $validated['spesifikasi'] ?? null,
                'satuan'      => $validated['satuan'] ?? 'pcs',
                'stok_min'    => $validated['stokMin'] ?? 1,
            ]);

            // Sync suppliers (delete all existing and recreate for simplicity)
            $part->suppliers()->delete();
            if (!empty($validated['suppliers'])) {
                foreach ($validated['suppliers'] as $sup) {
                    $part->suppliers()->create([
                        'nama'       => $sup['nama'],
                        'kontak'     => $sup['kontak'] ?? null,
                        'harga'      => $sup['harga'] ?? 0,
                        'lead_time'  => $sup['leadTime'] ?? null,
                        'catatan'    => $sup['catatan'] ?? null,
                        'is_primary' => $sup['isPrimary'] ?? false,
                    ]);
                }
            }
        });

        $part->refresh();
        $part->load('suppliers');

        return response()->json([
            'message' => 'Part berhasil diperbarui.',
            'data'    => $this->formatPart($part),
        ]);
    }

    public function destroy(Part $part): JsonResponse
    {
        $part->delete(); // Cascades suppliers automatically due to onDelete('cascade')

        return response()->json([
            'message' => 'Part berhasil dihapus.',
        ]);
    }

    private function formatPart(Part $part): array
    {
        return [
            'id'          => $part->id,
            'nama'        => $part->nama,
            'sku'         => $part->sku,
            'kategori'    => $part->kategori,
            'merek'       => $part->merek,
            'spesifikasi' => $part->spesifikasi,
            'satuan'      => $part->satuan,
            'stokMin'     => $part->stok_min, // Using camelCase for frontend matching
            'created_by'  => $part->created_by,
            'suppliers'   => $part->suppliers->map(fn ($s) => [
                'id'         => $s->id,
                'nama'       => $s->nama,
                'kontak'     => $s->kontak,
                'harga'      => $s->harga,
                'leadTime'   => $s->lead_time,
                'catatan'    => $s->catatan,
                'isPrimary'  => $s->is_primary,
            ])->values(),
        ];
    }
}
