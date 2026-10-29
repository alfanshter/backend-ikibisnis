<?php

namespace Tests\Feature;

use App\Models\Proyek;
use App\Models\ProyekInvoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyekPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Proyek $proyekSewa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // 1-year sewa project
        $this->proyekSewa = Proyek::create([
            'kode'              => 'P#S-2026-777',
            'nama'              => 'Proyek Sewa Aplikasi SaaS 1 Tahun',
            'client_nama'       => 'PT Solusi Bangsa',
            'tipe_kontrak'      => 'Sewa',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'nominal_per_bulan' => 5000000,
            'nilai'             => 60000000,
            'status'            => 'Berjalan',
            'created_by'        => $this->user->id,
        ]);
    }

    public function test_can_fetch_sewa_monthly_schedule(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/proyeks/{$this->proyekSewa->id}/sewa-jadwal");

        $response->assertStatus(200);
        $this->assertCount(12, $response->json('data.jadwal'));
        $this->assertEquals(12, $response->json('data.summary.total_bulan'));
        $this->assertEquals(0, $response->json('data.summary.bulan_terbayar'));
        $this->assertEquals(60000000, $response->json('data.summary.total_nilai_kontrak'));
        $this->assertEquals(60000000, $response->json('data.summary.sisa_tagihan'));
        $this->assertEquals(5000000, $response->json('data.jadwal.0.nominal_tagihan'));
        $this->assertFalse($response->json('data.jadwal.0.is_paid'));
    }

    public function test_record_sewa_monthly_payment_automatically_creates_pembukuan_transaction(): void
    {
        $payload = [
            'bulan_ke'          => 1,
            'periode_bulan'     => 'Agustus 2026',
            'tanggal_bayar'     => '2026-08-05',
            'nominal'           => 5000000,
            'metode_pembayaran' => 'Transfer Bank BCA',
            'catatan'           => 'Pembayaran sewa bulan ke-1 via transfer BCA',
            'masuk_pembukuan'   => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$this->proyekSewa->id}/pembayarans", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('proyek_pembayarans', [
            'proyek_id'         => $this->proyekSewa->id,
            'bulan_ke'          => 1,
            'nominal'           => 5000000,
            'metode_pembayaran' => 'Transfer Bank BCA',
            'masuk_pembukuan'   => true,
        ]);

        // Verify transaction is created in Pembukuan
        $txId = $response->json('data.transaction_id');
        $this->assertNotNull($txId);

        $this->assertDatabaseHas('transactions', [
            'id'        => $txId,
            'type'      => 'masuk',
            'category'  => 'Pembayaran Client',
            'proyek_id' => 'P#S-2026-777',
            'amount'    => 5000000,
        ]);

        // Verify schedule now shows bulan 1 as paid
        $jadwalRes = $this->actingAs($this->user)
            ->getJson("/api/proyeks/{$this->proyekSewa->id}/sewa-jadwal");

        $jadwalRes->assertStatus(200);
        $this->assertEquals(1, $jadwalRes->json('data.summary.bulan_terbayar'));
        $this->assertEquals(5000000, $jadwalRes->json('data.summary.total_sudah_dibayar'));
        $this->assertEquals(55000000, $jadwalRes->json('data.summary.sisa_tagihan'));
        $this->assertTrue($jadwalRes->json('data.jadwal.0.is_paid'));
        $this->assertEquals('Lunas', $jadwalRes->json('data.jadwal.0.status'));
    }

    public function test_payment_syncs_with_generated_invoice(): void
    {
        // First generate sewa invoices
        $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$this->proyekSewa->id}/generate-sewa-invoices", [
                'skema' => 'Bulanan',
                'ppn'   => 0,
            ])
            ->assertStatus(201);

        $firstInvoice = $this->proyekSewa->invoices()->where('nomor_termin', 1)->first();
        $this->assertEquals('Draft', $firstInvoice->status);

        // Record payment for month 1
        $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$this->proyekSewa->id}/pembayarans", [
                'bulan_ke'          => 1,
                'periode_bulan'     => 'Agustus 2026',
                'tanggal_bayar'     => '2026-08-05',
                'nominal'           => 5000000,
                'metode_pembayaran' => 'Transfer Bank Mandiri',
            ])
            ->assertStatus(201);

        // Verify invoice is automatically set to Lunas
        $firstInvoice->refresh();
        $this->assertEquals('Lunas', $firstInvoice->status);
    }

    public function test_marking_invoice_lunas_automatically_creates_pembukuan_transaction(): void
    {
        $invoice = $this->proyekSewa->invoices()->create([
            'nomor_invoice' => 'INV-TEST-001',
            'tanggal'       => '2026-08-01',
            'jatuh_tempo'   => '2026-08-15',
            'perihal'       => 'Tagihan Invoice Sewa Server',
            'tipe'          => 'Sewa Bulanan',
            'nomor_termin'  => 2,
            'status'        => 'Terkirim',
            'subtotal'      => 5000000,
            'total_harga'   => 5000000,
            'created_by'    => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/proyeks/{$this->proyekSewa->id}/invoices/{$invoice->id}/status", [
                'status'            => 'Lunas',
                'tanggal_bayar'     => '2026-08-10',
                'metode_pembayaran' => 'Transfer Bank BRI',
                'catatan'           => 'Pelunasan invoice bulan ke-2',
            ]);

        $response->assertStatus(200);

        // Verify payment is recorded
        $this->assertDatabaseHas('proyek_pembayarans', [
            'proyek_id'         => $this->proyekSewa->id,
            'proyek_invoice_id' => $invoice->id,
            'nominal'           => 5000000,
        ]);

        // Verify Pembukuan transaction is created
        $this->assertDatabaseHas('transactions', [
            'type'      => 'masuk',
            'amount'    => 5000000,
            'proyek_id' => 'P#S-2026-777',
        ]);
    }

    public function test_deleting_payment_also_removes_pembukuan_transaction(): void
    {
        $res = $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$this->proyekSewa->id}/pembayarans", [
                'bulan_ke'          => 3,
                'tanggal_bayar'     => '2026-10-01',
                'nominal'           => 5000000,
                'metode_pembayaran' => 'Transfer Bank BCA',
            ]);

        $pembayaranId = $res->json('data.id');
        $txId = $res->json('data.transaction_id');

        $this->assertDatabaseHas('transactions', ['id' => $txId]);

        // Delete payment
        $delRes = $this->actingAs($this->user)
            ->deleteJson("/api/proyeks/{$this->proyekSewa->id}/pembayarans/{$pembayaranId}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('proyek_pembayarans', ['id' => $pembayaranId]);
        $this->assertDatabaseMissing('transactions', ['id' => $txId]);
    }

    public function test_direct_project_can_be_marked_as_paid_and_enters_pembukuan(): void
    {
        $proyekLangsung = Proyek::create([
            'kode'              => 'P#L-2026-001',
            'nama'              => 'Instalasi CCTV Gedung Sentosa',
            'client_nama'       => 'Sentosa Group',
            'tipe_kontrak'      => 'Reguler',
            'nilai'             => 25000000,
            'status'            => 'Berjalan',
            'status_pembayaran' => 'Belum Dibayar',
            'created_by'        => $this->user->id,
        ]);

        $this->assertEquals('Belum Dibayar', $proyekLangsung->status_pembayaran);

        // Mark as paid directly
        $response = $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$proyekLangsung->id}/bayar-langsung", [
                'nominal'           => 25000000,
                'tanggal_bayar'     => '2026-10-29',
                'metode_pembayaran' => 'Transfer Bank BCA',
                'catatan'           => 'Pelunasan proyek CCTV langsung',
                'masuk_pembukuan'   => true,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Lunas', $response->json('data.status_pembayaran'));
        $this->assertEquals(25000000, $response->json('data.total_dibayar'));
        $this->assertEquals(0, $response->json('data.sisa_pembayaran'));

        // Check project updated in DB
        $proyekLangsung->refresh();
        $this->assertEquals('Lunas', $proyekLangsung->status_pembayaran);

        // Check payment created
        $this->assertDatabaseHas('proyek_pembayarans', [
            'proyek_id'         => $proyekLangsung->id,
            'nominal'           => 25000000,
            'metode_pembayaran' => 'Transfer Bank BCA',
            'masuk_pembukuan'   => true,
        ]);

        // Check Transaction created in Pembukuan
        $this->assertDatabaseHas('transactions', [
            'type'      => 'masuk',
            'category'  => 'Pembayaran Client',
            'proyek_id' => 'P#L-2026-001',
            'amount'    => 25000000,
        ]);
    }

    public function test_partial_payment_marks_direct_project_as_sebagian_then_lunas(): void
    {
        $proyekLangsung = Proyek::create([
            'kode'              => 'P#L-2026-002',
            'nama'              => 'Pemasangan Access Control',
            'client_nama'       => 'PT Mandiri Jaya',
            'tipe_kontrak'      => 'Reguler',
            'nilai'             => 20000000,
            'status'            => 'Berjalan',
            'status_pembayaran' => 'Belum Dibayar',
            'created_by'        => $this->user->id,
        ]);

        // 1. Partial DP 10M
        $res1 = $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$proyekLangsung->id}/bayar-langsung", [
                'nominal'           => 10000000,
                'tanggal_bayar'     => '2026-10-20',
                'metode_pembayaran' => 'Transfer Bank Mandiri',
                'catatan'           => 'DP 50%',
            ]);

        $res1->assertStatus(200);
        $this->assertEquals('Sebagian', $res1->json('data.status_pembayaran'));
        $this->assertEquals(10000000, $res1->json('data.total_dibayar'));
        $this->assertEquals(10000000, $res1->json('data.sisa_pembayaran'));

        // 2. Sisa 10M
        $res2 = $this->actingAs($this->user)
            ->postJson("/api/proyeks/{$proyekLangsung->id}/bayar-langsung", [
                'nominal'           => 10000000,
                'tanggal_bayar'     => '2026-10-29',
                'metode_pembayaran' => 'Transfer Bank Mandiri',
                'catatan'           => 'Pelunasan sisa 50%',
            ]);

        $res2->assertStatus(200);
        $this->assertEquals('Lunas', $res2->json('data.status_pembayaran'));
        $this->assertEquals(20000000, $res2->json('data.total_dibayar'));
        $this->assertEquals(0, $res2->json('data.sisa_pembayaran'));
    }
}
