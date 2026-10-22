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
            'id'         => $log->id,
            'proyek_id'  => $log->proyek_id,
            'tanggal'    => $log->tanggal?->format('Y-m-d'),
            'aktivitas'  => $log->aktivitas,
            'tim'        => $log->tim ?? [],
            'durasi'     => $log->durasi,
            'kendala'    => $log->kendala,
            'fotos'      => $log->fotos->map(fn($f) => [
                'id'    => $f->id,
                'url'   => $f->url,
                'label' => $f->label,
            ])->values(),
            'created_by' => $log->created_by,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/log-pekerjaan
     */
    public function index(Proyek $proyek): JsonResponse
    {
        $logs = $proyek->logPekerjaans()->with('fotos')->get();
        return response()->json(['data' => $logs->map(fn($l) => $this->formatLog($l))->values()]);
    }

    /**
     * POST /api/proyeks/{proyek}/log-pekerjaan
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'    => 'required|date',
            'aktivitas'  => 'required|string',
            'tim'        => 'nullable|array',
            'tim.*'      => 'string',
            'durasi'     => 'nullable|string|max:50',
            'kendala'    => 'nullable|string',
            'new_fotos'  => 'nullable|array',
            'new_fotos.*'=> 'file|image|max:5120',
            'new_foto_keterangans'   => 'nullable|array',
            'new_foto_keterangans.*' => 'nullable|string',
        ]);

        $log = $proyek->logPekerjaans()->create([
            'tanggal'    => $validated['tanggal'],
            'aktivitas'  => $validated['aktivitas'],
            'tim'        => $validated['tim'] ?? null,
            'durasi'     => $validated['durasi'] ?? null,
            'kendala'    => $validated['kendala'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        if ($request->hasFile('new_fotos')) {
            foreach ($request->file('new_fotos') as $index => $file) {
                $path = $file->store('log-pekerjaan', 'public');
                $keterangan = $request->input("new_foto_keterangans.{$index}");
                $log->fotos()->create([
                    'url'   => url('storage/' . $path),
                    'label' => $keterangan,
                ]);
            }
        }

        $log->load('fotos');

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
            'tanggal'       => 'sometimes|date',
            'aktivitas'     => 'sometimes|string',
            'tim'           => 'nullable|array',
            'tim.*'         => 'string',
            'durasi'        => 'nullable|string|max:50',
            'kendala'       => 'nullable|string',
            'existing_fotos'=> 'nullable|array',
            'existing_fotos.*.id'    => 'required|integer|exists:log_pekerjaan_fotos,id',
            'existing_fotos.*.label' => 'nullable|string',
            'new_fotos'     => 'nullable|array',
            'new_fotos.*'   => 'file|image|max:5120',
            'new_foto_keterangans'   => 'nullable|array',
            'new_foto_keterangans.*' => 'nullable|string',
        ]);

        $log->update([
            'tanggal'   => $validated['tanggal'] ?? $log->tanggal,
            'aktivitas' => $validated['aktivitas'] ?? $log->aktivitas,
            'tim'       => array_key_exists('tim', $validated) ? $validated['tim'] : $log->tim,
            'durasi'    => array_key_exists('durasi', $validated) ? $validated['durasi'] : $log->durasi,
            'kendala'   => array_key_exists('kendala', $validated) ? $validated['kendala'] : $log->kendala,
        ]);

        // Sync existing fotos
        $existingIds = [];
        if (!empty($validated['existing_fotos'])) {
            foreach ($validated['existing_fotos'] as $existing) {
                $existingIds[] = $existing['id'];
                $log->fotos()->where('id', $existing['id'])->update([
                    'label' => $existing['label'] ?? null,
                ]);
            }
        }
        
        // Delete fotos that are no longer present
        $log->fotos()->whereNotIn('id', $existingIds)->delete();

        // Process new fotos
        if ($request->hasFile('new_fotos')) {
            foreach ($request->file('new_fotos') as $index => $file) {
                $path = $file->store('log-pekerjaan', 'public');
                $keterangan = $request->input("new_foto_keterangans.{$index}");
                $log->fotos()->create([
                    'url'   => url('storage/' . $path),
                    'label' => $keterangan,
                ]);
            }
        }

        $log->load('fotos');

        return response()->json([
            'message' => 'Log pekerjaan diperbarui',
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
