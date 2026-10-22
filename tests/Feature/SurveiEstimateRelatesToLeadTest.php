<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveiEstimateRelatesToLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_survei_uses_lead_estimasi_when_created_from_crm_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'tanggal' => '2026-08-01',
            'nama' => 'Budi',
            'perusahaan' => 'PT Sinar Jaya',
            'sumber' => 'WhatsApp',
            'estimasi' => 2500000,
            'status' => 'Survey',
            'kebutuhan' => 'Instalasi CCTV',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/surveis', [
            'lead_id' => $lead->id,
            'client' => 'Budi',
            'perusahaan' => 'PT Sinar Jaya',
            'lokasi' => 'Bandung',
            'tanggal' => '2026-08-10',
            'kebutuhan' => 'Instalasi CCTV',
            'status' => 'Dijadwalkan',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lead_id', $lead->id)
            ->assertJsonPath('data.estimasi', 2500000);
    }
}
