<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Proyek;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teknisi1;
    private User $teknisi2;
    private Role $adminRole;
    private Role $teknisiRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name'      => 'Admin',
            'slug'      => 'admin',
            'is_system' => false,
        ]);

        $this->teknisiRole = Role::create([
            'name'      => 'Teknisi',
            'slug'      => 'teknisi',
            'is_system' => false,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'status'  => 'aktif',
        ]);

        $this->teknisi1 = User::factory()->create([
            'role_id' => $this->teknisiRole->id,
            'name'    => 'Teknisi Budi',
            'status'  => 'aktif',
        ]);

        $this->teknisi2 = User::factory()->create([
            'role_id' => $this->teknisiRole->id,
            'name'    => 'Teknisi Joko',
            'status'  => 'aktif',
        ]);
    }

    public function test_admin_can_view_all_projects_and_leads(): void
    {
        Proyek::create([
            'kode'       => 'PRJ-001',
            'nama'       => 'Proyek Rahasia',
            'pic_id'     => $this->teknisi1->id,
            'akses_tipe' => 'personal',
            'nilai'      => 10000000,
        ]);

        Lead::create([
            'kode_lead'  => 'CRM-001',
            'nama'       => 'Client VIP',
            'perusahaan' => 'PT VIP',
            'sumber'     => 'Website',
            'tanggal'    => now()->toDateString(),
            'status'     => 'Prospek',
            'pic_id'     => $this->teknisi1->id,
            'akses_tipe' => 'personal',
        ]);

        $resProyek = $this->actingAs($this->admin)->getJson('/api/proyeks');
        $resProyek->assertOk()->assertJsonCount(1, 'data');

        $resLead = $this->actingAs($this->admin)->getJson('/api/leads');
        $resLead->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_teknisi_only_sees_assigned_personal_projects(): void
    {
        // Project 1 assigned to teknisi 1
        $p1 = Proyek::create([
            'kode'       => 'PRJ-001',
            'nama'       => 'Proyek Milik Teknisi 1',
            'pic_id'     => $this->teknisi1->id,
            'akses_tipe' => 'personal',
            'nilai'      => 5000000,
        ]);

        // Project 2 assigned to teknisi 2
        $p2 = Proyek::create([
            'kode'       => 'PRJ-002',
            'nama'       => 'Proyek Milik Teknisi 2',
            'pic_id'     => $this->teknisi2->id,
            'akses_tipe' => 'personal',
            'nilai'      => 8000000,
        ]);

        // Teknisi 1 should only see p1
        $resTeknisi1 = $this->actingAs($this->teknisi1)->getJson('/api/proyeks');
        $resTeknisi1->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('PRJ-001', $resTeknisi1->json('data.0.kode'));

        // Teknisi 1 cannot access p2 directly (returns 403)
        $resForbidden = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p2->id}");
        $resForbidden->assertStatus(403);
    }

    public function test_teknisi_can_access_project_if_member_of_team(): void
    {
        $p = Proyek::create([
            'kode'       => 'PRJ-003',
            'nama'       => 'Proyek Kolaborasi',
            'pic_id'     => $this->teknisi2->id,
            'akses_tipe' => 'personal',
            'nilai'      => 12000000,
        ]);

        // Add teknisi 1 to project team
        $p->tim()->create([
            'user_id' => $this->teknisi1->id,
            'nama'    => $this->teknisi1->name,
            'role'    => 'Teknisi Lapangan',
        ]);

        $res = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p->id}");
        $res->assertOk();
    }

    public function test_role_based_project_access(): void
    {
        $p = Proyek::create([
            'kode'          => 'PRJ-004',
            'nama'          => 'Proyek Khusus Teknisi',
            'pic_id'        => null,
            'akses_tipe'    => 'role',
            'allowed_roles' => ['teknisi'],
            'nilai'         => 3000000,
        ]);

        // Teknisi 1 has role 'teknisi', should be able to view
        $res = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p->id}");
        $res->assertOk();
    }

    public function test_price_is_hidden_from_teknisi_when_tampilkan_nilai_is_false(): void
    {
        $p = Proyek::create([
            'kode'            => 'PRJ-005',
            'nama'            => 'Proyek Harga Sensitif',
            'pic_id'          => $this->teknisi1->id,
            'akses_tipe'      => 'personal',
            'tampilkan_nilai' => false,
            'nilai'           => 25000000,
        ]);

        // 1. Admin should see the real price
        $resAdmin = $this->actingAs($this->admin)->getJson("/api/proyeks/{$p->id}");
        $resAdmin->assertOk();
        $this->assertEquals(25000000, $resAdmin->json('data.nilai'));
        $this->assertEquals('Rp25.000.000', $resAdmin->json('data.nilai_format'));

        // 2. Teknisi 1 (PIC) can access the project, but the price is masked
        $resTeknisi = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p->id}");
        $resTeknisi->assertOk();
        $this->assertEquals(0, $resTeknisi->json('data.nilai'));
        $this->assertEquals('— (Dirahasiakan)', $resTeknisi->json('data.nilai_format'));

        // 3. In index list, price is also masked for teknisi
        $resIndex = $this->actingAs($this->teknisi1)->getJson('/api/proyeks');
        $resIndex->assertOk();
        $this->assertEquals(0, $resIndex->json('data.0.nilai'));
        $this->assertEquals('— (Dirahasiakan)', $resIndex->json('data.0.nilai_format'));
    }

    public function test_admin_can_toggle_tampilkan_nilai(): void
    {
        $p = Proyek::create([
            'kode'            => 'PRJ-006',
            'nama'            => 'Proyek Fleksibel',
            'pic_id'          => $this->teknisi1->id,
            'akses_tipe'      => 'personal',
            'tampilkan_nilai' => false,
            'nilai'           => 15000000,
        ]);

        // Initially masked for teknisi
        $resTeknisi = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p->id}");
        $this->assertEquals(0, $resTeknisi->json('data.nilai'));

        // Update tampilkan_nilai to true
        $updateRes = $this->actingAs($this->admin)->putJson("/api/proyeks/{$p->id}", [
            'tampilkan_nilai' => true,
        ]);
        $updateRes->assertOk();

        // Now teknisi can see the price
        $resTeknisiAfter = $this->actingAs($this->teknisi1)->getJson("/api/proyeks/{$p->id}");
        $this->assertEquals(15000000, $resTeknisiAfter->json('data.nilai'));
        $this->assertEquals('Rp15.000.000', $resTeknisiAfter->json('data.nilai_format'));
    }
}
