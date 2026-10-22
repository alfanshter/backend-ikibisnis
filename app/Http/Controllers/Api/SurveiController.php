<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Survei;
use App\Models\SurveiCategory;
use App\Models\SurveiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LeadActivity;

class SurveiController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatSurvei(Survei $survei, bool $withCategories = false): array
    {
        $data = [
            'id'          => $survei->id,
            'lead_id'     => $survei->lead_id,
            'client'      => $survei->client,
            'perusahaan'  => $survei->perusahaan,
            'surveyor'    => $survei->surveyor,
            'lokasi'      => $survei->lokasi,
            'tanggal'     => $survei->tanggal?->format('Y-m-d'),
            'waktu'       => $survei->waktu,
            'kebutuhan'   => $survei->kebutuhan,
            'estimasi'    => $survei->estimasi ?? 0,
            'status'      => $survei->status,
            'catatan'     => $survei->catatan,
            'rekomendasi' => $survei->rekomendasi,
            'created_by'  => $survei->created_by,
            'created_at'  => $survei->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $survei->updated_at?->format('Y-m-d H:i:s'),
        ];

        if ($withCategories) {
            $survei->loadMissing(['categories.questions']);
            $data['categories'] = $survei->categories->map(function ($cat) {
                return [
                    'id'        => $cat->id,
                    'name'      => $cat->name,
                    'urutan'    => $cat->urutan,
                    'questions' => $cat->questions->map(function ($q) {
                        return [
                            'id'     => $q->id,
                            'label'  => $q->label,
                            'answer' => $q->answer ?? '',
                            'notes'  => $q->notes ?? '',
                            'urutan' => $q->urutan,
                        ];
                    })->values(),
                ];
            })->values();

            // Progress
            $total    = 0;
            $answered = 0;
            foreach ($survei->categories as $cat) {
                foreach ($cat->questions as $q) {
                    $total++;
                    if (!empty($q->answer)) $answered++;
                }
            }
            $data['progress'] = [
                'total'    => $total,
                'answered' => $answered,
                'percent'  => $total > 0 ? round(($answered / $total) * 100) : 0,
            ];
        } else {
            // Lightweight progress for list view (load counts only)
            $total    = SurveiQuestion::where('survei_id', $survei->id)->count();
            $answered = SurveiQuestion::where('survei_id', $survei->id)
                                      ->whereNotNull('answer')
                                      ->where('answer', '!=', '')
                                      ->count();
            $data['progress'] = [
                'total'    => $total,
                'answered' => $answered,
                'percent'  => $total > 0 ? round(($answered / $total) * 100) : 0,
            ];
        }

        return $data;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/surveis?search=&status=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $surveis = Survei::query()
            ->search($request->input('search'))
            ->byStatus($request->input('status'))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => $surveis->map(fn($s) => $this->formatSurvei($s))->values(),
            'meta' => [
                'current_page' => $surveis->currentPage(),
                'last_page'    => $surveis->lastPage(),
                'per_page'     => $surveis->perPage(),
                'total'        => $surveis->total(),
            ],
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/surveis/stats
     */
    public function stats(): JsonResponse
    {
        $counts = Survei::selectRaw('status, count(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();

        return response()->json([
            'by_status' => array_map(fn($s) => [
                'status' => $s,
                'count'  => $counts[$s] ?? 0,
            ], Survei::VALID_STATUSES),
            'total' => Survei::count(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/surveis
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_id'    => 'nullable|exists:leads,id',
            'client'     => 'required|string|max:255',
            'perusahaan' => 'nullable|string|max:255',
            'surveyor'   => 'nullable|string|max:255',
            'lokasi'     => 'nullable|string|max:255',
            'tanggal'    => 'required|date',
            'waktu'      => 'nullable|string|max:10',
            'kebutuhan'  => 'nullable|string',
            'estimasi'   => 'nullable|numeric|min:0',
            'status'     => 'nullable|in:' . implode(',', Survei::VALID_STATUSES),
        ]);

        $leadEstimasi = null;
        if (!empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            if ($lead) {
                $leadEstimasi = $lead->estimasi;
            }
        }

        $survei = Survei::create([
            ...$validated,
            'estimasi'   => $leadEstimasi ?? ($validated['estimasi'] ?? null),
            'status'     => $validated['status'] ?? Survei::STATUS_DIJADWALKAN,
            'created_by' => $request->user()?->id,
        ]);

        if ($survei->lead_id) {
            LeadActivity::create([
                'lead_id'     => $survei->lead_id,
                'title'       => 'Survei',
                'description' => "Survei dijadwalkan pada {$survei->tanggal->format('d/m/Y')} {$survei->waktu} di {$survei->lokasi}",
                'date'        => now()->format('Y-m-d'),
                'created_by'  => $request->user()?->id,
            ]);
            // Auto update status lead to Survey if it's currently Prospek
            $lead = Lead::find($survei->lead_id);
            if ($lead && $lead->status === 'Prospek') {
                $lead->update(['status' => 'Survey']);
            }
        }

        return response()->json([
            'message' => 'Survei berhasil dijadwalkan',
            'data'    => $this->formatSurvei($survei, true),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/surveis/{survei}
     */
    public function show(Survei $survei): JsonResponse
    {
        return response()->json([
            'data' => $this->formatSurvei($survei, true),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * PUT /api/surveis/{survei}
     */
    public function update(Request $request, Survei $survei): JsonResponse
    {
        $validated = $request->validate([
            'client'      => 'sometimes|string|max:255',
            'perusahaan'  => 'nullable|string|max:255',
            'surveyor'    => 'nullable|string|max:255',
            'lokasi'      => 'nullable|string|max:255',
            'tanggal'     => 'sometimes|date',
            'waktu'       => 'nullable|string|max:10',
            'kebutuhan'   => 'nullable|string',
            'estimasi'    => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:' . implode(',', Survei::VALID_STATUSES),
            'catatan'     => 'nullable|string',
            'rekomendasi' => 'nullable|string',
        ]);

        $survei->update($validated);

        return response()->json([
            'message' => 'Survei berhasil diperbarui',
            'data'    => $this->formatSurvei($survei->fresh(), true),
        ]);
    }

    // ── Update Status Only ────────────────────────────────────────────────────

    /**
     * PATCH /api/surveis/{survei}/status
     */
    public function updateStatus(Request $request, Survei $survei): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Survei::VALID_STATUSES),
        ]);

        $oldStatus = $survei->status;
        $survei->update(['status' => $validated['status']]);

        if ($survei->lead_id && $oldStatus !== $validated['status']) {
            $desc = "Status survei diubah menjadi {$validated['status']}";
            LeadActivity::create([
                'lead_id'     => $survei->lead_id,
                'title'       => 'Survei',
                'description' => $desc,
                'date'        => now()->format('Y-m-d'),
                'created_by'  => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => 'Status survei berhasil diperbarui',
            'data'    => $this->formatSurvei($survei->fresh()),
        ]);
    }

    // ── Sync Form (Categories + Questions) ────────────────────────────────────

    /**
     * POST /api/surveis/{survei}/sync
     *
     * Payload:
     * {
     *   "categories": [
     *     {
     *       "id": 1,          // existing — null/0 = new
     *       "name": "...",
     *       "urutan": 0,
     *       "questions": [
     *         { "id": 5, "label": "...", "answer": "...", "notes": "...", "urutan": 0 }
     *       ]
     *     }
     *   ],
     *   "estimasi": 85000000,
     *   "catatan": "...",
     *   "rekomendasi": "..."
     * }
     */
    public function sync(Request $request, Survei $survei): JsonResponse
    {
        $validated = $request->validate([
            'categories'                       => 'nullable|array',
            'categories.*.id'                  => 'nullable|integer',
            'categories.*.name'                => 'required_with:categories|string|max:255',
            'categories.*.urutan'              => 'nullable|integer',
            'categories.*.questions'           => 'nullable|array',
            'categories.*.questions.*.id'      => 'nullable|integer',
            'categories.*.questions.*.label'   => 'required_with:categories.*.questions|string',
            'categories.*.questions.*.answer'  => 'nullable|string',
            'categories.*.questions.*.notes'   => 'nullable|string',
            'categories.*.questions.*.urutan'  => 'nullable|integer',
            'estimasi'                         => 'nullable|numeric|min:0',
            'catatan'                          => 'nullable|string',
            'rekomendasi'                      => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $survei) {
            // Update top-level survei fields
            $survei->update(array_filter([
                'estimasi'    => $validated['estimasi'] ?? null,
                'catatan'     => $validated['catatan'] ?? null,
                'rekomendasi' => $validated['rekomendasi'] ?? null,
            ], fn($v) => $v !== null));

            $categories = $validated['categories'] ?? [];
            $sentCatIds = [];

            foreach ($categories as $catIdx => $catData) {
                $catId = $catData['id'] ?? null;

                // Upsert category
                if ($catId) {
                    $category = SurveiCategory::where('id', $catId)
                                              ->where('survei_id', $survei->id)
                                              ->first();
                    if ($category) {
                        $category->update([
                            'name'   => $catData['name'],
                            'urutan' => $catData['urutan'] ?? $catIdx,
                        ]);
                    } else {
                        // Create if not found (safety)
                        $category = SurveiCategory::create([
                            'survei_id' => $survei->id,
                            'name'      => $catData['name'],
                            'urutan'    => $catData['urutan'] ?? $catIdx,
                        ]);
                    }
                } else {
                    $category = SurveiCategory::create([
                        'survei_id' => $survei->id,
                        'name'      => $catData['name'],
                        'urutan'    => $catData['urutan'] ?? $catIdx,
                    ]);
                }

                $sentCatIds[] = $category->id;

                // Sync questions
                $questions  = $catData['questions'] ?? [];
                $sentQIds   = [];

                foreach ($questions as $qIdx => $qData) {
                    $qId = $qData['id'] ?? null;

                    if ($qId) {
                        $question = SurveiQuestion::where('id', $qId)
                                                  ->where('category_id', $category->id)
                                                  ->first();
                        if ($question) {
                            $question->update([
                                'label'  => $qData['label'],
                                'answer' => $qData['answer'] ?? null,
                                'notes'  => $qData['notes'] ?? null,
                                'urutan' => $qData['urutan'] ?? $qIdx,
                            ]);
                        } else {
                            $question = SurveiQuestion::create([
                                'survei_id'   => $survei->id,
                                'category_id' => $category->id,
                                'label'       => $qData['label'],
                                'answer'      => $qData['answer'] ?? null,
                                'notes'       => $qData['notes'] ?? null,
                                'urutan'      => $qData['urutan'] ?? $qIdx,
                            ]);
                        }
                    } else {
                        $question = SurveiQuestion::create([
                            'survei_id'   => $survei->id,
                            'category_id' => $category->id,
                            'label'       => $qData['label'],
                            'answer'      => $qData['answer'] ?? null,
                            'notes'       => $qData['notes'] ?? null,
                            'urutan'      => $qData['urutan'] ?? $qIdx,
                        ]);
                    }

                    $sentQIds[] = $question->id;
                }

                // Delete removed questions
                SurveiQuestion::where('category_id', $category->id)
                               ->whereNotIn('id', $sentQIds)
                               ->delete();
            }

            // Delete removed categories (cascade deletes questions)
            SurveiCategory::where('survei_id', $survei->id)
                           ->whereNotIn('id', $sentCatIds)
                           ->delete();
        });

        return response()->json([
            'message' => 'Form survei berhasil disimpan',
            'data'    => $this->formatSurvei($survei->fresh(['categories.questions']), true),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    /**
     * DELETE /api/surveis/{survei}
     */
    public function destroy(Survei $survei): JsonResponse
    {
        $survei->delete();

        return response()->json(['message' => 'Survei berhasil dihapus']);
    }
}
