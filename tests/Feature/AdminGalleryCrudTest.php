<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGalleryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * UploadedFile::fake()->image() needs the GD extension to synthesize
     * pixel data, which isn't available in every environment (including
     * this one). This supplies a real, minimal, valid 1x1 PNG's actual
     * bytes instead, so Laravel's `image` validation rule — which decodes
     * real image data, not just the MIME header — is genuinely exercised
     * without depending on GD being installed.
     */
    protected function fakeRealImage(string $name = 'photo.png'): UploadedFile
    {
        $onePixelPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $path = tempnam(sys_get_temp_dir(), 'gallery-test').'.png';
        file_put_contents($path, $onePixelPng);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_admin_can_upload_a_gallery_image(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.gallery.store'), [
            'category' => 'rooms',
            'title' => 'Deluxe Room View',
            'image' => $this->fakeRealImage('room.png'),
        ]);

        $image = GalleryImage::firstWhere('title', 'Deluxe Room View');
        $this->assertNotNull($image);
        Storage::disk('public')->assertExists($image->path);
        $response->assertRedirect(route('admin.gallery.index'));
    }

    public function test_gallery_upload_rejects_a_non_image_file(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.gallery.store'), [
            'category' => 'rooms',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_deleting_a_gallery_image_removes_the_stored_file(): void
    {
        $admin = Admin::factory()->create();
        $path = $this->fakeRealImage('old.png')->store('gallery', 'public');
        $image = GalleryImage::create(['path' => $path, 'category' => 'garden', 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')->delete(route('admin.gallery.destroy', $image));

        $this->assertDatabaseMissing('gallery_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_cannot_manage_the_gallery(): void
    {
        $response = $this->post(route('admin.gallery.store'), ['category' => 'rooms']);

        $response->assertRedirect(route('admin.login'));
    }
}
