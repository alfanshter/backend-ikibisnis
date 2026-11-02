<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenawaranPdfImageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Endpoint gambar PDF dibuat publik (tanpa auth) agar bisa dipakai preview
     * <img> dan fetch() lintas origin oleh frontend saat menyusun PDF.
     */
    public function test_can_fetch_pdf_image_without_authentication(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('ttd.png', 600, 200);
        $this->actingAs($user)->postJson('/api/settings/penawaran-pdf/upload-image', [
            'file' => $file,
            'type' => 'signatureImage',
        ])->assertStatus(200);

        $response = $this->get('/api/settings/penawaran-pdf/image/signatureImage');

        $response->assertStatus(200);
        $this->assertStringStartsWith('image/', (string) $response->headers->get('content-type'));
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_returns_404_when_image_is_not_set(): void
    {
        Setting::create([
            'key' => 'penawaran_pdf',
            'group' => 'penawaran',
            'value' => ['signatureImage' => ''],
        ]);

        $this->get('/api/settings/penawaran-pdf/image/signatureImage')->assertStatus(404);
    }

    public function test_returns_404_for_unknown_image_type(): void
    {
        $this->get('/api/settings/penawaran-pdf/image/unknownImage')->assertStatus(404);
    }

    public function test_blocks_image_path_outside_pdf_settings_folder(): void
    {
        Setting::create([
            'key' => 'penawaran_pdf',
            'group' => 'penawaran',
            'value' => ['headerImage' => '/storage/secret/file.png'],
        ]);

        $this->get('/api/settings/penawaran-pdf/image/headerImage')->assertStatus(404);
    }
}
