<?php

namespace Tests\Feature;

use App\Models\LogPekerjaan;
use App\Models\Proyek;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogPekerjaanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teknisi;
    private Proyek $proyek;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_system' => false]);
        $teknisiRole = Role::create(['name' => 'Teknisi', 'slug' => 'teknisi', 'is_system' => false]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'name'    => 'Pak Supervisor',
            'status'  => 'aktif',
        ]);

        $this->teknisi = User::factory()->create([
            'role_id' => $teknisiRole->id,
            'name'    => 'Budi Teknisi',
            'status'  => 'aktif',
        ]);

        $this->proyek = Proyek::create([
            'kode'       => 'PRJ-TEST-001',
            'nama'       => 'Proyek Test CCTV',
            'pic_id'     => $this->teknisi->id,
            'akses_tipe' => 'semua',
            'nilai'      => 10000000,
        ]);
    }

    public function test_can_create_log_with_creator_duration_and_photo_captions(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('foto1.jpg');
        $file2 = UploadedFile::fake()->image('foto2.jpg');

        $res = $this->actingAs($this->teknisi)->postJson("/api/proyeks/{$this->proyek->id}/log-pekerjaan", [
            'tanggal'              => '2026-10-30',
            'aktivitas'            => 'Penarikan kabel UTP Cat6 dan setting IP camera',
            'tim'                  => ['Budi Teknisi', 'Joko'],
            'durasi'               => '8',
            'kendala'              => 'Jalur pipa sempit di plafon',
            'new_fotos'            => [$file1, $file2],
            'new_foto_labels'      => ['Before', 'After'],
            'new_foto_keterangans' => [
                'Kondisi jalur plafon sebelum ditarik kabel',
                'Kabel UTP sudah rapi di dalam conduit',
            ],
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.aktivitas', 'Penarikan kabel UTP Cat6 dan setting IP camera');
        $res->assertJsonPath('data.durasi', '8');
        $res->assertJsonPath('data.status_verifikasi', 'Menunggu Review');
        $res->assertJsonPath('data.creator.name', 'Budi Teknisi');
        $res->assertJsonCount(2, 'data.fotos');
        $this->assertEquals('Before', $res->json('data.fotos.0.label'));
        $this->assertEquals('Kondisi jalur plafon sebelum ditarik kabel', $res->json('data.fotos.0.keterangan'));
        $this->assertEquals('After', $res->json('data.fotos.1.label'));
        $this->assertEquals('Kabel UTP sudah rapi di dalam conduit', $res->json('data.fotos.1.keterangan'));
    }

    public function test_can_cross_check_and_mark_as_revisi(): void
    {
        $log = $this->proyek->logPekerjaans()->create([
            'tanggal'           => '2026-10-30',
            'aktivitas'         => 'Pemasangan grounding panel',
            'durasi'            => '6',
            'status_verifikasi' => LogPekerjaan::STATUS_MENUNGGU_REVIEW,
            'created_by'        => $this->teknisi->id,
        ]);

        // Supervisor cross checks and requests revision
        $res = $this->actingAs($this->admin)->patchJson("/api/proyeks/{$this->proyek->id}/log-pekerjaan/{$log->id}/verifikasi", [
            'status_verifikasi' => LogPekerjaan::STATUS_PERLU_REVISI,
            'catatan_revisi'    => 'Kabel grounding belum memakai sepatu kabel / skun yang sesuai standar.',
        ]);

        $res->assertOk();
        $res->assertJsonPath('data.status_verifikasi', 'Perlu Revisi');
        $res->assertJsonPath('data.catatan_revisi', 'Kabel grounding belum memakai sepatu kabel / skun yang sesuai standar.');
        $res->assertJsonPath('data.verifier.name', 'Pak Supervisor');

        // Verify in DB
        $log->refresh();
        $this->assertEquals('Perlu Revisi', $log->status_verifikasi);
        $this->assertEquals($this->admin->id, $log->verified_by);
    }

    public function test_editing_revisi_log_resets_status_to_menunggu_review(): void
    {
        $log = $this->proyek->logPekerjaans()->create([
            'tanggal'           => '2026-10-30',
            'aktivitas'         => 'Pemasangan grounding panel',
            'durasi'            => '6',
            'status_verifikasi' => LogPekerjaan::STATUS_PERLU_REVISI,
            'catatan_revisi'    => 'Perlu ganti skun',
            'created_by'        => $this->teknisi->id,
            'verified_by'       => $this->admin->id,
            'verified_at'       => now(),
        ]);

        // Teknisi edits log after fixing
        $res = $this->actingAs($this->teknisi)->putJson("/api/proyeks/{$this->proyek->id}/log-pekerjaan/{$log->id}", [
            'aktivitas' => 'Pemasangan grounding panel (skun sudah diganti sesuai standar)',
            'durasi'    => '7',
        ]);

        $res->assertOk();
        $res->assertJsonPath('data.status_verifikasi', 'Menunggu Review');

        $log->refresh();
        $this->assertEquals('Menunggu Review', $log->status_verifikasi);
    }

    public function test_can_approve_log_as_sesuai(): void
    {
        $log = $this->proyek->logPekerjaans()->create([
            'tanggal'           => '2026-10-30',
            'aktivitas'         => 'Pemasangan grounding panel',
            'durasi'            => '7',
            'status_verifikasi' => LogPekerjaan::STATUS_MENUNGGU_REVIEW,
            'created_by'        => $this->teknisi->id,
        ]);

        $res = $this->actingAs($this->admin)->patchJson("/api/proyeks/{$this->proyek->id}/log-pekerjaan/{$log->id}/verifikasi", [
            'status_verifikasi' => LogPekerjaan::STATUS_SESUAI,
            'catatan_revisi'    => 'Bagus, skun dan grounding sudah terpasang rapi.',
        ]);

        $res->assertOk();
        $res->assertJsonPath('data.status_verifikasi', 'Sesuai');
        $res->assertJsonPath('data.verifier.name', 'Pak Supervisor');
    }
}

