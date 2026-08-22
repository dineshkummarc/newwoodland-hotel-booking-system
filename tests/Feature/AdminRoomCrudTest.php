<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoomCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_room(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.rooms.store'), [
            'name' => 'Garden Suite',
            'price_per_night' => 2800,
            'max_guests' => 4,
            'is_active' => '1',
        ]);

        $room = Room::firstWhere('name', 'Garden Suite');
        $this->assertNotNull($room);
        $this->assertEquals('garden-suite', $room->slug);
        $response->assertRedirect(route('admin.rooms.edit', $room));
    }

    public function test_admin_can_update_room_price_and_it_reflects_publicly(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['price_per_night' => 1500, 'is_active' => true]);

        $this->actingAs($admin, 'admin')->put(route('admin.rooms.update', $room), [
            'name' => $room->name,
            'slug' => $room->slug,
            'price_per_night' => 9999,
            'max_guests' => $room->max_guests,
            'is_active' => '1',
        ]);

        $this->assertEquals(9999, (float) $room->fresh()->price_per_night);

        $public = $this->get(route('rooms.show', $room->slug));
        $public->assertSee('9,999');
    }

    public function test_admin_can_delete_a_room_without_bookings(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.rooms.destroy', $room));

        $response->assertRedirect(route('admin.rooms.index'));
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_guest_cannot_manage_rooms(): void
    {
        $room = Room::factory()->create();

        $response = $this->put(route('admin.rooms.update', $room), ['name' => 'Hacked']);

        $response->assertRedirect(route('admin.login'));
        $this->assertNotEquals('Hacked', $room->fresh()->name);
    }
}
