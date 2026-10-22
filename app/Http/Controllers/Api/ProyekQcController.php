<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekQc;
use App\Models\ProyekQcItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekQcController extends Controller
{
    private function formatQc(ProyekQc $qc): array
    {
        $qc->loadMissing(['sections.items']);
        return [
            'id'         => $qc->id,
            'proyek_id'  => $qc->proyek_id,
            'qc_pic'     => $qc->qc_pic,
            'qc_tanggal' => $qc->qc_tanggal?->format('Y-m-d'),
            'status'     => $qc->status,
            'sections'   => $qc->sections->map(fn($s) => [
                'id'         => $s->id,
                'title'      => $s->title,
                'sort_order' => $s->sort_order,
                'isExpanded' => true,
                'items'      => $s->items->map(fn($i) => [
                    'id'      => $i->id,
                    'text'    => $i->text,
                    'checked' => $i->checked,
                    'note'    => $i->note,
                ])->values(),
            ])->values(),
        ];
    }

    // ── Default QC sections template removed ──

    /**
     * GET /api/proyeks/{proyek}/qc
     * Auto-creates QC sheet if none exists
     */
    public function show(Proyek $proyek): JsonResponse
    {
        $qc = $proyek->qc;

        if (!$qc) {
            $qc = ProyekQc::create([
                'proyek_id'  => $proyek->id,
                'status'     => 'Draft',
                'created_by' => request()->user()?->id,
            ]);
        }

        return response()->json(['data' => $this->formatQc($qc->fresh(['sections.items']))]);
    }

    /**
     * PUT /api/proyeks/{proyek}/qc
     * Full update: status, pic, tanggal, sections/items
     */
    public function update(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'qc_pic'       => 'nullable|string|max:255',
            'qc_tanggal'   => 'nullable|date',
            'status'       => 'nullable|in:' . implode(',', ProyekQc::VALID_STATUSES),
            'sections'     => 'nullable|array',
            'sections.*.title'  => 'required|string|max:255',
            'sections.*.items'  => 'nullable|array',
            'sections.*.items.*.text'    => 'required|string',
            'sections.*.items.*.checked' => 'boolean',
            'sections.*.items.*.note'    => 'nullable|string',
        ]);

        $qc = $proyek->qc ?? ProyekQc::create(['proyek_id' => $proyek->id, 'status' => 'Draft']);

        $qc->update(collect($validated)->except('sections')->toArray());

        if (isset($validated['sections'])) {
            // Replace all sections/items
            $qc->sections()->delete();
            foreach ($validated['sections'] as $sIdx => $sData) {
                $sec = $qc->sections()->create(['title' => $sData['title'], 'sort_order' => $sIdx]);
                foreach ($sData['items'] ?? [] as $iIdx => $iData) {
                    $sec->items()->create([
                        'text'       => $iData['text'],
                        'checked'    => $iData['checked'] ?? false,
                        'note'       => $iData['note'] ?? null,
                        'sort_order' => $iIdx,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'QC sheet diperbarui',
            'data'    => $this->formatQc($qc->fresh(['sections.items'])),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/qc/items/{item}
     * Quick toggle checked / update note
     */
    public function updateItemCheck(Request $request, Proyek $proyek, ProyekQcItem $item): JsonResponse
    {
        $validated = $request->validate([
            'checked' => 'sometimes|boolean',
            'note'    => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item QC diperbarui',
            'data'    => [
                'id'      => $item->id,
                'checked' => $item->checked,
                'note'    => $item->note,
            ],
        ]);
    }
}
