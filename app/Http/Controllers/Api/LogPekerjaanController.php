<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogPekerjaan;
use App\Models\Proyek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogPekerjaanController extends Controller
{
    private function formatLog(LogPekerjaan $log): array
    {
        return [
            'id'                => $log->id,
            'proyek_id'         => $log->proyek_id,
            'tanggal'           => $log->tanggal?->format('Y-m-d'),
            'aktivitas'         => $log->aktivitas,
            'tim'               => $log->tim ?? [],
            'durasi'            => $log->durasi,
            'kendala'           => $log->kendala,
            'status_verifikasi' => $log->status_verifikasi ?? LogPekerjaan::STATUS_MENUNGGU_REVIEW,
            'catatan_revisi'    => $log->catatan_revisi,
            'verified_by'       => $log->verified_by,
            'verified_at'       => $log->verified_at?->format('Y-m-d H:i:s'),
            'verifier'          => $log->verifiedBy ? [
                'id'   => $log->verifiedBy->id,
                'name' => $log->verifiedBy->name,
                'role' => $log->verifiedBy->role?->name ?? 'Reviewer',
            ] : null,
            'fotos'             => $log->fotos->map(fn($f) => [
                'id'         => $f->id,
                'url'        => $f->url,
                'label'      => $f->label,
                'keterangan' => $f->keterangan ?? $f->label,
            ])->values(),
            'created_by'        => $log->created_by,
            'creator'           => $log->createdBy ? [
                'id'   => $log->createdBy->id,
                'name' => $log->createdBy->name,
                'role' => $log->createdBy->role?->name ?? 'Staf Lapangan',
            ] : null,
            'created_at'        => $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/log-pekerjaan
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $logs = $proyek->logPekerjaans()
            ->with(['fotos', 'createdBy.role', 'verifiedBy.role'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['data' => $logs->map(fn($l) => $this->formatLog($l))->values()]);
    }

    /**
     * POST /api/proyeks/{proyek}/log-pekerjaan
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'              => 'required|date',
            'aktivitas'            => 'required|string',
            'tim'                  => 'nullable|array',
            'tim.*'                => 'string',
            'durasi'               => 'nullable|string|max:50',
            'kendala'              => 'nullable|string',
            'new_fotos'            => 'nullable|array',
            'new_fotos.*'          => 'file|image|max:5120',
            'new_foto_labels'      => 'nullable|array',
            'new_foto_labels.*'    => 'nullable|string',
            'new_foto_keterangans'   => 'nullable|array',
            'new_foto_keterangans.*' => 'nullable|string',
        ]);

        $log = $proyek->logPekerjaans()->create([
            'tanggal'           => $validated['tanggal'],
            'aktivitas'         => $validated['aktivitas'],
            'tim'               => $validated['tim'] ?? null,
            'durasi'            => $validated['durasi'] ?? null,
            'kendala'           => $validated['kendala'] ?? null,
            'status_verifikasi' => LogPekerjaan::STATUS_MENUNGGU_REVIEW,
            'created_by'        => $request->user()?->id,
        ]);

        if ($request->hasFile('new_fotos')) {
            foreach ($request->file('new_fotos') as $index => $file) {
                $path = $file->store('log-pekerjaan', 'public');
                $label = $request->input("new_foto_labels.{$index}") ?: 'Dokumentasi';
                $keterangan = $request->input("new_foto_keterangans.{$index}");
                $log->fotos()->create([
                    'url'        => url('storage/' . $path),
                    'label'      => $label,
                    'keterangan' => $keterangan,
                ]);
            }
        }

        $log->load(['fotos', 'createdBy.role', 'verifiedBy.role']);

        return response()->json([
            'message' => 'Log pekerjaan ditambahkan',
            'data'    => $this->formatLog($log),
        ], 201);
    }

    /**
     * PUT /api/proyeks/{proyek}/log-pekerjaan/{log}
     */
    public function update(Request $request, Proyek $proyek, LogPekerjaan $log): JsonResponse
    {
        abort_if($log->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal'                  => 'sometimes|date',
            'aktivitas'                => 'sometimes|string',
            'tim'                      => 'nullable|array',
            'tim.*'                    => 'string',
            'durasi'                   => 'nullable|string|max:50',
            'kendala'                  => 'nullable|string',
            'existing_fotos'           => 'nullable|array',
            'existing_fotos.*.id'         => 'required|integer|exists:log_pekerjaan_fotos,id',
            'existing_fotos.*.label'      => 'nullable|string',
            'existing_fotos.*.keterangan' => 'nullable|string',
            'new_fotos'                => 'nullable|array',
            'new_fotos.*'              => 'file|image|max:5120',
            'new_foto_labels'          => 'nullable|array',
            'new_foto_labels.*'        => 'nullable|string',
            'new_foto_keterangans'     => 'nullable|array',
            'new_foto_keterangans.*'   => 'nullable|string',
        ]);

        $updateData = [
            'tanggal'   => $validated['tanggal'] ?? $log->tanggal,
            'aktivitas' => $validated['aktivitas'] ?? $log->aktivitas,
            'tim'       => array_key_exists('tim', $validated) ? $validated['tim'] : $log->tim,
            'durasi'    => array_key_exists('durasi', $validated) ? $validated['durasi'] : $log->durasi,
            'kendala'   => array_key_exists('kendala', $validated) ? $validated['kendala'] : $log->kendala,
        ];

        // If previously requested for revision, reset to Menunggu Review after edit
        if ($log->status_verifikasi === LogPekerjaan::STATUS_PERLU_REVISI) {
            $updateData['status_verifikasi'] = LogPekerjaan::STATUS_MENUNGGU_REVIEW;
        }

        $log->update($updateData);

        // Sync existing fotos
        $existingIds = [];
        if (!empty($validated['existing_fotos'])) {
            foreach ($validated['existing_fotos'] as $existing) {
                $existingIds[] = $existing['id'];
                $log->fotos()->where('id', $existing['id'])->update([
                    'label'      => $existing['label'] ?? null,
                    'keterangan' => $existing['keterangan'] ?? null,
                ]);
            }
        }
        
        // Delete fotos that are no longer present
        $log->fotos()->whereNotIn('id', $existingIds)->delete();

        // Process new fotos
        if ($request->hasFile('new_fotos')) {
            foreach ($request->file('new_fotos') as $index => $file) {
                $path = $file->store('log-pekerjaan', 'public');
                $label = $request->input("new_foto_labels.{$index}") ?: 'Dokumentasi';
                $keterangan = $request->input("new_foto_keterangans.{$index}");
                $log->fotos()->create([
                    'url'        => url('storage/' . $path),
                    'label'      => $label,
                    'keterangan' => $keterangan,
                ]);
            }
        }

        $log->load(['fotos', 'createdBy.role', 'verifiedBy.role']);

        return response()->json([
            'message' => 'Log pekerjaan diperbarui',
            'data'    => $this->formatLog($log),
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/log-pekerjaan/{log}/verifikasi
     */
    public function verifikasi(Request $request, Proyek $proyek, LogPekerjaan $log): JsonResponse
    {
        abort_if($log->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'status_verifikasi' => 'required|in:' . implode(',', LogPekerjaan::VALID_STATUS_VERIFIKASI),
            'catatan_revisi'    => 'nullable|string',
        ]);

        $log->update([
            'status_verifikasi' => $validated['status_verifikasi'],
            'catatan_revisi'    => $validated['catatan_revisi'] ?? null,
            'verified_by'       => $request->user()?->id,
            'verified_at'       => now(),
        ]);

        $log->load(['fotos', 'createdBy.role', 'verifiedBy.role']);

        $msg = match ($validated['status_verifikasi']) {
            LogPekerjaan::STATUS_SESUAI => 'Pekerjaan berhasil diverifikasi (Sesuai)',
            LogPekerjaan::STATUS_PERLU_REVISI => 'Status pekerjaan diubah ke Perlu Revisi',
            default => 'Status verifikasi direset ke Menunggu Review',
        };

        return response()->json([
            'message' => $msg,
            'data'    => $this->formatLog($log),
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/log-pekerjaan/{log}
     */
    public function destroy(Proyek $proyek, LogPekerjaan $log): JsonResponse
    {
        abort_if($log->proyek_id !== $proyek->id, 404);
        $log->delete();
        return response()->json(['message' => 'Log pekerjaan dihapus']);
    }
}
