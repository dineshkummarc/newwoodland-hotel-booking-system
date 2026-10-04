<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function bookingWithStatus(string $status, array $overrides = []): Booking
    {
        $room = Room::factory()->create();

        return Booking::factory()->for($room)->create(array_merge([
            'status' => $status,
        ], $overrides));
    }

    public function test_admin_can_check_in_a_confirmed_booking(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1']);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CHECKED_IN, $booking->status);
        $this->assertNotNull($booking->checked_in_at);
    }

    public function test_pending_booking_cannot_be_checked_in(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_PENDING);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1']);

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        $this->assertNull($booking->checked_in_at);
    }

    public function test_cancelled_booking_cannot_be_checked_in(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CANCELLED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1']);

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_already_checked_in_booking_cannot_be_checked_in_again(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_IN, ['checked_in_at' => now()->subHour()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1']);

        $response->assertSessionHas('error');
    }

    public function test_admin_can_check_out_a_checked_in_booking(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_IN, ['checked_in_at' => now()->subDay()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-out', $booking));

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CHECKED_OUT, $booking->status);
        $this->assertNotNull($booking->checked_out_at);
    }

    public function test_confirmed_booking_cannot_be_checked_out_directly(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-out', $booking));

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
    }

    public function test_already_checked_out_booking_cannot_be_checked_out_again(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_OUT, ['checked_out_at' => now()->subHour()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-out', $booking));

        $response->assertSessionHas('error');
    }

    public function test_admin_can_mark_a_confirmed_booking_as_no_show(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.no-show', $booking));

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_NO_SHOW, $booking->status);
    }

    public function test_checked_in_booking_cannot_be_marked_no_show(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_IN, ['checked_in_at' => now()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.no-show', $booking));

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CHECKED_IN, $booking->status);
    }

    public function test_admin_can_mark_a_checked_out_booking_as_completed(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_OUT, ['checked_out_at' => now()->subHour()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.complete', $booking));

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_COMPLETED, $booking->status);
    }

    public function test_confirmed_booking_cannot_be_marked_completed_directly(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.complete', $booking));

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
    }

    public function test_checked_in_booking_blocks_room_availability(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CHECKED_IN,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(5)->toDateString().'&check_out='.now()->addDays(7)->toDateString());

        $response->assertJson(['available' => false]);
    }

    public function test_checked_out_booking_does_not_block_room_availability(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CHECKED_OUT,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(5)->toDateString().'&check_out='.now()->addDays(7)->toDateString());

        $response->assertJson(['available' => true]);
    }

    public function test_guest_cannot_perform_front_desk_actions(): void
    {
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1']);

        $response->assertRedirect(route('admin.login'));
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
    }

    public function test_manual_status_override_cannot_undo_a_checked_in_booking(): void
    {
        // Regression test: the pending/confirmed/cancelled dropdown on the
        // booking page must not be able to bypass the lifecycle state
        // machine for a booking that has already been checked in — doing
        // so would silently free the room (AVAILABILITY_BLOCKING_STATUSES
        // only covers confirmed/checked_in) while the guest is still
        // physically in the room.
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CHECKED_IN, [
            'checked_in_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $response->assertSessionHasErrors('status');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CHECKED_IN, $booking->status);
        $this->assertNotNull($booking->checked_in_at);
    }

    public function test_manual_status_override_cannot_undo_a_completed_booking(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_COMPLETED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_PENDING,
        ]);

        $response->assertSessionHasErrors('status');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_COMPLETED, $booking->status);
    }

    public function test_manual_status_override_still_allows_cancelling_a_confirmed_booking(): void
    {
        // Make sure the fix above didn't collaterally break the existing,
        // intentional pre-arrival override behaviour.
        $admin = Admin::factory()->create();
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $response->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
    }
}
