<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query();

        // Apply filters
        if ($request->filled('type') && $request->type !== 'semua') {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        }

        $transactions = $query->orderBy('date', 'desc')
                              ->orderBy('id', 'desc')
                              ->paginate(15);

        return response()->json([
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Get statistics for the dashboard/pembukuan view.
     */
    public function stats(Request $request): JsonResponse
    {
        // 1. Current Month Totals
        $currentMonth = $request->input('month', Carbon::now()->month);
        $currentYear = $request->input('year', Carbon::now()->year);

        $totalMasuk = Transaction::whereMonth('date', $currentMonth)
                                 ->whereYear('date', $currentYear)
                                 ->masuk()
                                 ->sum('amount');

        $totalKeluar = Transaction::whereMonth('date', $currentMonth)
                                  ->whereYear('date', $currentYear)
                                  ->keluar()
                                  ->sum('amount');

        $saldoBersih = $totalMasuk - $totalKeluar;

        // 2. Bar Chart Data (last 6 months including current)
        $barData = [];
        for ($i = 5; $i >= 0; $i--) {
            $targetDate = Carbon::createFromDate($currentYear, $currentMonth, 1)->subMonthsNoOverflow($i);
            
            $masuk = Transaction::whereMonth('date', $targetDate->month)
                                ->whereYear('date', $targetDate->year)
                                ->masuk()
                                ->sum('amount');
                                
            $keluar = Transaction::whereMonth('date', $targetDate->month)
                                 ->whereYear('date', $targetDate->year)
                                 ->keluar()
                                 ->sum('amount');

            // Format month name (e.g., 'Jan', 'Feb')
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $monthLabel = $monthNames[$targetDate->month - 1];

            // Convert amount to millions (jt) for chart display logic if needed, 
            // but returning raw values is safer for frontend flexibility. 
            // We'll return raw and let frontend format.
            $barData[] = [
                'month' => $monthLabel,
                'raw_month' => $targetDate->month,
                'raw_year' => $targetDate->year,
                'masuk' => $masuk,
                'keluar' => $keluar,
                // Millions format for chart
                'masuk_jt' => round($masuk / 1000000, 1),
                'keluar_jt' => round($keluar / 1000000, 1),
            ];
        }

        return response()->json([
            'current_period' => [
                'month' => $currentMonth,
                'year' => $currentYear,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_bersih' => $saldoBersih,
            ],
            'chart_data' => $barData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:masuk,keluar',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'nullable|string|max:100',
            'proyekId' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
        ]);

        $transaction = Transaction::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'date' => $validated['date'],
            'category' => $validated['category'] ?? null,
            'proyek_id' => $validated['proyekId'] ?? null,
            'amount' => $validated['amount'],
            'created_by' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil ditambahkan',
            'data' => new TransactionResource($transaction)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json([
            'data' => new TransactionResource($transaction)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:masuk,keluar',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'nullable|string|max:100',
            'proyekId' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
        ]);

        $transaction->update([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'date' => $validated['date'],
            'category' => $validated['category'] ?? null,
            'proyek_id' => $validated['proyekId'] ?? null,
            'amount' => $validated['amount'],
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil diupdate',
            'data' => new TransactionResource($transaction)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dihapus'
        ]);
    }
}
