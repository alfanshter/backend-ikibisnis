<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialEntryController extends Controller
{
    /**
     * List all financial entries, optionally filtered by type or date.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinancialEntry::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date')) {
            $query->where('date', '<=', $request->date);
        }

        $entries = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json(['data' => $entries]);
    }

    /**
     * Store a new financial entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'   => 'required|in:aset_lancar,aset_tetap,liabilitas_pendek,liabilitas_panjang,ekuitas',
            'label'  => 'required|string|max:255',
            'amount' => 'required|numeric',
            'date'   => 'required|date',
            'notes'  => 'nullable|string|max:500',
        ]);

        $entry = FinancialEntry::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Entri berhasil ditambahkan',
            'data'    => $entry,
        ], 201);
    }

    /**
     * Show a specific financial entry.
     */
    public function show(FinancialEntry $financialEntry): JsonResponse
    {
        return response()->json(['data' => $financialEntry]);
    }

    /**
     * Update a financial entry.
     */
    public function update(Request $request, FinancialEntry $financialEntry): JsonResponse
    {
        $validated = $request->validate([
            'type'   => 'required|in:aset_lancar,aset_tetap,liabilitas_pendek,liabilitas_panjang,ekuitas',
            'label'  => 'required|string|max:255',
            'amount' => 'required|numeric',
            'date'   => 'required|date',
            'notes'  => 'nullable|string|max:500',
        ]);

        $financialEntry->update($validated);

        return response()->json([
            'message' => 'Entri berhasil diperbarui',
            'data'    => $financialEntry->fresh(),
        ]);
    }

    /**
     * Delete a financial entry.
     */
    public function destroy(FinancialEntry $financialEntry): JsonResponse
    {
        $financialEntry->delete();

        return response()->json(['message' => 'Entri berhasil dihapus']);
    }
}
