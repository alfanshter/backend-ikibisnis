<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Proyek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSewaConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_lead_with_sewa_contract(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/leads', [
            'tanggal'           => '2026-08-01',
            'nama'              => 'Pak Zulham',
            'perusahaan'        => 'PT Media Nusantara',
            'sumber'            => 'WhatsApp',
            'status'            => 'Prospek',
            'estimasi'          => 40000000,
            'tipe_kontrak'      => 'Sewa',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tipe_kontrak', 'Sewa')
            ->assertJsonPath('data.sewa_durasi_bulan', 12)
            ->assertJsonPath('data.skema_pembayaran', 'Bulanan')
            ->assertJsonPath('data.nominal_per_bulan', 3333333);

        $this->assertDatabaseHas('leads', [
            'perusahaan'        => 'PT Media Nusantara',
            'tipe_kontrak'      => 'Sewa',
            'sewa_durasi_bulan' => 12,
            'nominal_per_bulan' => 3333333,
        ]);
    }

    public function test_converting_sewa_lead_to_proyek_inherits_sewa_fields(): void
    {
        $user = User::factory()->create();

        $lead = Lead::create([
            'tanggal'           => '2026-08-01',
            'nama'              => 'Pak Zulham',
            'perusahaan'        => 'PT Media Nusantara',
            'sumber'            => 'WhatsApp',
            'status'            => 'Deal',
            'estimasi'          => 40000000,
            'tipe_kontrak'      => 'Sewa',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'sewa_mulai'        => '2026-08-01',
            'sewa_selesai'      => '2027-08-01',
            'nominal_per_bulan' => 3333333,
            'created_by'        => $user->id,
        ]);

        // When converted to Proyek via POST /api/proyeks referencing lead_id
        $response = $this->actingAs($user)->postJson('/api/proyeks', [
            'nama'        => 'Proyek Sewa IT - PT Media Nusantara',
            'client_nama' => $lead->nama,
            'perusahaan'  => $lead->perusahaan,
            'nilai'       => 40000000,
            'lead_id'     => $lead->id,
            'status'      => 'Belum Dimulai',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tipe_kontrak', 'Sewa')
            ->assertJsonPath('data.sewa_durasi_bulan', 12)
            ->assertJsonPath('data.skema_pembayaran', 'Bulanan')
            ->assertJsonPath('data.nominal_per_bulan', 3333333)
            ->assertJsonPath('data.status_sewa', 'Aktif');

        $this->assertDatabaseHas('proyeks', [
            'lead_id'           => $lead->id,
            'tipe_kontrak'      => 'Sewa',
            'sewa_durasi_bulan' => 12,
            'skema_pembayaran'  => 'Bulanan',
            'nominal_per_bulan' => 3333333,
            'status_sewa'       => 'Aktif',
        ]);
    }

    public function test_can_filter_leads_by_tipe_kontrak(): void
    {
        $user = User::factory()->create();

        Lead::create([
            'tanggal'      => '2026-08-01',
            'nama'         => 'Lead Sewa',
            'perusahaan'   => 'PT Sewa Sejahtera',
            'sumber'       => 'WhatsApp',
            'status'       => 'Prospek',
            'tipe_kontrak' => 'Sewa',
        ]);

        Lead::create([
            'tanggal'      => '2026-08-01',
            'nama'         => 'Lead Langsung',
            'perusahaan'   => 'PT Beli Putus',
            'sumber'       => 'Website',
            'status'       => 'Prospek',
            'tipe_kontrak' => 'Langsung',
        ]);

        $response = $this->actingAs($user)->getJson('/api/leads?tipe_kontrak=Sewa');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('PT Sewa Sejahtera', $response->json('data.0.perusahaan'));

        $responseLangsung = $this->actingAs($user)->getJson('/api/leads?tipe_kontrak=Langsung');
        $responseLangsung->assertStatus(200);
        $this->assertCount(1, $responseLangsung->json('data'));
        $this->assertEquals('PT Beli Putus', $responseLangsung->json('data.0.perusahaan'));
    }
}
