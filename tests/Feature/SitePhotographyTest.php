<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use App\Models\Room;
use Database\Seeders\RoomSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Only genuine Newwoodland photos are shown. Where the client hasn't provided
 * one yet, a labelled "Client Photo Required" placeholder stands in — never a
 * stock or hotlinked image, and never a social preview image.
 */
class SitePhotographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RoomSeeder::class]);
        Storage::fake('public');
    }

    public function test_no_public_page_uses_stock_or_placeholder_images(): void
    {
        $room = Room::where('is_active', true)->firstOrFail();
        $urls = [route('home'), route('about'), route('rooms.index'), route('rooms.show', $room->slug),
            route('facilities'), route('gallery'), route('contact'), route('booking')];

        foreach ($urls as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (['unsplash.com', 'pexels.com', 'ui-avatars.com', 'placehold.co', 'placeholder.com', 'placeholder.jpg', 'dummy', 'picsum', 'storage/site/hero-1.jpg', 'storage/site/about.jpg', 'og-default'] as $needle) {
                $this->assertStringNotContainsString($needle, $html, "{$url} references {$needle}");
            }
            $this->assertStringNotContainsString('og:image', $html, "{$url} shares a preview image that isn't a hotel photo");
        }
    }

    public function test_home_page_names_the_photos_it_needs(): void
    {
        $this->get(route('home'))
            ->assertSee('Hotel Exterior / Main Entrance — Client Photo Required')
            ->assertSee('Hotel Property / Wide View');
    }

    public function test_rooms_without_photos_show_their_own_name_on_the_placeholder(): void
    {
        $room = Room::where('slug', 'deluxe-room')->firstOrFail();

        $this->get(route('rooms.index'))->assertSee('Deluxe Room')->assertSee('Client Photo Required');
        $this->get(route('rooms.show', $room->slug))->assertSee('Client Photos Required');
    }

    public function test_an_empty_gallery_asks_for_client_photos_without_filters(): void
    {
        $this->get(route('gallery'))
            ->assertSee('Hotel Gallery')
            ->assertSee('Client Photos Required')
            ->assertDontSee('data-filter="pool"', false);
    }

    public function test_uploaded_photos_replace_the_placeholders(): void
    {
        Storage::disk('public')->put('site/home-hero.jpg', 'jpeg-bytes');
        Storage::disk('public')->put('site/home-intro.jpg', 'jpeg-bytes');
        $room = Room::where('slug', 'deluxe-room')->firstOrFail();
        $room->images()->create(['path' => 'rooms/abc123.jpg', 'is_primary' => true, 'sort_order' => 0]);
        GalleryImage::create(['path' => 'gallery/def456.jpg', 'title' => 'Swimming Pool', 'category' => 'pool', 'sort_order' => 1]);

        $this->get(route('home'))
            ->assertSee(Storage::disk('public')->url('site/home-hero.jpg'), false)
            ->assertSee('property="og:image"', false)
            ->assertDontSee('Hotel Exterior / Main Entrance — Client Photo Required');

        $this->get(route('rooms.show', $room->slug))
            ->assertSee('storage/rooms/abc123.jpg', false)
            ->assertSee('alt="Deluxe Room at Newwoodland Hotel &amp; Lounge"', false);

        $this->get(route('gallery'))
            ->assertSee('alt="Newwoodland Hotel &amp; Lounge — Swimming Pool"', false)
            ->assertSee('data-filter="pool"', false);
    }
}
