<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenawaranPdfSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_empty_penawaran_pdf_settings(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/settings/penawaran-pdf');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    public function test_can_save_penawaran_pdf_settings(): void
    {
        $payload = [
            'pageSize' => 'A4',
            'orientation' => 'portrait',
            'showHeader' => true,
            'headerTitle' => 'PENAWARAN HARGA',
            'headerSubtitle' => 'PT Putra Wisanggeni Satu',
            'showFooter' => true,
            'footerText' => 'Dokumen resmi PT Putra Wisanggeni Satu',
            'marginTop' => 20,
            'marginLeft' => 14,
            'marginRight' => 14,
            'marginBottom' => 18,
            'fontSize' => 10,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/settings/penawaran-pdf', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pengaturan PDF penawaran berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'penawaran_pdf',
        ]);

        $setting = Setting::where('key', 'penawaran_pdf')->first();
        $this->assertEquals('PENAWARAN HARGA', $setting->value['headerTitle']);
    }

    public function test_can_upload_pdf_header_image_to_storage(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('kop_surat.png', 1200, 200);

        $response = $this->actingAs($this->user)->postJson('/api/settings/penawaran-pdf/upload-image', [
            'file' => $file,
            'type' => 'headerImage',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gambar berhasil disimpan ke storage.',
                'type' => 'headerImage',
            ]);

        $setting = Setting::where('key', 'penawaran_pdf')->first();
        $this->assertNotNull($setting);
        $this->assertStringContainsString('/storage/pdf_settings/', $setting->value['headerImage']);

        $storedPath = str_replace('/storage/', '', $setting->value['headerImage']);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_can_delete_pdf_image_from_storage(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('footer_banner.png', 1200, 100);

        $uploadResponse = $this->actingAs($this->user)->postJson('/api/settings/penawaran-pdf/upload-image', [
            'file' => $file,
            'type' => 'footerImage',
        ]);

        $uploadResponse->assertStatus(200);
        $setting = Setting::where('key', 'penawaran_pdf')->first();
        $storedPath = str_replace('/storage/', '', $setting->value['footerImage']);
        Storage::disk('public')->assertExists($storedPath);

        // Delete the image
        $deleteResponse = $this->actingAs($this->user)->deleteJson('/api/settings/penawaran-pdf/image', [
            'type' => 'footerImage',
        ]);

        $deleteResponse->assertStatus(200);
        Storage::disk('public')->assertMissing($storedPath);

        $setting->refresh();
        $this->assertEquals('', $setting->value['footerImage']);
    }
}

