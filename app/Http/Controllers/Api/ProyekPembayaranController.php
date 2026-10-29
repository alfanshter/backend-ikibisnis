<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\ProyekInvoice;
use App\Models\ProyekPembayaran;
use App\Models\Transaction;
use App\Services\DocumentNumberingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekPembayaranController extends Controller
{
    private function formatPembayaran(ProyekPembayaran $p, bool $maskNominal = false): array
    {
        return [
            'id'                => $p->id,
            'proyek_id'         => $p->proyek_id,
            'proyek_invoice_id' => $p->proyek_invoice_id,
            'nomor_invoice'     => $p->invoice?->nomor_invoice,
            'transaction_id'    => $p->transaction_id,
            'nomor_pembayaran'  => $p->nomor_pembayaran,
            'bulan_ke'          => $p->bulan_ke,
            'periode_bulan'     => $p->periode_bulan,
            'tanggal_bayar'     => $p->tanggal_bayar?->format('Y-m-d'),
            'nominal'           => $maskNominal ? 0 : $p->nominal,
            'nominal_format'    => $maskNominal ? '—' : ('Rp ' . number_format($p->nominal, 0, ',', '.')),
            'metode_pembayaran' => $p->metode_pembayaran,
            'catatan'           => $p->catatan,
            'bukti_bayar'       => $p->bukti_bayar,
            'masuk_pembukuan'   => (bool) $p->masuk_pembukuan,
            'created_by'        => $p->createdBy?->name ?? 'System',
            'created_at'        => $p->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/proyeks/{proyek}/pembayarans
     */
    public function index(Request $request, Proyek $proyek): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');

        $user = $request->user();
        $isAdmin = $user && $user->role && in_array($user->role->slug, ['super-admin', 'admin']);
        $maskNominal = !$isAdmin && isset($proyek->tampilkan_nilai) && !$proyek->tampilkan_nilai;

        $pembayarans = $proyek->pembayarans()
            ->with(['invoice', 'transaction', 'createdBy'])
            ->get();

        return response()->json([
            'data' => $pembayarans->map(fn($p) => $this->formatPembayaran($p, $maskNominal))->values(),
            'total_dibayar' => $maskNominal ? 0 : $pembayarans->sum('nominal'),
        ]);
    }

    /**
     * GET /api/proyeks/{proyek}/sewa-jadwal
     * Returns monthly rental billing schedule & payment history for Proyek Sewa
     */
    public function sewaJadwal(Request $request, Proyek $proyek): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');

        $user = $request->user();
        $isAdmin = $user && $user->role && in_array($user->role->slug, ['super-admin', 'admin']);
        $maskNominal = !$isAdmin && isset($proyek->tampilkan_nilai) && !$proyek->tampilkan_nilai;

        $durasi = max(1, $proyek->sewa_durasi_bulan ?? 12);
        $startDate = $proyek->sewa_mulai
            ? Carbon::parse($proyek->sewa_mulai)
            : ($proyek->tanggal ? Carbon::parse($proyek->tanggal) : now());

        $nominalPerBulan = $proyek->nominal_per_bulan ?: (int) round(($proyek->nilai ?? 0) / $durasi);

        $allPayments = $proyek->pembayarans()
            ->with(['invoice', 'transaction', 'createdBy'])
            ->get();
        $paymentsByBulan = $allPayments->whereNotNull('bulan_ke')->keyBy('bulan_ke');
        $invoicesByTermin = $proyek->invoices()->whereNotNull('nomor_termin')->get()->keyBy('nomor_termin');

        $items = [];
        for ($m = 1; $m <= $durasi; $m++) {
            $bDate = $startDate->copy()->addMonths($m - 1);
            $bNama = $bDate->translatedFormat('F Y');
            $jtDate = $bDate->copy()->addDays(14);

            /** @var ProyekPembayaran|null $pmt */
            $pmt = $paymentsByBulan->get($m);
            /** @var ProyekInvoice|null $inv */
            $inv = $invoicesByTermin->get($m);

            $isPaid = $pmt !== null;
            $isOverdue = !$isPaid && now()->greaterThan($jtDate);

            $status = $isPaid
                ? 'Lunas'
                : ($isOverdue ? 'Jatuh Tempo' : ($inv?->status === 'Terkirim' ? 'Menunggu Bayar' : 'Belum Dibayar'));

            $items[] = [
                'bulan_ke'               => $m,
                'periode_bulan'          => $bNama,
                'tanggal_periode'        => $bDate->format('Y-m-d'),
                'tanggal_jatuh_tempo'    => $jtDate->format('Y-m-d'),
                'nominal_tagihan'        => $maskNominal ? 0 : $nominalPerBulan,
                'nominal_tagihan_format' => $maskNominal ? '—' : ('Rp ' . number_format($nominalPerBulan, 0, ',', '.')),
                'status'                 => $status,
                'is_paid'                => $isPaid,
                'is_overdue'             => $isOverdue,
                'pembayaran'             => $pmt ? $this->formatPembayaran($pmt, $maskNominal) : null,
                'invoice'                => $inv ? [
                    'id'            => $inv->id,
                    'nomor_invoice' => $inv->nomor_invoice,
                    'status'        => $inv->status,
                    'total_harga'   => $maskNominal ? 0 : $inv->total_harga,
                ] : null,
            ];
        }

        $totalKontrak = $proyek->nilai ?? ($nominalPerBulan * $durasi);
        $totalPaid = $allPayments->sum('nominal');
        $bulanTerbayar = $paymentsByBulan->count();

        $summary = [
            'total_nilai_kontrak'        => $maskNominal ? 0 : $totalKontrak,
            'total_nilai_kontrak_format' => $maskNominal ? '— (Dirahasiakan)' : ('Rp ' . number_format($totalKontrak, 0, ',', '.')),
            'total_sudah_dibayar'        => $maskNominal ? 0 : $totalPaid,
            'total_sudah_dibayar_format' => $maskNominal ? '—' : ('Rp ' . number_format($totalPaid, 0, ',', '.')),
            'sisa_tagihan'               => $maskNominal ? 0 : max(0, $totalKontrak - $totalPaid),
            'sisa_tagihan_format'        => $maskNominal ? '—' : ('Rp ' . number_format(max(0, $totalKontrak - $totalPaid), 0, ',', '.')),
            'total_bulan'                => $durasi,
            'bulan_terbayar'             => $bulanTerbayar,
            'bulan_sisa'                 => max(0, $durasi - $bulanTerbayar),
            'persen_terbayar'            => round(($bulanTerbayar / $durasi) * 100),
        ];

        return response()->json([
            'data' => [
                'jadwal'  => $items,
                'summary' => $summary,
                'proyek'  => [
                    'id'                => $proyek->id,
                    'kode'              => $proyek->kode,
                    'nama'              => $proyek->nama,
                    'sewa_mulai'        => $proyek->sewa_mulai?->format('Y-m-d'),
                    'sewa_selesai'      => $proyek->sewa_selesai?->format('Y-m-d'),
                    'sewa_durasi_bulan' => $durasi,
                    'skema_pembayaran'  => $proyek->skema_pembayaran,
                    'status_sewa'       => $proyek->status_sewa,
                ],
            ],
        ]);
    }

    /**
     * POST /api/proyeks/{proyek}/pembayarans
     * Record a new payment and automatically post to Pembukuan (transactions table)
     */
    public function store(Request $request, Proyek $proyek): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');

        $validated = $request->validate([
            'bulan_ke'          => 'nullable|integer|min:1',
            'periode_bulan'     => 'nullable|string|max:100',
            'tanggal_bayar'     => 'required|date',
            'nominal'           => 'required|integer|min:1',
            'metode_pembayaran' => 'required|string|max:100',
            'catatan'           => 'nullable|string',
            'proyek_invoice_id' => 'nullable|exists:proyek_invoices,id',
            'masuk_pembukuan'   => 'nullable|boolean',
            'bukti_bayar'       => 'nullable|string',
            'bukti_file'        => 'nullable|file|max:10240',
        ]);

        $buktiBayar = $validated['bukti_bayar'] ?? null;
        if ($request->hasFile('bukti_file')) {
            $path = $request->file('bukti_file')->store('pembayaran', 'public');
            $buktiBayar = '/storage/' . $path;
        }

        $nomorPembayaran = DocumentNumberingService::generateNumber('pembayaran');
        $masukPembukuan = $request->boolean('masuk_pembukuan', true);

        // 1. Create Transaction in Pembukuan if enabled
        $txId = null;
        if ($masukPembukuan) {
            $bulanKe = $validated['bulan_ke'] ?? null;
            $periodeBulan = $validated['periode_bulan'] ?? null;
            $invoice = !empty($validated['proyek_invoice_id'])
                ? ProyekInvoice::find($validated['proyek_invoice_id'])
                : null;

            if ($bulanKe) {
                $desc = "Pembayaran Sewa Bulan ke-{$bulanKe}" . ($periodeBulan ? " ({$periodeBulan})" : '') . " - {$proyek->nama}";
            } elseif ($invoice) {
                $desc = "Pembayaran Invoice {$invoice->nomor_invoice} - {$invoice->perihal}";
            } else {
                $desc = "Pembayaran Proyek {$proyek->kode} - {$proyek->nama}";
            }

            if (!empty($validated['catatan'])) {
                $desc .= " (" . $validated['catatan'] . ")";
            }

            $tx = Transaction::create([
                'type'       => 'masuk',
                'name'       => $desc,
                'date'       => $validated['tanggal_bayar'],
                'category'   => 'Pembayaran Client',
                'proyek_id'  => $proyek->kode,
                'amount'     => $validated['nominal'],
                'created_by' => $request->user()?->id,
            ]);

            $txId = $tx->id;
        }

        // 2. Create ProyekPembayaran record
        $pembayaran = $proyek->pembayarans()->create([
            'proyek_invoice_id' => $validated['proyek_invoice_id'] ?? null,
            'transaction_id'    => $txId,
            'nomor_pembayaran'  => $nomorPembayaran,
            'bulan_ke'          => $validated['bulan_ke'] ?? null,
            'periode_bulan'     => $validated['periode_bulan'] ?? null,
            'tanggal_bayar'     => $validated['tanggal_bayar'],
            'nominal'           => $validated['nominal'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'catatan'           => $validated['catatan'] ?? null,
            'bukti_bayar'       => $buktiBayar,
            'masuk_pembukuan'   => $masukPembukuan,
            'created_by'        => $request->user()?->id,
        ]);

        // 3. Sync matching invoice status to Lunas if applicable
        if (!empty($validated['proyek_invoice_id'])) {
            $inv = ProyekInvoice::find($validated['proyek_invoice_id']);
            $inv?->update(['status' => 'Lunas']);
        } elseif (!empty($validated['bulan_ke'])) {
            $inv = $proyek->invoices()->where('nomor_termin', $validated['bulan_ke'])->first();
            if ($inv) {
                $inv->update(['status' => 'Lunas']);
                $pembayaran->updateQuietly(['proyek_invoice_id' => $inv->id]);
            }
        }

        $proyek->syncStatusPembayaran();

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat' . ($txId ? ' dan otomatis masuk ke Pembukuan' : ''),
            'data'    => $this->formatPembayaran($pembayaran->fresh(['invoice', 'transaction', 'createdBy'])),
        ], 201);
    }

    /**
     * PUT /api/proyeks/{proyek}/pembayarans/{pembayaran}
     */
    public function update(Request $request, Proyek $proyek, ProyekPembayaran $pembayaran): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');
        abort_if($pembayaran->proyek_id !== $proyek->id, 404);

        $validated = $request->validate([
            'tanggal_bayar'     => 'sometimes|date',
            'nominal'           => 'sometimes|integer|min:1',
            'metode_pembayaran' => 'sometimes|string|max:100',
            'catatan'           => 'nullable|string',
            'bukti_bayar'       => 'nullable|string',
        ]);

        $pembayaran->update($validated);

        // Update synced Transaction in Pembukuan if exists
        if ($pembayaran->transaction) {
            $txData = [];
            if (isset($validated['nominal'])) {
                $txData['amount'] = $validated['nominal'];
            }
            if (isset($validated['tanggal_bayar'])) {
                $txData['date'] = $validated['tanggal_bayar'];
            }
            if (!empty($txData)) {
                $pembayaran->transaction->update($txData);
            }
        }

        return response()->json([
            'message' => 'Data pembayaran berhasil diperbarui',
            'data'    => $this->formatPembayaran($pembayaran->fresh(['invoice', 'transaction', 'createdBy'])),
        ]);
    }

    /**
     * DELETE /api/proyeks/{proyek}/pembayarans/{pembayaran}
     */
    public function destroy(Request $request, Proyek $proyek, ProyekPembayaran $pembayaran): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');
        abort_if($pembayaran->proyek_id !== $proyek->id, 404);

        // 1. Delete associated Pembukuan Transaction if exists
        if ($pembayaran->transaction) {
            $pembayaran->transaction->delete();
        }

        // 2. If tied to an invoice, reset invoice to Terkirim if no other payments exist
        if ($pembayaran->proyek_invoice_id) {
            $invoice = $pembayaran->invoice;
            if ($invoice && $invoice->pembayarans()->where('id', '!=', $pembayaran->id)->count() === 0) {
                $invoice->update(['status' => 'Terkirim']);
            }
        }

        $pembayaran->delete();
        $proyek->syncStatusPembayaran();

        return response()->json([
            'message' => 'Pembayaran dan catatan pembukuan terkait berhasil dihapus',
        ]);
    }
}
