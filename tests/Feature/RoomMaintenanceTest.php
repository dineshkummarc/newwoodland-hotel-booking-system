<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_under_maintenance_cannot_be_booked(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9800000000',
        ]);

        $response->assertSessionHasErrors('room_id');
    }

    public function test_out_of_service_room_cannot_be_booked(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_OUT_OF_SERVICE]);

        $response = $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9800000000',
        ]);

        $response->assertSessionHasErrors('room_id');
    }

    public function test_available_room_can_still_be_booked(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        $response = $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9800000000',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bookings', ['room_id' => $room->id, 'customer_name' => 'Jane Doe']);
    }

    public function test_maintenance_room_is_excluded_from_the_booking_form_room_list(): void
    {
        $available = Room::factory()->create(['availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $maintenance = Room::factory()->create(['availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->get(route('booking'));

        $response->assertSee($available->name);
        $response->assertDontSee($maintenance->name);
    }

    public function test_maintenance_room_still_appears_in_public_catalog_when_active(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->get(route('rooms.index'));

        $response->assertSee($room->name);
    }

    public function test_availability_endpoint_reports_unavailable_for_maintenance_room(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(3)->toDateString().'&check_out='.now()->addDays(5)->toDateString());

        $response->assertJson(['available' => false]);
    }

    public function test_admin_can_set_room_to_maintenance(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['availability_status' => Room::AVAILABILITY_AVAILABLE]);

        $this->actingAs($admin, 'admin')->put(route('admin.rooms.update', $room), [
            'name' => $room->name,
            'slug' => $room->slug,
            'price_per_night' => $room->price_per_night,
            'max_guests' => $room->max_guests,
            'is_active' => '1',
            'availability_status' => Room::AVAILABILITY_MAINTENANCE,
        ]);

        $this->assertEquals(Room::AVAILABILITY_MAINTENANCE, $room->fresh()->availability_status);
    }

    public function test_room_history_is_preserved_when_marked_out_of_service(): void
    {
        $room = Room::factory()->create();
        $booking = \App\Models\Booking::factory()->for($room)->create();

        $room->update(['availability_status' => Room::AVAILABILITY_OUT_OF_SERVICE]);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'room_id' => $room->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }
}
