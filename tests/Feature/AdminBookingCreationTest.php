<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(Room $room, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $room->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Walk-in Guest',
            'email' => 'walkin@example.com',
            'phone' => '9800000000',
        ], $overrides);
    }

    public function test_admin_can_create_a_walk_in_booking(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->payload($room));

        $booking = Booking::firstWhere('email', 'walkin@example.com');
        $this->assertNotNull($booking);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        $this->assertNull($booking->user_id);
        $this->assertEquals(3000, (float) $booking->total_price);
        $response->assertRedirect(route('admin.bookings.show', $booking));
    }

    public function test_front_desk_can_create_a_walk_in_booking(): void
    {
        $admin = Admin::factory()->frontDesk()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->payload($room));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['email' => 'walkin@example.com']);
    }

    public function test_admin_booking_creation_prevents_double_booking(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        $checkIn = now()->addDays(3)->toDateString();
        $checkOut = now()->addDays(5)->toDateString();

        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->payload($room, [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]));

        $response->assertSessionHasErrors('check_in');
        $this->assertDatabaseMissing('bookings', ['email' => 'walkin@example.com']);
    }

    public function test_admin_booking_creation_rejects_invalid_dates(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->payload($room, [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]));

        $response->assertSessionHasErrors('check_out');
    }

    public function test_admin_cannot_book_a_maintenance_room(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->payload($room));

        $response->assertSessionHasErrors('room_id');
    }

    public function test_maintenance_rooms_are_excluded_from_the_admin_create_form(): void
    {
        $admin = Admin::factory()->create();
        $available = Room::factory()->create(['name' => 'Bookable Room']);
        $maintenance = Room::factory()->create(['name' => 'Maintenance Room', 'availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.create'));

        $response->assertSee('Bookable Room');
        $response->assertDontSee('Maintenance Room');
    }

    public function test_guest_cannot_access_admin_booking_creation(): void
    {
        $response = $this->get(route('admin.bookings.create'));

        $response->assertRedirect(route('admin.login'));
    }
}
