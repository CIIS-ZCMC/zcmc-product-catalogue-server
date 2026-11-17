<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSpecsPdfS3Test extends TestCase
{
    use RefreshDatabase;

    public function test_upload_specs_pdf_uses_configured_s3_disk(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->create('specs.pdf', 128, 'application/pdf');

        $response = $this->postJson('/api/products/upload-specs-pdf', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'file_path', 'file_url', 'original_filename']);

        $filePath = $response->json('file_path');
        $this->assertIsString($filePath);
        $this->assertStringStartsWith('tech_specs/', $filePath);

        Storage::disk('s3')->assertExists($filePath);
        $this->assertSame(Storage::disk('s3')->url($filePath), $response->json('file_url'));

        $product = new Product(['tech_specs_pdf_path' => $filePath]);
        $this->assertSame(Storage::disk('s3')->url($filePath), $product->tech_specs_pdf_url);
    }
}
