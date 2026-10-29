<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyek;
use App\Models\Transaction;
use App\Services\DocumentNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProyekController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatProyek(Proyek $p, bool $withRelations = false): array
    {
        $totalDibayar = isset($p->total_dibayar)
            ? (int) $p->total_dibayar
            : ($p->relationLoaded('pembayarans') ? (int) $p->pembayarans->sum('nominal') : (int) $p->pembayarans()->sum('nominal'));
        $nilai = (int) ($p->nilai ?? 0);
        $sisaPembayaran = max(0, $nilai - $totalDibayar);

        $statusPembayaran = $p->status_pembayaran ?? 'Belum Dibayar';
        if ($totalDibayar >= $nilai && $nilai > 0) {
            $statusPembayaran = 'Lunas';
        } elseif ($totalDibayar > 0 && $statusPembayaran !== 'Lunas') {
            $statusPembayaran = 'Sebagian';
        }

        $data = [
            'id'            => $p->id,
            'kode'          => $p->kode,
            'nama'          => $p->nama,
            'client'        => $p->client_nama,       // alias for frontend compat
            'client_nama'   => $p->client_nama,
            'perusahaan'    => $p->perusahaan,
            'phone'         => $p->phone,
            'email'         => $p->email,
            'kategori'      => $p->kategori,
            'divisi'        => $p->divisi,
            'tipe_kontrak'  => $p->tipe_kontrak ?? 'Reguler',
            'status_pembayaran' => $statusPembayaran,
            'total_dibayar' => $totalDibayar,
            'total_dibayar_format' => 'Rp' . number_format($totalDibayar, 0, ',', '.'),
            'sisa_pembayaran' => $sisaPembayaran,
            'sisa_pembayaran_format' => 'Rp' . number_format($sisaPembayaran, 0, ',', '.'),
            'sewa_mulai'    => $p->sewa_mulai?->format('Y-m-d'),
            'sewa_selesai'  => $p->sewa_selesai?->format('Y-m-d'),
            'sewa_durasi_bulan' => $p->sewa_durasi_bulan,
            'skema_pembayaran'  => $p->skema_pembayaran,
            'nominal_per_bulan' => $p->nominal_per_bulan,
            'nominal_per_bulan_format' => $p->nominal_per_bulan ? 'Rp' . number_format($p->nominal_per_bulan, 0, ',', '.') : null,
            'status_sewa'   => $p->status_sewa ?? 'Aktif',
            'sisa_hari_sewa'=> ($p->tipe_kontrak === 'Sewa' && $p->sewa_selesai)
                ? (int) ceil(now()->diffInDays($p->sewa_selesai, false))
                : null,
            'pic_id'        => $p->pic_id,
            'pic'           => $p->picUser?->name,
            'akses_tipe'    => $p->akses_tipe ?? 'personal',
            'allowed_roles' => $p->allowed_roles ?? [],
            'tanggal'       => $p->tanggal?->format('Y-m-d'),
            'targetSelesai' => $p->target_selesai?->format('Y-m-d'),  // camelCase for frontend
            'target_selesai'=> $p->target_selesai?->format('Y-m-d'),
            'deskripsi'     => $p->deskripsi,
            'nilai'         => $p->nilai,
            'nilai_format'  => 'Rp' . number_format($p->nilai, 0, ',', '.'),
            'progress'      => $p->progress,
            'status'        => $p->status,
            'penawaran_id'  => $p->penawaran_id,
            'created_by'    => $p->created_by,
            'created_at'    => $p->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $p->updated_at?->format('Y-m-d H:i:s'),
        ];

        if ($withRelations) {
            $data['tim'] = $p->tim->map(fn($t) => [
                'id'           => $t->id,
                'user_id'      => $t->user_id,
                'nama'         => $t->nama,
                'nama_anggota' => $t->nama,
                'role'         => $t->role,
            ])->values();

            $data['perpanjangan_sewas'] = $p->perpanjanganSewas->map(fn($r) => [
                'id'                   => $r->id,
                'tanggal_perpanjangan' => $r->tanggal_perpanjangan?->format('Y-m-d'),
                'sewa_mulai_lama'      => $r->sewa_mulai_lama?->format('Y-m-d'),
                'sewa_selesai_lama'    => $r->sewa_selesai_lama?->format('Y-m-d'),
                'sewa_mulai_baru'      => $r->sewa_mulai_baru?->format('Y-m-d'),
                'sewa_selesai_baru'    => $r->sewa_selesai_baru?->format('Y-m-d'),
                'durasi_bulan'         => $r->durasi_bulan,
                'nominal'              => $r->nominal,
                'nominal_format'       => 'Rp' . number_format($r->nominal, 0, ',', '.'),
                'skema_pembayaran'     => $r->skema_pembayaran,
                'catatan'              => $r->catatan,
                'created_by'           => $r->createdBy?->name,
                'created_at'           => $r->created_at?->format('Y-m-d H:i:s'),
            ])->values();

            $data['pembayarans'] = $p->pembayarans->map(fn($pmt) => [
                'id'                => $pmt->id,
                'proyek_id'         => $pmt->proyek_id,
                'proyek_invoice_id' => $pmt->proyek_invoice_id,
                'transaction_id'    => $pmt->transaction_id,
                'nomor_pembayaran'  => $pmt->nomor_pembayaran,
                'tanggal_bayar'     => $pmt->tanggal_bayar?->format('Y-m-d'),
                'nominal'           => $pmt->nominal,
                'nominal_format'    => 'Rp' . number_format($pmt->nominal, 0, ',', '.'),
                'metode_pembayaran' => $pmt->metode_pembayaran,
                'catatan'           => $pmt->catatan,
                'bukti_bayar'       => $pmt->bukti_bayar,
                'masuk_pembukuan'   => (bool) $pmt->masuk_pembukuan,
                'created_by'        => $pmt->createdBy?->name ?? 'System',
                'created_at'        => $pmt->created_at?->format('Y-m-d H:i:s'),
            ])->values();
        }

        return $data;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks?search=&status=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proyek::query()
            ->with(['picUser'])
            ->withSum('pembayarans as total_dibayar', 'nominal')
            ->accessibleBy($request->user())
            ->search($request->input('search'))
            ->byStatus($request->input('status'))
            ->byTipeKontrak($request->input('tipe_kontrak'));

        $proyeks = $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(12);

        return response()->json([
            'data' => $proyeks->map(fn($p) => $this->formatProyek($p))->values(),
            'meta' => [
                'current_page' => $proyeks->currentPage(),
                'last_page'    => $proyeks->lastPage(),
                'per_page'     => $proyeks->perPage(),
                'total'        => $proyeks->total(),
            ],
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $base = Proyek::query()->accessibleBy($request->user());

        $counts = (clone $base)->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byStatus = array_map(fn($s) => [
            'status' => $s,
            'count'  => $counts[$s] ?? 0,
        ], Proyek::VALID_STATUSES);

        $nilaiTotal = (clone $base)->whereIn('status', ['Berjalan', 'QC', 'Serah Terima'])->sum('nilai');

        return response()->json([
            'by_status'   => $byStatus,
            'total'       => (clone $base)->count(),
            'nilai_total' => $nilaiTotal,
            'total_sewa'  => (clone $base)->where('tipe_kontrak', 'Sewa')->count(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/proyeks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama'              => 'required|string|max:255',
            'client_nama'       => 'nullable|string|max:255',
            'perusahaan'        => 'nullable|string|max:255',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'kategori'          => 'nullable|string|max:100',
            'divisi'            => 'nullable|string|max:100',
            'tipe_kontrak'      => 'nullable|in:' . implode(',', Proyek::VALID_TIPE_KONTRAK),
            'sewa_mulai'        => 'nullable|date',
            'sewa_selesai'      => 'nullable|date',
            'sewa_durasi_bulan' => 'nullable|integer|min:1',
            'skema_pembayaran'  => 'nullable|in:' . implode(',', Proyek::VALID_SKEMA_PEMBAYARAN),
            'nominal_per_bulan' => 'nullable|integer|min:0',
            'status_sewa'       => 'nullable|in:' . implode(',', Proyek::VALID_STATUS_SEWA),
            'pic_id'            => 'nullable|exists:users,id',
            'akses_tipe'        => 'nullable|in:' . implode(',', Proyek::VALID_AKSES_TIPE),
            'allowed_roles'     => 'nullable|array',
            'tanggal'           => 'nullable|date',
            'target_selesai'    => 'nullable|date',
            'deskripsi'         => 'nullable|string',
            'nilai'             => 'nullable|integer|min:0',
            'progress'          => 'nullable|integer|min:0|max:100',
            'status'            => 'nullable|in:' . implode(',', Proyek::VALID_STATUSES),
            'penawaran_id'      => 'nullable|exists:penawarans,id',
            'lead_id'           => 'nullable|exists:leads,id',
            'tim'               => 'nullable|array',
            'tim.*.user_id'     => 'nullable|exists:users,id',
            'tim.*.nama'        => 'required_with:tim|string|max:255',
            'tim.*.role'        => 'nullable|string|max:100',
        ]);

        $tipeKontrak = $validated['tipe_kontrak'] ?? null;
        $nilai = $validated['nilai'] ?? 0;

        // Auto inherit sewa from Lead if lead_id is provided
        if (!empty($validated['lead_id'])) {
            $lead = \App\Models\Lead::find($validated['lead_id']);
            if ($lead) {
                if (empty($tipeKontrak) && $lead->tipe_kontrak === 'Sewa') {
                    $tipeKontrak = 'Sewa';
                }
                if ($tipeKontrak === 'Sewa' || $lead->tipe_kontrak === 'Sewa') {
                    $tipeKontrak = 'Sewa';
                    $validated['sewa_durasi_bulan'] = $validated['sewa_durasi_bulan'] ?? $lead->sewa_durasi_bulan ?? 12;
                    $validated['skema_pembayaran']  = $validated['skema_pembayaran'] ?? $lead->skema_pembayaran ?? 'Bulanan';
                    $durasiBln = $validated['sewa_durasi_bulan'];
                    $validated['sewa_mulai']        = $validated['sewa_mulai'] ?? ($lead->sewa_mulai?->format('Y-m-d') ?? now()->toDateString());
                    $validated['sewa_selesai']      = $validated['sewa_selesai'] ?? ($lead->sewa_selesai?->format('Y-m-d') ?? now()->addMonths($durasiBln)->toDateString());
                    if (empty($validated['nominal_per_bulan'])) {
                        $validated['nominal_per_bulan'] = $lead->nominal_per_bulan;
                    }
                }
            }
        }

        $tipeKontrak = $tipeKontrak ?? 'Reguler';

        // Auto calculate nominal per bulan if sewa
        if ($tipeKontrak === 'Sewa') {
            $durasi = $validated['sewa_durasi_bulan'] ?? 1;
            if (empty($validated['nominal_per_bulan']) && $durasi > 0 && $nilai > 0) {
                $validated['nominal_per_bulan'] = (int) round($nilai / $durasi);
            }
            if (empty($validated['skema_pembayaran'])) {
                $validated['skema_pembayaran'] = 'Bulanan';
            }
            if (empty($validated['status_sewa'])) {
                $validated['status_sewa'] = 'Aktif';
            }
        }

        // Auto-generate kode & force default status
        $proyek = new Proyek($validated);
        $proyek->kode         = $proyek->generateKode();
        $proyek->status       = 'Belum Dimulai';
        $proyek->tipe_kontrak = $tipeKontrak;
        $proyek->nilai        = $nilai;  // ensure NOT NULL on PostgreSQL
        $proyek->progress     = $validated['progress'] ?? 0;
        $proyek->akses_tipe   = $validated['akses_tipe'] ?? 'personal';
        $proyek->allowed_roles = $validated['allowed_roles'] ?? null;
        $proyek->created_by   = $request->user()?->id;
        $proyek->save();

        // Seed initial team members if provided
        if (!empty($request->input('tim')) && is_array($request->input('tim'))) {
            foreach ($request->input('tim') as $member) {
                if (!empty($member['nama'])) {
                    $proyek->tim()->create([
                        'user_id' => $member['user_id'] ?? null,
                        'nama'    => $member['nama'],
                        'role'    => $member['role'] ?? 'Teknisi',
                    ]);
                }
            }
        }

        $proyek->load(['picUser', 'tim']);

        return response()->json([
            'message' => 'Proyek berhasil ditambahkan',
            'data'    => $this->formatProyek($proyek),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/proyeks/{proyek}
     */
    public function show(Request $request, Proyek $proyek): JsonResponse
    {
        if (!$proyek->isAccessibleBy($request->user())) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses ke proyek ini.'], 403);
        }

        $proyek->load(['picUser', 'tim.user', 'penawaran', 'perpanjanganSewas.createdBy', 'pembayarans.transaction', 'pembayarans.createdBy', 'pembayarans.invoice']);

        return response()->json([
            'data' => $this->formatProyek($proyek, true),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * PUT /api/proyeks/{proyek}
     */
    public function update(Request $request, Proyek $proyek): JsonResponse
    {
        if (!$proyek->isAccessibleBy($request->user())) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk mengubah proyek ini.'], 403);
        }

        $validated = $request->validate([
            'nama'              => 'sometimes|string|max:255',
            'client_nama'       => 'nullable|string|max:255',
            'perusahaan'        => 'nullable|string|max:255',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'kategori'          => 'nullable|string|max:100',
            'divisi'            => 'nullable|string|max:100',
            'tipe_kontrak'      => 'nullable|in:' . implode(',', Proyek::VALID_TIPE_KONTRAK),
            'sewa_mulai'        => 'nullable|date',
            'sewa_selesai'      => 'nullable|date',
            'sewa_durasi_bulan' => 'nullable|integer|min:1',
            'skema_pembayaran'  => 'nullable|in:' . implode(',', Proyek::VALID_SKEMA_PEMBAYARAN),
            'nominal_per_bulan' => 'nullable|integer|min:0',
            'status_sewa'       => 'nullable|in:' . implode(',', Proyek::VALID_STATUS_SEWA),
            'pic_id'            => 'nullable|exists:users,id',
            'akses_tipe'        => 'nullable|in:' . implode(',', Proyek::VALID_AKSES_TIPE),
            'allowed_roles'     => 'nullable|array',
            'tanggal'           => 'nullable|date',
            'target_selesai'    => 'nullable|date',
            'deskripsi'         => 'nullable|string',
            'nilai'             => 'nullable|integer|min:0',
            'progress'          => 'nullable|integer|min:0|max:100',
            'status'            => 'sometimes|in:' . implode(',', Proyek::VALID_STATUSES),
            'penawaran_id'      => 'nullable|exists:penawarans,id',
            'lead_id'           => 'nullable|exists:leads,id',
        ]);

        $tipeKontrak = $validated['tipe_kontrak'] ?? $proyek->tipe_kontrak;
        if ($tipeKontrak === 'Sewa') {
            $durasi = $validated['sewa_durasi_bulan'] ?? $proyek->sewa_durasi_bulan ?? 1;
            $nilai  = array_key_exists('nilai', $validated) ? $validated['nilai'] : $proyek->nilai;
            if (empty($validated['nominal_per_bulan']) && $durasi > 0 && $nilai > 0) {
                $validated['nominal_per_bulan'] = (int) round($nilai / $durasi);
            }
        }

        $proyek->update($validated);

        return response()->json([
            'message' => 'Proyek berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh(['picUser', 'perpanjanganSewas.createdBy'])),
        ]);
    }

    // ── Perpanjang Sewa ───────────────────────────────────────────────────────

    /**
     * POST /api/proyeks/{proyek}/perpanjang-sewa
     */
    public function perpanjangSewa(Request $request, Proyek $proyek): JsonResponse
    {
        $validated = $request->validate([
            'durasi_bulan'       => 'required|integer|min:1',
            'sewa_mulai_baru'    => 'nullable|date',
            'sewa_selesai_baru'  => 'required|date',
            'nominal'            => 'required|integer|min:0',
            'skema_pembayaran'   => 'nullable|in:' . implode(',', Proyek::VALID_SKEMA_PEMBAYARAN),
            'catatan'            => 'nullable|string',
            'generate_invoices'  => 'nullable|boolean',
        ]);

        $sewaMulaiLama   = $proyek->sewa_mulai;
        $sewaSelesaiLama = $proyek->sewa_selesai;

        $sewaMulaiBaru = !empty($validated['sewa_mulai_baru'])
            ? $validated['sewa_mulai_baru']
            : ($sewaSelesaiLama ? \Carbon\Carbon::parse($sewaSelesaiLama)->addDay()->format('Y-m-d') : now()->format('Y-m-d'));

        $proyek->perpanjanganSewas()->create([
            'tanggal_perpanjangan' => now()->format('Y-m-d'),
            'sewa_mulai_lama'      => $sewaMulaiLama,
            'sewa_selesai_lama'    => $sewaSelesaiLama,
            'sewa_mulai_baru'      => $sewaMulaiBaru,
            'sewa_selesai_baru'    => $validated['sewa_selesai_baru'],
            'durasi_bulan'         => $validated['durasi_bulan'],
            'nominal'              => $validated['nominal'],
            'skema_pembayaran'     => $validated['skema_pembayaran'] ?? $proyek->skema_pembayaran ?? 'Bulanan',
            'catatan'              => $validated['catatan'] ?? null,
            'created_by'           => $request->user()?->id,
        ]);

        // Update Proyek
        $proyek->tipe_kontrak      = 'Sewa';
        $proyek->sewa_selesai      = $validated['sewa_selesai_baru'];
        $proyek->sewa_durasi_bulan = ($proyek->sewa_durasi_bulan ?? 0) + $validated['durasi_bulan'];
        $proyek->nilai             = ($proyek->nilai ?? 0) + $validated['nominal'];
        if (!empty($validated['skema_pembayaran'])) {
            $proyek->skema_pembayaran = $validated['skema_pembayaran'];
        }
        $proyek->nominal_per_bulan = $validated['durasi_bulan'] > 0
            ? (int) round($validated['nominal'] / $validated['durasi_bulan'])
            : $proyek->nominal_per_bulan;
        $proyek->status_sewa       = 'Aktif';
        $proyek->save();

        if (!empty($validated['generate_invoices'])) {
            $skema = $validated['skema_pembayaran'] ?? $proyek->skema_pembayaran ?? 'Bulanan';
            if ($skema === 'Bulanan') {
                $durasi = $validated['durasi_bulan'];
                $startDate = \Carbon\Carbon::parse($sewaMulaiBaru);
                $nomPerBln = (int) round($validated['nominal'] / max(1, $durasi));
                for ($m = 1; $m <= $durasi; $m++) {
                    $bDate = $startDate->copy()->addMonths($m - 1);
                    $bNama = $bDate->translatedFormat('F Y');
                    $inv = $proyek->invoices()->create([
                        'nomor_invoice' => \App\Services\DocumentNumberingService::generateNumber('invoice'),
                        'tanggal'       => $bDate->format('Y-m-d'),
                        'jatuh_tempo'   => $bDate->copy()->addDays(14)->format('Y-m-d'),
                        'perihal'       => "Tagihan Perpanjangan Sewa Bulan ke-{$m} ({$bNama}) - {$proyek->nama}",
                        'catatan'       => "Tagihan perpanjangan sewa periode {$bNama}.",
                        'ppn'           => 11,
                        'tipe'          => 'Sewa Bulanan',
                        'nomor_termin'  => $m,
                        'status'        => 'Draft',
                        'created_by'    => $request->user()?->id,
                    ]);
                    $inv->items()->create([
                        'nama'         => "Perpanjangan Sewa Bulan ke-{$m} ({$bNama})",
                        'satuan'       => 'bulan',
                        'qty'          => 1,
                        'harga_satuan' => $nomPerBln,
                        'diskon'       => 0,
                    ]);
                    $inv->load('items');
                    $inv->recalculate();
                }
            } else {
                $inv = $proyek->invoices()->create([
                    'nomor_invoice' => \App\Services\DocumentNumberingService::generateNumber('invoice'),
                    'tanggal'       => $sewaMulaiBaru,
                    'jatuh_tempo'   => \Carbon\Carbon::parse($sewaMulaiBaru)->addDays(14)->format('Y-m-d'),
                    'perihal'       => "Tagihan Perpanjangan Sewa Penuh - {$proyek->nama}",
                    'catatan'       => "Pembayaran perpanjangan sewa sekaligus {$validated['durasi_bulan']} bulan.",
                    'ppn'           => 11,
                    'tipe'          => 'Sewa',
                    'nomor_termin'  => 1,
                    'status'        => 'Draft',
                    'created_by'    => $request->user()?->id,
                ]);
                $inv->items()->create([
                    'nama'         => "Perpanjangan Sewa {$validated['durasi_bulan']} Bulan",
                    'satuan'       => 'paket',
                    'qty'          => 1,
                    'harga_satuan' => $validated['nominal'],
                    'diskon'       => 0,
                ]);
                $inv->load('items');
                $inv->recalculate();
            }
        }

        $proyek->load(['picUser', 'tim.user', 'penawaran', 'perpanjanganSewas.createdBy']);

        return response()->json([
            'message' => 'Sewa proyek berhasil diperpanjang',
            'data'    => $this->formatProyek($proyek, true),
        ]);
    }

    // ── Generate Sewa Invoices ────────────────────────────────────────────────

    /**
     * POST /api/proyeks/{proyek}/generate-sewa-invoices
     */
    public function generateSewaInvoices(Request $request, Proyek $proyek): JsonResponse
    {
        abort_if($proyek->tipe_kontrak !== 'Sewa', 400, 'Proyek ini bukan tipe sewa');

        $validated = $request->validate([
            'skema'            => 'required|in:Bulanan,Sekaligus',
            'ppn'              => 'nullable|integer|min:0|max:100',
            'jatuh_tempo_hari' => 'nullable|integer|min:1|max:90',
        ]);

        $ppn    = $validated['ppn'] ?? 11;
        $jtHari = $validated['jatuh_tempo_hari'] ?? 14;
        $createdCount = 0;

        if ($validated['skema'] === 'Bulanan') {
            $durasi = max(1, $proyek->sewa_durasi_bulan ?? 12);
            $startDate = $proyek->sewa_mulai ? \Carbon\Carbon::parse($proyek->sewa_mulai) : now();
            $nominalPerBulan = $proyek->nominal_per_bulan ?: (int) round(($proyek->nilai ?? 0) / $durasi);

            for ($m = 1; $m <= $durasi; $m++) {
                $bulanDate  = $startDate->copy()->addMonths($m - 1);
                $bulanNama  = $bulanDate->translatedFormat('F Y');
                $tanggalInv = $bulanDate->format('Y-m-d');
                $jatuhTempo = $bulanDate->copy()->addDays($jtHari)->format('Y-m-d');

                $inv = $proyek->invoices()->create([
                    'nomor_invoice' => \App\Services\DocumentNumberingService::generateNumber('invoice'),
                    'tanggal'       => $tanggalInv,
                    'jatuh_tempo'   => $jatuhTempo,
                    'perihal'       => "Tagihan Sewa Bulan ke-{$m} ({$bulanNama}) - {$proyek->nama}",
                    'catatan'       => "Tagihan sewa periode bulan ke-{$m} ({$bulanNama}) dari total {$durasi} bulan kontrak sewa.",
                    'ppn'           => $ppn,
                    'tipe'          => 'Sewa Bulanan',
                    'nomor_termin'  => $m,
                    'status'        => 'Draft',
                    'created_by'    => $request->user()?->id,
                ]);

                $inv->items()->create([
                    'nama'         => "Biaya Sewa Bulan ke-{$m} ({$bulanNama})",
                    'satuan'       => 'bulan',
                    'qty'          => 1,
                    'harga_satuan' => $nominalPerBulan,
                    'diskon'       => 0,
                ]);

                $inv->load('items');
                $inv->recalculate();
                $createdCount++;
            }
        } else {
            // Sekaligus
            $tanggalInv = $proyek->sewa_mulai ? \Carbon\Carbon::parse($proyek->sewa_mulai)->format('Y-m-d') : now()->format('Y-m-d');
            $jatuhTempo = \Carbon\Carbon::parse($tanggalInv)->addDays($jtHari)->format('Y-m-d');
            $periodeStr = ($proyek->sewa_mulai && $proyek->sewa_selesai)
                ? \Carbon\Carbon::parse($proyek->sewa_mulai)->translatedFormat('d M Y') . ' s/d ' . \Carbon\Carbon::parse($proyek->sewa_selesai)->translatedFormat('d M Y')
                : ($proyek->sewa_durasi_bulan ? "{$proyek->sewa_durasi_bulan} Bulan" : '1 Periode');

            $inv = $proyek->invoices()->create([
                'nomor_invoice' => \App\Services\DocumentNumberingService::generateNumber('invoice'),
                'tanggal'       => $tanggalInv,
                'jatuh_tempo'   => $jatuhTempo,
                'perihal'       => "Tagihan Sewa Penuh ({$periodeStr}) - {$proyek->nama}",
                'catatan'       => "Pembayaran sewa sekaligus di muka untuk periode {$periodeStr}.",
                'ppn'           => $ppn,
                'tipe'          => 'Sewa',
                'nomor_termin'  => 1,
                'status'        => 'Draft',
                'created_by'    => $request->user()?->id,
            ]);

            $inv->items()->create([
                'nama'         => "Biaya Sewa Periode {$periodeStr}",
                'satuan'       => 'paket',
                'qty'          => 1,
                'harga_satuan' => $proyek->nilai,
                'diskon'       => 0,
            ]);

            $inv->load('items');
            $inv->recalculate();
            $createdCount++;
        }

        return response()->json([
            'message' => "{$createdCount} invoice sewa berhasil dibuat",
            'count'   => $createdCount,
        ], 201);
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    /**
     * PATCH /api/proyeks/{proyek}/status
     */
    public function updateStatus(Request $request, Proyek $proyek): JsonResponse
    {
        if (!$proyek->isAccessibleBy($request->user())) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk mengubah status proyek ini.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Proyek::VALID_STATUSES),
        ]);

        $proyek->update($validated);

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh(['picUser'])),
        ]);
    }

    // ── Update Progress ───────────────────────────────────────────────────────

    /**
     * PATCH /api/proyeks/{proyek}/progress
     */
    public function updateProgress(Request $request, Proyek $proyek): JsonResponse
    {
        if (!$proyek->isAccessibleBy($request->user())) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk mengubah progress proyek ini.'], 403);
        }

        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
        ]);

        $proyek->update($validated);

        return response()->json([
            'message' => 'Progress berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh()),
        ]);
    }

    // ── Bayar Langsung (Proyek Langsung -> Pembukuan) ──────────────────────────

    /**
     * POST /api/proyeks/{proyek}/bayar-langsung
     * Tandai pembayaran proyek langsung dan otomatis sinkron ke Pembukuan (transactions)
     */
    public function bayarLangsung(Request $request, Proyek $proyek): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');

        $validated = $request->validate([
            'nominal'           => 'nullable|integer|min:0',
            'tanggal_bayar'     => 'nullable|date',
            'metode_pembayaran' => 'nullable|string|max:100',
            'catatan'           => 'nullable|string',
            'masuk_pembukuan'   => 'nullable|boolean',
            'status_pembayaran' => 'nullable|in:Lunas,Sebagian,Belum Dibayar',
        ]);

        $sudahDibayar = (int) $proyek->pembayarans()->sum('nominal');
        $nilai = (int) ($proyek->nilai ?? 0);
        $sisa = max(0, $nilai - $sudahDibayar);

        $nominal = array_key_exists('nominal', $validated) && $validated['nominal'] !== null
            ? (int) $validated['nominal']
            : ($sisa > 0 ? $sisa : $nilai);

        $tanggalBayar    = $validated['tanggal_bayar'] ?? now()->format('Y-m-d');
        $metode          = $validated['metode_pembayaran'] ?? 'Transfer Bank BCA';
        $catatan         = $validated['catatan'] ?? null;
        $masukPembukuan  = $request->boolean('masuk_pembukuan', true);
        $nomorPembayaran = DocumentNumberingService::generateNumber('pembayaran');

        // 1. Catat ke Pembukuan (transactions)
        $txId = null;
        if ($masukPembukuan && $nominal > 0) {
            $desc = "Pembayaran Proyek {$proyek->kode} - {$proyek->nama}";
            if ($catatan) {
                $desc .= " ({$catatan})";
            }

            $tx = Transaction::create([
                'type'       => 'masuk',
                'name'       => $desc,
                'date'       => $tanggalBayar,
                'category'   => 'Pembayaran Client',
                'proyek_id'  => $proyek->kode,
                'amount'     => $nominal,
                'created_by' => $request->user()?->id,
            ]);
            $txId = $tx->id;
        }

        // 2. Catat Riwayat Pembayaran Proyek
        $pembayaran = null;
        if ($nominal > 0) {
            $pembayaran = $proyek->pembayarans()->create([
                'transaction_id'    => $txId,
                'nomor_pembayaran'  => $nomorPembayaran,
                'tanggal_bayar'     => $tanggalBayar,
                'nominal'           => $nominal,
                'metode_pembayaran' => $metode,
                'catatan'           => $catatan,
                'masuk_pembukuan'   => $masukPembukuan,
                'created_by'        => $request->user()?->id,
            ]);
        }

        // 3. Update Status Pembayaran Proyek
        $newTotalPaid = (int) $proyek->pembayarans()->sum('nominal');
        $statusPembayaran = $validated['status_pembayaran'] ?? null;
        if (!$statusPembayaran) {
            if ($nilai > 0 && $newTotalPaid >= $nilai) {
                $statusPembayaran = 'Lunas';
            } elseif ($newTotalPaid > 0) {
                $statusPembayaran = 'Sebagian';
            } else {
                $statusPembayaran = 'Lunas';
            }
        }

        $proyek->update(['status_pembayaran' => $statusPembayaran]);

        // 4. Update status invoices jika lunas
        if ($statusPembayaran === 'Lunas') {
            $proyek->invoices()->where('status', '!=', 'Lunas')->update(['status' => 'Lunas']);
        }

        $proyek->load(['picUser', 'tim.user', 'penawaran', 'perpanjanganSewas.createdBy', 'pembayarans.transaction', 'pembayarans.createdBy', 'pembayarans.invoice']);

        return response()->json([
            'message'    => 'Pembayaran proyek berhasil dicatat' . ($txId ? ' dan otomatis masuk ke Pembukuan' : ''),
            'data'       => $this->formatProyek($proyek, true),
            'pembayaran' => $pembayaran,
        ]);
    }

    /**
     * PATCH /api/proyeks/{proyek}/status-pembayaran
     */
    public function updateStatusPembayaran(Request $request, Proyek $proyek): JsonResponse
    {
        abort_unless($proyek->isAccessibleBy($request->user()), 403, 'Akses ditolak');

        $validated = $request->validate([
            'status_pembayaran' => 'required|in:' . implode(',', Proyek::VALID_STATUS_PEMBAYARAN),
        ]);

        $proyek->update(['status_pembayaran' => $validated['status_pembayaran']]);

        return response()->json([
            'message' => 'Status pembayaran berhasil diperbarui',
            'data'    => $this->formatProyek($proyek->fresh(['picUser'])),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    /**
     * DELETE /api/proyeks/{proyek}
     */
    public function destroy(Request $request, Proyek $proyek): JsonResponse
    {
        if (!$proyek->isAccessibleBy($request->user())) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk menghapus proyek ini.'], 403);
        }

        $proyek->delete();
        return response()->json(['message' => 'Proyek berhasil dihapus']);
    }
}
