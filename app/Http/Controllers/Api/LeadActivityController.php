<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadActivityController extends Controller
{
    /**
     * GET /api/leads/{lead}/activities
     * List all activities for a lead.
     */
    public function index(Lead $lead): JsonResponse
    {
        $activities = $lead->activities()->with('createdBy')->orderBy('date')->orderBy('id')->get();

        return response()->json([
            'data' => $activities->map(fn($a) => [
                'id'              => $a->id,
                'lead_id'         => $a->lead_id,
                'title'           => $a->title,
                'description'     => $a->description,
                'date'            => $a->date?->format('Y-m-d'),
                'created_by'      => $a->created_by,
                'created_by_name' => $a->createdBy ? $a->createdBy->name : 'Sistem',
                'created_at'      => $a->created_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ]);
    }

    /**
     * POST /api/leads/{lead}/activities
     * Add a new activity and optionally advance lead status.
     */
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:100',
            'description' => 'nullable|string',
            'date'        => 'required|date',
        ]);

        $activity = LeadActivity::create([
            'lead_id'     => $lead->id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date'        => $validated['date'],
            'created_by'  => $request->user()?->id,
        ]);

        // Auto-advance lead status based on the SOP step recorded
        $statusMap = [
            'Prospek'   => 'Prospek',
            'Survei'    => 'Survey',
            'Survey'    => 'Survey',
            'Penawaran' => 'Penawaran',
            'Follow Up' => 'Penawaran',
            'Deal'      => 'Deal',
        ];

        $newStatus = $statusMap[$validated['title']] ?? null;
        if ($newStatus && in_array($newStatus, \App\Models\Lead::VALID_STATUSES)) {
            $lead->update(['status' => $newStatus]);
        }

        return response()->json([
            'message'  => 'Aktivitas berhasil dicatat',
            'data'     => [
                'id'          => $activity->id,
                'lead_id'     => $activity->lead_id,
                'title'       => $activity->title,
                'description' => $activity->description,
                'date'        => $activity->date?->format('Y-m-d'),
                'created_by'  => $activity->created_by,
                'created_at'  => $activity->created_at?->format('Y-m-d H:i:s'),
            ],
            'lead_status' => $lead->fresh()->status,
        ], 201);
    }

    /**
     * DELETE /api/leads/{lead}/activities/{activity}
     */
    public function destroy(Lead $lead, LeadActivity $activity): JsonResponse
    {
        abort_if($activity->lead_id !== $lead->id, 404);
        $activity->delete();

        return response()->json(['message' => 'Aktivitas berhasil dihapus']);
    }
}
