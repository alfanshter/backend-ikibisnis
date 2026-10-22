<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenawaranServiceItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_penawaran_accepts_jasa_items_without_part_reference(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'tanggal' => '2026-08-01',
            'nama' => 'Budi',
            'perusahaan' => 'PT Sinar Jaya',
            'sumber' => 'WhatsApp',
            'estimasi' => 2500000,
            'status' => 'Prospek',
            'kebutuhan' => 'Website company profile',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/penawarans', [
            'lead_id' => $lead->id,
            'tanggal' => '2026-08-10',
            'valid_until' => '2026-08-20',
            'status' => 'Draft',
            'catatan' => 'Proposal jasa desain UI/UX',
            'subtotal' => 5000000,
            'pajak' => 0,
            'diskon' => 0,
            'total_harga' => 5000000,
            'items' => [
                [
                    'jenis_item' => 'jasa',
                    'nama_item' => 'Jasa bikin aplikasi android',
                    'deskripsi' => 'UI/UX + implementasi MVP',
                    'qty' => 1,
                    'harga_satuan' => 5000000,
                    'total_harga' => 5000000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.jenis_item', 'jasa')
            ->assertJsonPath('data.items.0.nama_item', 'Jasa bikin aplikasi android');
    }

    public function test_penawaran_update_accepts_multiple_mixed_items(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'tanggal' => '2026-08-01',
            'nama' => 'Rina',
            'perusahaan' => 'PT Maju Bersama',
            'sumber' => 'Instagram',
            'estimasi' => 12000000,
            'status' => 'Prospek',
            'kebutuhan' => 'Sistem CRM',
        ]);

        $createResponse = $this->actingAs($user, 'sanctum')->postJson('/api/penawarans', [
            'lead_id' => $lead->id,
            'tanggal' => '2026-08-10',
            'valid_until' => '2026-08-20',
            'status' => 'Draft',
            'subtotal' => 9000000,
            'pajak' => 0,
            'diskon' => 0,
            'total_harga' => 9000000,
            'items' => [
                [
                    'jenis_item' => 'jasa',
                    'nama_item' => 'UI/UX research',
                    'deskripsi' => 'Discovery phase',
                    'qty' => 1,
                    'harga_satuan' => 3000000,
                    'total_harga' => 3000000,
                ],
            ],
        ]);

        $penawaranId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($user, 'sanctum')->putJson('/api/penawarans/' . $penawaranId, [
            'lead_id' => $lead->id,
            'nomor_penawaran' => $createResponse->json('data.nomor_penawaran'),
            'tanggal' => '2026-08-10',
            'valid_until' => '2026-08-20',
            'status' => 'Draft',
            'subtotal' => 9000000,
            'pajak' => 0,
            'diskon' => 0,
            'total_harga' => 9000000,
            'items' => [
                [
                    'jenis_item' => 'jasa',
                    'nama_item' => 'UI/UX research',
                    'deskripsi' => 'Discovery phase',
                    'qty' => 1,
                    'harga_satuan' => 3000000,
                    'total_harga' => 3000000,
                ],
                [
                    'jenis_item' => 'barang',
                    'nama_item' => 'License produk',
                    'deskripsi' => 'Lisensi awal',
                    'qty' => 2,
                    'harga_satuan' => 3000000,
                    'total_harga' => 6000000,
                ],
            ],
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.items.0.jenis_item', 'jasa')
            ->assertJsonPath('data.items.1.jenis_item', 'barang')
            ->assertJsonCount(2, 'data.items');
    }
}
