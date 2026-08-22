<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_pages_return_successfully(): void
    {
        $routes = [
            'home', 'about', 'rooms.index', 'facilities', 'gallery',
            'contact', 'booking', 'privacy', 'terms', 'sitemap', 'robots',
        ];

        foreach ($routes as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_rooms_page_lists_active_room_names(): void
    {
        $active = Room::factory()->create(['name' => 'Deluxe Room']);
        Room::factory()->inactive()->create(['name' => 'Hidden Room']);

        $response = $this->get(route('rooms.index'));

        $response->assertOk();
        $response->assertSee('Deluxe Room');
        $response->assertDontSee('Hidden Room');
    }

    public function test_dynamic_room_details_page_shows_correct_room(): void
    {
        $room = Room::factory()->create(['name' => 'Executive Room', 'slug' => 'executive-room']);

        $response = $this->get(route('rooms.show', $room->slug));

        $response->assertOk();
        $response->assertSee('Executive Room');
    }

    public function test_inactive_room_details_page_returns_404(): void
    {
        $room = Room::factory()->inactive()->create();

        $this->get(route('rooms.show', $room->slug))->assertNotFound();
    }

    public function test_unknown_route_returns_custom_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
    }

    public function test_admin_routes_are_disallowed_in_robots_txt(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertSee('Disallow: /admin');
    }

    public function test_sitemap_includes_active_room(): void
    {
        $room = Room::factory()->create(['slug' => 'garden-suite']);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertSee('garden-suite');
    }
}
