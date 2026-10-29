<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Ambil pengaturan PDF penawaran
     */
    public function getPenawaranPdf(): JsonResponse
    {
        $setting = Setting::where('key', 'penawaran_pdf')->first();

        return response()->json([
            'success' => true,
            'data' => $setting ? $setting->value : null,
        ]);
    }

    /**
     * Simpan pengaturan PDF penawaran
     */
    public function savePenawaranPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pageSize' => 'nullable|string',
            'orientation' => 'nullable|string',
            'showHeader' => 'nullable|boolean',
            'showHeaderImage' => 'nullable|boolean',
            'headerTitle' => 'nullable|string',
            'headerSubtitle' => 'nullable|string',
            'headerImage' => 'nullable|string',
            'headerImageHeight' => 'nullable|numeric',
            'showFooter' => 'nullable|boolean',
            'showFooterImage' => 'nullable|boolean',
            'footerText' => 'nullable|string',
            'footerImage' => 'nullable|string',
            'footerImageHeight' => 'nullable|numeric',
            'showWatermark' => 'nullable|boolean',
            'showWatermarkImage' => 'nullable|boolean',
            'watermarkText' => 'nullable|string',
            'watermarkImage' => 'nullable|string',
            'documentIntro' => 'nullable|string',
            'showScopeOfWork' => 'nullable|boolean',
            'scopeOfWork' => 'nullable|string',
            'showTerms' => 'nullable|boolean',
            'termsText' => 'nullable|string',
            'showSignature' => 'nullable|boolean',
            'signatureName' => 'nullable|string',
            'signatureTitle' => 'nullable|string',
            'signatureImage' => 'nullable|string',
            'marginTop' => 'nullable|numeric',
            'marginRight' => 'nullable|numeric',
            'marginBottom' => 'nullable|numeric',
            'marginLeft' => 'nullable|numeric',
            'fontSize' => 'nullable|numeric',
        ]);

        $setting = Setting::firstOrCreate(
            ['key' => 'penawaran_pdf'],
            ['group' => 'penawaran', 'value' => []]
        );

        $merged = array_merge($setting->value ?? [], $request->all());
        $setting->value = $merged;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan PDF penawaran berhasil disimpan.',
            'data' => $setting->value,
        ]);
    }

    /**
     * Upload gambar kop/footer/watermark/tanda tangan ke storage
     */
    public function uploadPenawaranPdfImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240', // max 10MB
            'type' => 'required|string|in:headerImage,footerImage,watermarkImage,signatureImage',
        ]);

        $type = $request->input('type');
        $setting = Setting::firstOrCreate(
            ['key' => 'penawaran_pdf'],
            ['group' => 'penawaran', 'value' => []]
        );

        $current = $setting->value ?? [];

        // Hapus file lama jika ada di public storage
        if (!empty($current[$type])) {
            $oldPath = str_replace('/storage/', '', $current[$type]);
            // Pastikan URL lama bukan base64 atau eksternal
            if (!str_starts_with($current[$type], 'data:') && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('file')->store('pdf_settings', 'public');
        $url = Storage::url($path);

        $current[$type] = $url;
        $setting->value = $current;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil disimpan ke storage.',
            'url' => $url,
            'path' => $path,
            'type' => $type,
            'data' => $setting->value,
        ]);
    }

    /**
     * Hapus gambar tertentu dari storage
     */
    public function deletePenawaranPdfImage(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:headerImage,footerImage,watermarkImage,signatureImage',
        ]);

        $type = $request->input('type');
        $setting = Setting::where('key', 'penawaran_pdf')->first();

        if ($setting) {
            $current = $setting->value ?? [];
            if (!empty($current[$type])) {
                $oldPath = str_replace('/storage/', '', $current[$type]);
                if (!str_starts_with($current[$type], 'data:') && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                $current[$type] = '';
                $setting->value = $current;
                $setting->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil dihapus.',
            'type' => $type,
            'data' => $setting ? $setting->value : null,
        ]);
    }
}

