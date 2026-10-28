<?php

namespace Tests\Feature;

use App\Models\Proyek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyekSewaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_proyek_sewa_with_auto_calculated_monthly(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/proyeks', [
            'nama'              => 'Proyek IT Sewa Server & Lisensi',
            'client_nama'       => 'PT Mitra Digital',
            'tipe_kontrak'      => 'Sewa',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'nilai'             => 40000000,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Sewa', $response->json('data.tipe_kontrak'));
        $this->assertEquals(40000000, $response->json('data.nilai'));
        $this->assertEquals(12, $response->json('data.sewa_durasi_bulan'));
        $this->assertEquals(3333333, $response->json('data.nominal_per_bulan'));
        $this->assertEquals('Bulanan', $response->json('data.skema_pembayaran'));
        $this->assertEquals('Aktif', $response->json('data.status_sewa'));
    }

    public function test_can_generate_monthly_sewa_invoices(): void
    {
        $proyek = Proyek::create([
            'kode'              => 'P#S-2026-001',
            'nama'              => 'Proyek IT Sewa',
            'tipe_kontrak'      => 'Sewa',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'nominal_per_bulan' => 3333333,
            'nilai'             => 40000000,
            'status'            => 'Berjalan',
            'created_by'        => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/proyeks/{$proyek->id}/generate-sewa-invoices", [
            'skema' => 'Bulanan',
            'ppn'   => 11,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(12, $response->json('count'));
        $this->assertCount(12, $proyek->invoices);

        $firstInvoice = $proyek->invoices()->reorder()->orderBy('id')->first();
        $this->assertEquals('Sewa Bulanan', $firstInvoice->tipe);
        $this->assertEquals(1, $firstInvoice->nomor_termin);
        $this->assertEquals(3333333, $firstInvoice->subtotal);
    }

    public function test_can_perpanjang_sewa(): void
    {
        $proyek = Proyek::create([
            'kode'              => 'P#S-2026-002',
            'nama'              => 'Proyek IT Sewa Perpanjangan',
            'tipe_kontrak'      => 'Sewa',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'nominal_per_bulan' => 3333333,
            'nilai'             => 40000000,
            'status'            => 'Berjalan',
            'created_by'        => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/proyeks/{$proyek->id}/perpanjang-sewa", [
            'durasi_bulan'      => 12,
            'sewa_mulai_baru'   => '2027-08-02',
            'sewa_selesai_baru' => '2028-08-02',
            'nominal'           => 40000000,
            'skema_pembayaran'  => 'Bulanan',
            'catatan'           => 'Perpanjangan kontrak tahun ke-2',
        ]);

        $response->assertStatus(200);
        $proyek->refresh();

        $this->assertEquals('2028-08-02', $proyek->sewa_selesai->format('Y-m-d'));
        $this->assertEquals(24, $proyek->sewa_durasi_bulan);
        $this->assertEquals(80000000, $proyek->nilai);
        $this->assertCount(1, $proyek->perpanjanganSewas);
        $this->assertEquals('Perpanjangan kontrak tahun ke-2', $proyek->perpanjanganSewas->first()->catatan);
    }
}
