<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireAbandonedBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_pending_bookings_past_the_hold_window(): void
    {
        $room = Room::factory()->create();
        $stale = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subMinutes(Booking::PENDING_HOLD_MINUTES + 5),
        ]);

        $this->artisan('bookings:expire-abandoned')->assertExitCode(0);

        $stale->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $stale->status);
        $this->assertNotNull($stale->cancelled_at);
    }

    public function test_command_leaves_fresh_pending_bookings_alone(): void
    {
        $room = Room::factory()->create();
        $fresh = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subMinutes(5),
        ]);

        $this->artisan('bookings:expire-abandoned');

        $this->assertEquals(Booking::STATUS_PENDING, $fresh->fresh()->status);
    }

    public function test_command_does_not_touch_confirmed_or_checked_in_bookings(): void
    {
        $room = Room::factory()->create();
        $confirmed = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'created_at' => now()->subDays(2),
        ]);
        $checkedIn = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CHECKED_IN,
            'checked_in_at' => now()->subHour(),
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('bookings:expire-abandoned');

        $this->assertEquals(Booking::STATUS_CONFIRMED, $confirmed->fresh()->status);
        $this->assertEquals(Booking::STATUS_CHECKED_IN, $checkedIn->fresh()->status);
    }

    public function test_pending_booking_with_a_completed_payment_is_not_auto_expired(): void
    {
        // The rare edge case confirmAfterVerifiedPayment() itself documents:
        // payment succeeded but the room lost its hold in the meantime, so
        // the booking stays pending, flagged for manual staff resolution —
        // auto-expiry must never bury a booking someone actually paid for.
        $room = Room::factory()->create();
        $stale = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subHour(),
        ]);
        \App\Models\Payment::factory()->for($stale)->completed()->create(['amount' => $stale->total_price]);

        $this->artisan('bookings:expire-abandoned');

        $this->assertEquals(Booking::STATUS_PENDING, $stale->fresh()->status);
    }

    public function test_command_is_idempotent(): void
    {
        $room = Room::factory()->create();
        $stale = Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('bookings:expire-abandoned');
        $this->artisan('bookings:expire-abandoned');

        $this->assertEquals(Booking::STATUS_CANCELLED, $stale->fresh()->status);
    }

    public function test_expired_booking_no_longer_counts_as_pending_on_the_dashboard(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('bookings:expire-abandoned');

        $admin = \App\Models\Admin::factory()->create();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertViewHas('pendingBookings', 0);
    }

    public function test_expiring_frees_the_room_for_other_guests(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subHour(),
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
        ]);

        $this->artisan('bookings:expire-abandoned');

        $response = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(10)->toDateString().'&check_out='.now()->addDays(12)->toDateString());

        $response->assertJson(['available' => true]);
    }
}
