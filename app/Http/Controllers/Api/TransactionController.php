<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    /**
     * Nama bulan dalam Bahasa Indonesia (untuk label periode).
     */
    private const BULAN = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /**
     * Tentukan rentang tanggal & label periode laporan.
     *
     * period = month    → bulan terpilih (contoh: Juli 2026)
     * period = semester → semester berjalan (Semester 1 = Jan–Jun, Semester 2 = Jul–Des)
     * period = year     → satu tahun penuh
     */
    private function resolvePeriod(Request $request): array
    {
        $period = (string) $request->input('period', 'month');
        if (!in_array($period, ['month', 'semester', 'year', 'custom'], true)) {
            $period = 'month';
        }

        // Rentang tanggal kustom (dipakai date range picker) menggantikan preset
        if ($request->filled('start_date') && $request->filled('end_date')) {
            try {
                $customStart = Carbon::parse((string) $request->input('start_date'))->startOfDay();
                $customEnd   = Carbon::parse((string) $request->input('end_date'))->endOfDay();

                if ($customStart->gt($customEnd)) {
                    [$customStart, $customEnd] = [$customEnd, $customStart];
                }

                return [
                    'period' => 'custom',
                    'year'   => (int) $request->input('year', $customStart->year),
                    'month'  => (int) $request->input('month', $customStart->month),
                    'start'  => $customStart,
                    'end'    => $customEnd,
                    'label'  => 'Rentang Tanggal',
                ];
            } catch (\Throwable $e) {
                // Tanggal tidak valid → lanjut memakai preset
            }
        }

        $now   = Carbon::now();
        $year  = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        if ($period === 'year') {
            $start = Carbon::createFromDate($year, 1, 1)->startOfDay();
            $end   = Carbon::createFromDate($year, 12, 1)->endOfMonth()->endOfDay();
            $label = 'Tahun ' . $year;
        } elseif ($period === 'semester') {
            $semester  = $month <= 6 ? 1 : 2;
            $startMonth = $semester === 1 ? 1 : 7;
            $endMonth   = $semester === 1 ? 6 : 12;
            $start = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
            $end   = Carbon::createFromDate($year, $endMonth, 1)->endOfMonth()->endOfDay();
            $label = 'Semester ' . $semester . ' ' . $year;
        } else {
            $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $end   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
            $label = self::BULAN[$month] . ' ' . $year;
        }

        return [
            'period' => $period,
            'year'   => $year,
            'month'  => $month,
            'start'  => $start,
            'end'    => $end,
            'label'  => $label,
        ];
    }

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

        // Filter periode: bulan / semester / tahun
        $range = $this->resolvePeriod($request);
        $query->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()]);

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
        // 1. Total untuk periode terpilih (bulan / semester / tahun)
        $range = $this->resolvePeriod($request);

        $totalMasuk = (float) (Transaction::whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
                                 ->masuk()
                                 ->sum('amount') ?? 0);

        $totalKeluar = (float) (Transaction::whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
                                  ->keluar()
                                  ->sum('amount') ?? 0);

        $saldoBersih = $totalMasuk - $totalKeluar;

        // 2. Bar Chart Data — menyesuaikan periode:
        //    month    → 6 bulan terakhir (termasuk bulan terpilih)
        //    semester → 6 bulan dalam semester berjalan
        //    year     → 12 bulan dalam satu tahun
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $chartMonths = [];
        if ($range['period'] === 'custom') {
            // Agregasi per bulan sepanjang rentang_custom (maks. 24 bar)
            $cursor = Carbon::createFromDate($range['start']->year, $range['start']->month, 1);
            $lastMonth = Carbon::createFromDate($range['end']->year, $range['end']->month, 1);
            $guard = 0;
            while ($cursor->lte($lastMonth) && $guard < 24) {
                $chartMonths[] = $cursor->copy();
                $cursor->addMonthNoOverflow();
                $guard++;
            }
        } elseif ($range['period'] === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $chartMonths[] = Carbon::createFromDate($range['year'], $m, 1);
            }
        } elseif ($range['period'] === 'semester') {
            $startMonth = $range['month'] <= 6 ? 1 : 7;
            for ($m = $startMonth; $m < $startMonth + 6; $m++) {
                $chartMonths[] = Carbon::createFromDate($range['year'], $m, 1);
            }
        } else {
            for ($i = 5; $i >= 0; $i--) {
                $chartMonths[] = Carbon::createFromDate($range['year'], $range['month'], 1)->subMonthsNoOverflow($i);
            }
        }

        $barData = [];
        foreach ($chartMonths as $targetDate) {
            $masuk = (float) (Transaction::whereMonth('date', $targetDate->month)
                                ->whereYear('date', $targetDate->year)
                                ->masuk()
                                ->sum('amount') ?? 0);

            $keluar = (float) (Transaction::whereMonth('date', $targetDate->month)
                                 ->whereYear('date', $targetDate->year)
                                 ->keluar()
                                 ->sum('amount') ?? 0);

            $barData[] = [
                'month' => $monthNames[$targetDate->month - 1],
                'raw_month' => $targetDate->month,
                'raw_year' => $targetDate->year,
                'masuk' => $masuk,
                'keluar' => $keluar,
                // Format juta untuk kebutuhan chart
                'masuk_jt' => round($masuk / 1000000, 1),
                'keluar_jt' => round($keluar / 1000000, 1),
            ];
        }

        return response()->json([
            'current_period' => [
                'period' => $range['period'],
                'month' => $range['month'],
                'year' => $range['year'],
                'label' => $range['label'],
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
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
