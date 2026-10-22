<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialEntry;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // ARUS KAS
    //
    // Kategori transaksi dikelompokkan ke 3 aktivitas standar:
    //   Operasional  → penerimaan, dp, material, fee_tim, operasional, listrik, dll
    //   Investasi    → peralatan, kendaraan, laptop, aset
    //   Pendanaan    → pinjaman, cicilan, kredit
    //
    // Semua transaksi masuk = positif, keluar = negatif
    // ═══════════════════════════════════════════════════════════════════════

    // Mapping category keywords → aktivitas arus kas
    private const KAS_MAPPING = [
        'operasional' => [
            'penerimaan', 'dp', 'invoice', 'pembayaran_klien', 'klien',
            'material', 'supplier', 'fee_tim', 'fee', 'tim',
            'transport', 'operasional', 'listrik', 'internet', 'makan',
            'bensin', 'lain',
        ],
        'investasi' => [
            'peralatan', 'alat', 'laptop', 'komputer', 'kendaraan',
            'inventaris', 'aset', 'investasi', 'toolset',
        ],
        'pendanaan' => [
            'pinjaman', 'cicilan', 'kredit', 'modal', 'pendanaan', 'hutang_bank',
        ],
    ];

    private function mapCategoryToAktivitas(?string $category): string
    {
        if (!$category) return 'operasional';
        $cat = strtolower($category);
        foreach (self::KAS_MAPPING as $aktivitas => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($cat, $kw)) return $aktivitas;
            }
        }
        return 'operasional'; // default
    }

    public function arusKas(Request $request): JsonResponse
    {
        $month = (int) $request->input('month', Carbon::now()->month);
        $year  = (int) $request->input('year', Carbon::now()->year);

        $transactions = Transaction::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get(['type', 'name', 'date', 'category', 'amount']);

        // Group by aktivitas
        $grouped = [
            'operasional' => [],
            'investasi'   => [],
            'pendanaan'   => [],
        ];

        foreach ($transactions as $tx) {
            $aktivitas = $this->mapCategoryToAktivitas($tx->category);
            $amount = $tx->type === 'masuk' ? $tx->amount : -$tx->amount;
            $grouped[$aktivitas][] = [
                'label'    => $tx->name,
                'amount'   => $amount,
                'category' => $tx->category,
                'date'     => $tx->date->format('Y-m-d'),
                'type'     => $tx->type,
            ];
        }

        // Subtotals
        $subtotals = [];
        foreach ($grouped as $key => $items) {
            $subtotals[$key] = array_sum(array_column($items, 'amount'));
        }

        $netCashFlow = array_sum($subtotals);

        // Saldo awal = semua transaksi sebelum periode ini (kumulatif)
        $targetDate   = Carbon::createFromDate($year, $month, 1);
        $saldoAwal    = $this->getSaldoKumulatif($targetDate->copy()->subDay());
        $saldoAkhir   = $saldoAwal + $netCashFlow;

        // Trend 6 bulan (untuk chart)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $targetDate->copy()->subMonthsNoOverflow($i);
            $masuk  = Transaction::whereMonth('date', $d->month)->whereYear('date', $d->year)->masuk()->sum('amount');
            $keluar = Transaction::whereMonth('date', $d->month)->whereYear('date', $d->year)->keluar()->sum('amount');
            $net    = $masuk - $keluar;
            $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            $trend[] = [
                'month'     => $monthNames[$d->month - 1],
                'raw_month' => $d->month,
                'raw_year'  => $d->year,
                'masuk'     => $masuk,
                'keluar'    => $keluar,
                'net'       => $net,
                'net_jt'    => round($net / 1_000_000, 1),
            ];
        }

        return response()->json([
            'period' => ['month' => $month, 'year' => $year],
            'sections' => [
                [
                    'key'      => 'operasional',
                    'title'    => 'Aktivitas Operasional',
                    'color'    => '#22c55e',
                    'items'    => $grouped['operasional'],
                    'subtotal' => $subtotals['operasional'],
                ],
                [
                    'key'      => 'investasi',
                    'title'    => 'Aktivitas Investasi',
                    'color'    => '#3b82f6',
                    'items'    => $grouped['investasi'],
                    'subtotal' => $subtotals['investasi'],
                ],
                [
                    'key'      => 'pendanaan',
                    'title'    => 'Aktivitas Pendanaan',
                    'color'    => '#a78bfa',
                    'items'    => $grouped['pendanaan'],
                    'subtotal' => $subtotals['pendanaan'],
                ],
            ],
            'net_cash_flow' => $netCashFlow,
            'saldo_awal'    => $saldoAwal,
            'saldo_akhir'   => $saldoAkhir,
            'trend'         => $trend,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LABA RUGI
    //
    // Pendapatan  = transactions masuk yang terkait dengan pendapatan usaha
    // HPP         = material, fee_tim, transport (biaya langsung proyek)
    // Beban Opex  = gaji, sewa, listrik, pemasaran, depresiasi, lain
    // Non-Operasional = bunga
    // ═══════════════════════════════════════════════════════════════════════

    private const LR_MAPPING = [
        'pendapatan'  => ['penerimaan', 'dp', 'invoice', 'klien', 'proyek', 'jasa', 'lain_masuk'],
        'hpp'         => ['material', 'supplier', 'fee_tim', 'fee', 'tim', 'transport', 'subkon'],
        'beban_opex'  => ['gaji', 'sewa', 'listrik', 'internet', 'pemasaran', 'depresiasi', 'kantor'],
        'non_operasional' => ['bunga', 'bank', 'pinjaman', 'investasi'],
    ];

    private function mapCategoryToLR(?string $category, string $txType): string
    {
        if (!$category) return $txType === 'masuk' ? 'pendapatan' : 'beban_opex';
        $cat = strtolower($category);
        foreach (self::LR_MAPPING as $section => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($cat, $kw)) {
                    // Ensure masuk → pendapatan/non_op, keluar → hpp/beban/non_op
                    if ($txType === 'masuk' && in_array($section, ['pendapatan', 'non_operasional'])) return $section;
                    if ($txType === 'keluar' && in_array($section, ['hpp', 'beban_opex', 'non_operasional'])) return $section;
                }
            }
        }
        return $txType === 'masuk' ? 'pendapatan' : 'beban_opex';
    }

    public function labaRugi(Request $request): JsonResponse
    {
        $month = (int) $request->input('month', Carbon::now()->month);
        $year  = (int) $request->input('year', Carbon::now()->year);

        $transactions = Transaction::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get(['type', 'name', 'date', 'category', 'amount']);

        $grouped = [
            'pendapatan'      => [],
            'hpp'             => [],
            'beban_opex'      => [],
            'non_operasional' => [],
        ];

        foreach ($transactions as $tx) {
            $section = $this->mapCategoryToLR($tx->category, $tx->type);
            $amount  = $tx->type === 'masuk' ? $tx->amount : $tx->amount; // store as positive, sign handled by section
            $grouped[$section][] = [
                'label'  => $tx->name,
                'amount' => (int) $amount,
                'type'   => $tx->type,
                'date'   => $tx->date->format('Y-m-d'),
            ];
        }

        $totalPendapatan    = array_sum(array_column($grouped['pendapatan'], 'amount'));
        $totalHPP           = array_sum(array_column($grouped['hpp'], 'amount'));
        $labaKotor          = $totalPendapatan - $totalHPP;
        $totalBeban         = array_sum(array_column($grouped['beban_opex'], 'amount'));
        $labaOperasional    = $labaKotor - $totalBeban;

        // Non-operasional: masuk = positive, keluar = negative
        $nonOpTotal = 0;
        $nonOpItems = [];
        foreach ($grouped['non_operasional'] as $item) {
            $signed = $item['type'] === 'masuk' ? $item['amount'] : -$item['amount'];
            $nonOpItems[] = array_merge($item, ['signed_amount' => $signed]);
            $nonOpTotal += $signed;
        }

        $labaSebelumPajak = $labaOperasional + $nonOpTotal;
        $pajak            = $labaSebelumPajak > 0 ? round($labaSebelumPajak * 0.11) : 0;
        $labaBersih       = $labaSebelumPajak - $pajak;

        $marginKotor  = $totalPendapatan > 0 ? round($labaKotor  / $totalPendapatan * 100, 2) : 0;
        $marginBersih = $totalPendapatan > 0 ? round($labaBersih / $totalPendapatan * 100, 2) : 0;
        $hppRatio     = $totalPendapatan > 0 ? round($totalHPP   / $totalPendapatan * 100, 2) : 0;

        // Trend 6 bulan laba bersih
        $targetDate = Carbon::createFromDate($year, $month, 1);
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d      = $targetDate->copy()->subMonthsNoOverflow($i);
            $masuk  = Transaction::whereMonth('date', $d->month)->whereYear('date', $d->year)->masuk()->sum('amount');
            $keluar = Transaction::whereMonth('date', $d->month)->whereYear('date', $d->year)->keluar()->sum('amount');
            $net    = $masuk - $keluar;
            $laba   = $net > 0 ? round($net * 0.89) : $net; // rough approximation after tax
            $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            $trend[] = [
                'month'   => $monthNames[$d->month - 1],
                'raw_month' => $d->month,
                'raw_year'  => $d->year,
                'laba'    => $laba,
                'laba_jt' => round($laba / 1_000_000, 1),
            ];
        }

        return response()->json([
            'period' => ['month' => $month, 'year' => $year],
            'pendapatan' => [
                'items' => $grouped['pendapatan'],
                'total' => $totalPendapatan,
            ],
            'hpp' => [
                'items' => $grouped['hpp'],
                'total' => $totalHPP,
            ],
            'beban_opex' => [
                'items' => $grouped['beban_opex'],
                'total' => $totalBeban,
            ],
            'non_operasional' => [
                'items' => $nonOpItems,
                'total' => $nonOpTotal,
            ],
            'summary' => [
                'laba_kotor'          => $labaKotor,
                'laba_operasional'    => $labaOperasional,
                'laba_sebelum_pajak'  => $labaSebelumPajak,
                'pajak'               => $pajak,
                'laba_bersih'         => $labaBersih,
                'margin_kotor'        => $marginKotor,
                'margin_bersih'       => $marginBersih,
                'hpp_ratio'           => $hppRatio,
            ],
            'trend' => $trend,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NERACA
    //
    // Kas = saldo kumulatif transaksi s.d. tanggal tertentu (real dari DB)
    // Aset tetap, liabilitas, ekuitas = dari tabel financial_entries
    // ═══════════════════════════════════════════════════════════════════════

    public function neraca(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::now()->toDateString());
        $dateObj = Carbon::parse($date);

        // Kas dari transactions
        $kasAmount = $this->getSaldoKumulatif($dateObj);

        // Financial entries s.d. tanggal ini
        $entries = FinancialEntry::where('date', '<=', $date)->get();

        // Group entries
        $asetLancarEntries   = $entries->where('type', 'aset_lancar')->values();
        $asetTetapEntries    = $entries->where('type', 'aset_tetap')->values();
        $liabPendekEntries   = $entries->where('type', 'liabilitas_pendek')->values();
        $liabPanjangEntries  = $entries->where('type', 'liabilitas_panjang')->values();
        $ekuitasEntries      = $entries->where('type', 'ekuitas')->values();

        $formatEntry = fn($item) => [
            'id'     => $item->id,
            'label'  => $item->label,
            'amount' => $item->amount,
            'date'   => $item->date->format('Y-m-d'),
            'type'   => $item->type,
        ];

        // Inject kas ke aset lancar
        $kasItem = ['id' => null, 'label' => 'Kas dan Setara Kas', 'amount' => $kasAmount, 'date' => $date, 'type' => 'aset_lancar'];
        $asetLancarList = array_merge([$kasItem], $asetLancarEntries->map($formatEntry)->toArray());

        $asetTetapList   = $asetTetapEntries->map($formatEntry)->toArray();
        $liabPendekList  = $liabPendekEntries->map($formatEntry)->toArray();
        $liabPanjangList = $liabPanjangEntries->map($formatEntry)->toArray();
        $ekuitasList     = $ekuitasEntries->map($formatEntry)->toArray();

        // Totals
        $totalAsetLancar  = array_sum(array_column($asetLancarList, 'amount'));
        $totalAsetTetap   = array_sum(array_column($asetTetapList, 'amount'));
        $totalAset        = $totalAsetLancar + $totalAsetTetap;
        $totalLiabPendek  = array_sum(array_column($liabPendekList, 'amount'));
        $totalLiabPanjang = array_sum(array_column($liabPanjangList, 'amount'));
        $totalLiab        = $totalLiabPendek + $totalLiabPanjang;
        $totalEkuitas     = array_sum(array_column($ekuitasList, 'amount'));
        $totalLiabEkuitas = $totalLiab + $totalEkuitas;

        // Rasio
        $currentRatio = $totalLiabPendek > 0 ? round($totalAsetLancar / $totalLiabPendek, 2) : 0;
        $debtRatio    = $totalAset > 0 ? round($totalLiab / $totalAset * 100, 1) : 0;
        $equityRatio  = $totalAset > 0 ? round($totalEkuitas / $totalAset * 100, 1) : 0;
        $isBalanced   = abs($totalAset - $totalLiabEkuitas) < 1000;

        return response()->json([
            'date' => $date,
            'aset' => [
                'lancar' => [
                    'items' => $asetLancarList,
                    'total' => $totalAsetLancar,
                ],
                'tetap' => [
                    'items' => $asetTetapList,
                    'total' => $totalAsetTetap,
                ],
                'total' => $totalAset,
            ],
            'liabilitas' => [
                'jangka_pendek' => [
                    'items' => $liabPendekList,
                    'total' => $totalLiabPendek,
                ],
                'jangka_panjang' => [
                    'items' => $liabPanjangList,
                    'total' => $totalLiabPanjang,
                ],
                'total' => $totalLiab,
            ],
            'ekuitas' => [
                'items' => $ekuitasList,
                'total' => $totalEkuitas,
            ],
            'total_liab_ekuitas' => $totalLiabEkuitas,
            'is_balanced' => $isBalanced,
            'rasio' => [
                'current_ratio' => $currentRatio,
                'debt_ratio'    => $debtRatio,
                'equity_ratio'  => $equityRatio,
            ],
        ]);
    }

    // ─── Helper ────────────────────────────────────────────────────────────────

    /**
     * Hitung saldo kumulatif transaksi sampai tanggal tertentu (inklusif)
     */
    private function getSaldoKumulatif(Carbon $date): int
    {
        $masuk  = Transaction::where('date', '<=', $date->toDateString())->masuk()->sum('amount');
        $keluar = Transaction::where('date', '<=', $date->toDateString())->keluar()->sum('amount');
        return (int) ($masuk - $keluar);
    }
}
