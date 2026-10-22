<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->input('auditable_type') . '%');
        }

        if ($request->has('auditable_id')) {
            $query->where('auditable_id', $request->input('auditable_id'));
        }

        if ($request->has('survei_id')) {
            $surveiId = $request->input('survei_id');
            $query->where(function ($q) use ($surveiId) {
                $q->where(function ($sub) use ($surveiId) {
                    $sub->where('auditable_type', 'like', '%Survei')
                        ->where('auditable_id', $surveiId);
                })->orWhere(function ($sub) use ($surveiId) {
                    $sub->where('auditable_type', 'like', '%SurveiCategory')
                        ->whereIn('auditable_id', \App\Models\SurveiCategory::where('survei_id', $surveiId)->pluck('id'));
                })->orWhere(function ($sub) use ($surveiId) {
                    $sub->where('auditable_type', 'like', '%SurveiQuestion')
                        ->whereIn('auditable_id', \App\Models\SurveiQuestion::where('survei_id', $surveiId)->pluck('id'));
                });
            });
        }

        $logs = $query->latest()->paginate(50);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id'             => $log->id,
                'user_name'      => $log->user ? $log->user->name : 'Sistem',
                'action'         => $log->action,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id'   => $log->auditable_id,
                'old_values'     => $log->old_values,
                'new_values'     => $log->new_values,
                'ip_address'     => $log->ip_address,
                'created_at'     => $log->created_at->format('Y-m-d H:i:s'),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ]
        ]);
    }
}
