<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_leads_by_nama(): void
    {
        Lead::create([
            'tanggal'    => '2026-10-27',
            'nama'       => 'Pak Zulham',
            'perusahaan' => 'Koperasi Sejahtera',
            'sumber'     => 'WhatsApp',
            'status'     => 'Prospek',
        ]);

        Lead::create([
            'tanggal'    => '2026-10-27',
            'nama'       => 'Budi Santoso',
            'perusahaan' => 'PT Maju Bersama',
            'sumber'     => 'Website',
            'status'     => 'Prospek',
        ]);

        $results = Lead::query()->search('zulha')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Pak Zulham', $results->first()->nama);
    }

    public function test_can_search_leads_by_pic_user_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Zulham PIC',
        ]);

        Lead::create([
            'tanggal'    => '2026-10-27',
            'nama'       => 'Client A',
            'perusahaan' => 'Perusahaan A',
            'pic_id'     => $user->id,
            'sumber'     => 'WhatsApp',
            'status'     => 'Prospek',
        ]);

        Lead::create([
            'tanggal'    => '2026-10-27',
            'nama'       => 'Client B',
            'perusahaan' => 'Perusahaan B',
            'sumber'     => 'Website',
            'status'     => 'Prospek',
        ]);

        $results = Lead::query()->search('Zulham PIC')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Client A', $results->first()->nama);
    }
}
