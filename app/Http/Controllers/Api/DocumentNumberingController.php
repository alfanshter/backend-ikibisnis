<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentNumbering;
use Illuminate\Http\Request;

class DocumentNumberingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $numberings = DocumentNumbering::orderBy('id', 'asc')->get()->map(function ($num) {
            $num->next_preview = \App\Services\DocumentNumberingService::formatPreview($num);
            return $num;
        });

        return response()->json($numberings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|unique:document_numberings,module',
            'module_label' => 'required|string|max:255',
            'format' => 'nullable|string|max:255',
            'starting_number' => 'required|integer|min:1',
            'digit' => 'required|integer|min:1',
            'current_number' => 'required|integer|min:0',
        ]);

        $numbering = DocumentNumbering::create($validated);

        return response()->json($numbering, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $numbering = DocumentNumbering::findOrFail($id);

        $validated = $request->validate([
            'module' => 'required|string|unique:document_numberings,module,' . $numbering->id,
            'module_label' => 'required|string|max:255',
            'format' => 'nullable|string|max:255',
            'starting_number' => 'required|integer|min:1',
            'digit' => 'required|integer|min:1',
            'current_number' => 'required|integer|min:0',
        ]);

        $numbering->update($validated);

        return response()->json($numbering);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $numbering = DocumentNumbering::findOrFail($id);
        $numbering->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
