<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekSerahTerima;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProyekSerahTerimaController extends Controller
{
    private function defaultChecklists(): array
    {
        return [
            'Pekerjaan telah selesai',
            'Equipment telah terpasang sesuai spesifikasi',
            'Sistem telah dilakukan testing',
            'Sistem berfungsi dengan normal',
            'Area kerja telah dibersihkan',
            'Dokumentasi pekerjaan tersedia',
            'Training / briefing user telah dilakukan'
        ];
    }

    private function generateBASTNumber(): string
    {
        return \App\Services\DocumentNumberingService::generateNumber('bast');
    }

    public function show(Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;

        if (!$st) {
            $st = ProyekSerahTerima::create([
                'proyek_id'  => $proyek->id,
                'status'     => 'Draft',
                'created_by' => request()->user()?->id,
                'pic_pws_id' => $proyek->pic_id,
                'pic_client_nama' => $proyek->lead?->pic_nama,
                'pic_client_jabatan' => 'Perwakilan Klien'
            ]);

            // Seed default checklists
            foreach ($this->defaultChecklists() as $idx => $text) {
                $st->checklists()->create(['text' => $text, 'sort_order' => $idx]);
            }
        }

        return response()->json(['data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    public function update(Request $request, Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;
        if (!$st) abort(404, 'Data tidak ditemukan');

        $validated = $request->validate([
            'tanggal'              => 'nullable|date',
            'waktu'                => 'nullable|string',
            'pic_pws_id'           => 'nullable|exists:users,id',
            'pic_client_nama'      => 'nullable|string|max:255',
            'pic_client_jabatan'   => 'nullable|string|max:255',
            'hasil_pekerjaan'      => 'nullable|string',
            'catatan_serah_terima' => 'nullable|string',
            'status'               => 'nullable|string',
        ]);

        $st->update($validated);

        return response()->json([
            'message' => 'Detail serah terima diperbarui',
            'data'    => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))
        ]);
    }

    // --- Checklists ---
    public function updateChecklists(Request $request, Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;
        $validated = $request->validate([
            'checklists' => 'required|array',
            'checklists.*.id' => 'nullable|integer',
            'checklists.*.text' => 'required|string',
            'checklists.*.status' => 'nullable|string',
            'checklists.*.catatan' => 'nullable|string',
        ]);

        // Sync checklists
        $st->checklists()->delete();
        foreach ($validated['checklists'] as $idx => $cData) {
            $st->checklists()->create([
                'text' => $cData['text'],
                'status' => $cData['status'] ?? null,
                'catatan' => $cData['catatan'] ?? null,
                'sort_order' => $idx
            ]);
        }
        return response()->json(['message' => 'Checklist diperbarui', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    // --- Equipments ---
    public function updateEquipments(Request $request, Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;
        $validated = $request->validate([
            'equipments' => 'required|array',
            'equipments.*.id' => 'nullable|integer',
            'equipments.*.nama_item' => 'required|string',
            'equipments.*.qty' => 'required|integer',
            'equipments.*.unit' => 'required|string',
            'equipments.*.sn' => 'nullable|string',
            'equipments.*.kondisi' => 'required|string',
            'equipments.*.catatan' => 'nullable|string',
        ]);

        $st->equipments()->delete();
        foreach ($validated['equipments'] as $idx => $eq) {
            $st->equipments()->create([
                'nama_item' => $eq['nama_item'],
                'qty' => $eq['qty'],
                'unit' => $eq['unit'],
                'sn' => $eq['sn'],
                'kondisi' => $eq['kondisi'],
                'catatan' => $eq['catatan'],
                'sort_order' => $idx
            ]);
        }
        return response()->json(['message' => 'Equipment diperbarui', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    // --- Punch Lists ---
    public function updatePunchLists(Request $request, Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;
        $validated = $request->validate([
            'punch_lists' => 'required|array',
            'punch_lists.*.id' => 'nullable|integer',
            'punch_lists.*.temuan' => 'required|string',
            'punch_lists.*.pic' => 'nullable|string',
            'punch_lists.*.deadline' => 'nullable|date',
            'punch_lists.*.status' => 'required|string',
            'punch_lists.*.catatan' => 'nullable|string',
        ]);

        $st->punchLists()->delete();
        foreach ($validated['punch_lists'] as $idx => $pl) {
            $st->punchLists()->create([
                'temuan' => $pl['temuan'],
                'pic' => $pl['pic'],
                'deadline' => $pl['deadline'],
                'status' => $pl['status'],
                'catatan' => $pl['catatan']
            ]);
        }
        return response()->json(['message' => 'Punch list diperbarui', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    // --- Files (Documents & Photos) ---
    public function uploadDocument(Request $request, Proyek $proyek): JsonResponse
    {
        $request->validate([
            'nama_dokumen' => 'required|string',
            'file' => 'required|file|max:10240'
        ]);

        $st = $proyek->serahTerima;
        $path = $request->file('file')->store('proyek_st_documents', 'public');

        $st->documents()->create([
            'nama_dokumen' => $request->nama_dokumen,
            'file_url' => Storage::url($path),
            'created_by' => $request->user()->id
        ]);

        return response()->json(['message' => 'Dokumen diupload', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }
    
    public function deleteDocument(Request $request, Proyek $proyek, $docId): JsonResponse
    {
        $doc = $proyek->serahTerima->documents()->findOrFail($docId);
        // Note: keeping file in storage or delete it. Let's delete it.
        $path = str_replace('/storage/', '', $doc->file_url);
        Storage::disk('public')->delete($path);
        $doc->delete();
        
        return response()->json(['message' => 'Dokumen dihapus', 'data' => $this->formatData($proyek->serahTerima->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    public function uploadPhoto(Request $request, Proyek $proyek): JsonResponse
    {
        $request->validate([
            'kategori' => 'required|string',
            'file' => 'required|image|max:10240',
            'caption' => 'nullable|string'
        ]);

        $st = $proyek->serahTerima;
        $path = $request->file('file')->store('proyek_st_photos', 'public');

        $st->photos()->create([
            'kategori' => $request->kategori,
            'file_url' => Storage::url($path),
            'caption' => $request->caption,
            'created_by' => $request->user()->id
        ]);

        return response()->json(['message' => 'Foto diupload', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    public function deletePhoto(Request $request, Proyek $proyek, $photoId): JsonResponse
    {
        $photo = $proyek->serahTerima->photos()->findOrFail($photoId);
        $path = str_replace('/storage/', '', $photo->file_url);
        Storage::disk('public')->delete($path);
        $photo->delete();
        
        return response()->json(['message' => 'Foto dihapus', 'data' => $this->formatData($proyek->serahTerima->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    // --- Signatures ---
    public function saveSignatures(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'ttd_pws' => 'nullable|string', // base64
            'ttd_client' => 'nullable|string' // base64
        ]);
        
        $st = $proyek->serahTerima;
        if (isset($validated['ttd_pws'])) {
            $st->ttd_pws = $validated['ttd_pws'];
            $st->ttd_pws_tgl = now();
        }
        if (isset($validated['ttd_client'])) {
            $st->ttd_client = $validated['ttd_client'];
            $st->ttd_client_tgl = now();
        }
        $st->save();
        
        return response()->json(['message' => 'Tanda tangan disimpan', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    // --- Complete Action ---
    public function complete(Request $request, Proyek $proyek): JsonResponse
    {
        $st = $proyek->serahTerima;
        
        if ($st->punchLists()->where('status', 'Open')->exists()) {
            return response()->json(['message' => 'Tidak dapat diselesaikan, masih ada punch list yang Open'], 422);
        }
        
        if (!$st->ttd_client) {
            return response()->json(['message' => 'Tidak dapat diselesaikan, tanda tangan client belum tersedia'], 422);
        }
        
        if (!$st->nomor_bast) {
            $st->nomor_bast = $this->generateBASTNumber();
        }

        DB::transaction(function () use ($st, $proyek) {
            $st->status = 'Selesai';
            $st->save();

            $proyek->status = 'Selesai';
            $proyek->save();
        });

        return response()->json(['message' => 'Serah terima diselesaikan', 'data' => $this->formatData($st->fresh(['checklists', 'equipments', 'documents', 'photos', 'punchLists']))]);
    }

    private function formatData(ProyekSerahTerima $st): array
    {
        return [
            'id' => $st->id,
            'proyek_id' => $st->proyek_id,
            'nomor_bast' => $st->nomor_bast,
            'tanggal' => $st->tanggal?->format('Y-m-d'),
            'waktu' => $st->waktu,
            'pic_pws_id' => $st->pic_pws_id,
            'pic_client_nama' => $st->pic_client_nama,
            'pic_client_jabatan' => $st->pic_client_jabatan,
            'status' => $st->status,
            'hasil_pekerjaan' => $st->hasil_pekerjaan,
            'catatan_serah_terima' => $st->catatan_serah_terima,
            'ttd_pws' => $st->ttd_pws,
            'ttd_client' => $st->ttd_client,
            'ttd_pws_tgl' => $st->ttd_pws_tgl,
            'ttd_client_tgl' => $st->ttd_client_tgl,
            'checklists' => $st->checklists,
            'equipments' => $st->equipments,
            'documents' => $st->documents,
            'photos' => $st->photos,
            'punch_lists' => $st->punchLists,
        ];
    }
}
