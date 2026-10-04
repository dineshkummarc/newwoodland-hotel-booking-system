<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * B5 — the admin status override can never double-book a room.
 */
class AdminStatusConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function dates(): array
    {
        return ['check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(8)->toDateString()];
    }

    public function test_reconfirming_a_cancelled_booking_is_refused_when_the_room_was_resold(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->for($room)->confirmed()->create($this->dates());
        $cancelled = Booking::factory()->for($room)->cancelled()->create($this->dates() + ['cancelled_at' => now()]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $cancelled), ['status' => Booking::STATUS_CONFIRMED]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->refresh()->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.status_change_refused', 'target_id' => $cancelled->id]);
    }

    public function test_confirming_a_pending_booking_is_refused_when_another_guest_holds_the_room(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        $stale = Booking::factory()->for($room)->create($this->dates());
        $stale->forceFill(['created_at' => now()->subHours(2)])->save();
        // Another guest's fresh hold (mid-payment) on the same room/dates.
        Booking::factory()->for($room)->create($this->dates());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $stale), ['status' => Booking::STATUS_CONFIRMED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $stale->refresh()->status);
    }

    public function test_a_partly_overlapping_stay_also_blocks_confirmation(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->for($room)->confirmed()->create([
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
        ]);
        $cancelled = Booking::factory()->for($room)->cancelled()->create($this->dates());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $cancelled), ['status' => Booking::STATUS_CONFIRMED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->refresh()->status);
    }

    public function test_a_multi_room_booking_is_refused_if_any_of_its_rooms_is_taken(): void
    {
        $admin = Admin::factory()->create();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $cancelled = Booking::factory()->for($roomA)->cancelled()->create($this->dates());
        $cancelled->additionalRooms()->create(['room_id' => $roomB->id, 'guests_label' => '2 Adults', 'nightly_rate' => 1000, 'subtotal' => 3000]);
        Booking::factory()->for($roomB)->confirmed()->create($this->dates());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $cancelled), ['status' => Booking::STATUS_CONFIRMED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->refresh()->status);
    }

    public function test_reconfirming_works_when_the_room_is_still_free_and_is_audited(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        $cancelled = Booking::factory()->for($room)->cancelled()->create($this->dates() + ['cancelled_at' => now()]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $cancelled), ['status' => Booking::STATUS_CONFIRMED])
            ->assertSessionHasNoErrors();

        $cancelled->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $cancelled->status);
        $this->assertNull($cancelled->cancelled_at);
        $this->assertTrue(AuditLog::where('action', 'booking.status_changed')->where('target_id', $cancelled->id)->exists());
    }

    public function test_cancelling_never_needs_a_room_check(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->for($room)->confirmed()->create($this->dates());
        $pending = Booking::factory()->for($room)->create($this->dates());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $pending), ['status' => Booking::STATUS_CANCELLED])
            ->assertSessionHasNoErrors();

        $this->assertSame(Booking::STATUS_CANCELLED, $pending->refresh()->status);
    }

    public function test_the_service_enforces_the_rule_for_any_caller(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->confirmed()->create($this->dates());
        $cancelled = Booking::factory()->for($room)->cancelled()->create($this->dates());

        $this->expectException(ValidationException::class);
        app(BookingService::class)->changeStatusByStaff($cancelled, Booking::STATUS_CONFIRMED);
    }

    public function test_checked_in_bookings_cannot_be_moved_by_the_override(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create($this->dates() + ['status' => Booking::STATUS_CHECKED_IN]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->refresh()->status);
    }
}
