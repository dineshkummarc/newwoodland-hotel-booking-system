<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * The live availability check (GET /rooms/{id}/availability) must answer
 * exactly what a real booking submission then does — for every scenario.
 * Before the fix it said "available" for past dates, dates beyond the
 * booking horizon, over-long stays, oversized parties and a room under
 * maintenance, and the booking was then refused.
 */
class AvailabilityConsistencyTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function room(array $overrides = []): Room
    {
        return Room::factory()->create(array_merge([
            'name' => 'Deluxe Room', 'price_per_night' => 1500, 'max_guests' => 3,
            'availability_status' => Room::AVAILABILITY_AVAILABLE, 'is_active' => true,
        ], $overrides))->fresh();
    }

    private function d(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }

    /**
     * Ask the availability endpoint, then really try to book; both must agree.
     * Returns the endpoint's JSON for further assertions.
     */
    private function assertConsistent(string $scenario, Room $room, string $in, string $out, bool $expectBookable, array $extra = []): array
    {
        $guests = $extra['guests_label'] ?? '2 Adults';
        $api = $this->getJson("/rooms/{$room->id}/availability?check_in={$in}&check_out={$out}&guests_label=".urlencode($guests))
            ->assertOk()->json();

        Cache::flush(); // the booking form's own rate limit is not what is under test
        $this->n++;
        $before = Booking::count();
        $this->post(route('booking.store'), array_merge([
            'room_id' => $room->id, 'check_in' => $in, 'check_out' => $out, 'guests_label' => '2 Adults',
            'customer_name' => 'Guest', 'email' => "guest{$this->n}@example.com",
            'phone' => '98'.str_pad((string) (10000000 + $this->n), 8, '0', STR_PAD_LEFT),
        ], $extra));
        $booked = Booking::count() > $before;

        $apiSaysBookable = $api['available'] && ($extra['rooms_count'] ?? 1) <= $api['free_rooms'];
        $this->assertSame($expectBookable, $booked, "[{$scenario}] booking outcome");
        $this->assertSame($booked, $apiSaysBookable, "[{$scenario}] availability check disagrees with the booking");
        if (! $api['available']) {
            $this->assertNotEmpty($api['reason'], "[{$scenario}] a reason is given");
        }

        // Keep each scenario independent.
        Booking::where('email', "guest{$this->n}@example.com")->each(fn ($b) => $b->delete());

        return $api;
    }

    public function test_dates_and_stay_rules(): void
    {
        $a = $this->room();
        $this->room();

        $this->assertConsistent('free', $a, $this->d(5), $this->d(7), true);
        $this->assertConsistent('same-day check-in', $a, $this->d(0), $this->d(1), true);
        $past = $this->assertConsistent('past check-in', $a, $this->d(-1), $this->d(1), false);
        $this->assertSame('Check-in date cannot be in the past.', $past['reason']);
        $this->assertConsistent('beyond booking horizon', $a, $this->d(800), $this->d(802), false);
        $this->assertConsistent('stay too long', $a, $this->d(5), $this->d(200), false);
        $this->assertConsistent('party over capacity', $a, $this->d(5), $this->d(7), false, ['guests_label' => '4 Adults']);
    }

    public function test_existing_bookings_and_holds(): void
    {
        $a = $this->room();
        $b = $this->room();

        Booking::factory()->for($a)->confirmed()->create(['check_in' => $this->d(5), 'check_out' => $this->d(7)]);
        $this->assertConsistent('one confirmed, sibling free', $a, $this->d(5), $this->d(7), true);

        Booking::factory()->for($b)->confirmed()->create(['check_in' => $this->d(5), 'check_out' => $this->d(7)]);
        $this->assertConsistent('all rooms of the type taken', $a, $this->d(6), $this->d(8), false);
        $this->assertConsistent('arrive on their checkout day', $a, $this->d(7), $this->d(9), true);
        $this->assertConsistent('leave on their check-in day', $a, $this->d(3), $this->d(5), true);

        Booking::query()->delete();
        $held = Booking::factory()->for($a)->create(['check_in' => $this->d(5), 'check_out' => $this->d(7), 'status' => Booking::STATUS_PENDING]);
        Booking::factory()->for($b)->create(['check_in' => $this->d(5), 'check_out' => $this->d(7), 'status' => Booking::STATUS_PENDING]);
        $this->assertConsistent('fresh unpaid holds on every room', $a, $this->d(5), $this->d(7), false);

        Booking::query()->update(['created_at' => now()->subHour()]);
        $this->assertConsistent('expired unpaid holds', $a, $this->d(5), $this->d(7), true);

        Payment::factory()->for($held)->create(['provider' => 'bank_transfer', 'status' => Payment::STATUS_PENDING, 'created_at' => now()->subHours(2)]);
        $api = $this->assertConsistent('bank-transfer hold still active', $a, $this->d(5), $this->d(7), true);
        $this->assertSame(1, $api['free_rooms']);

        Booking::query()->delete();
        Booking::factory()->for($a)->cancelled()->create(['check_in' => $this->d(5), 'check_out' => $this->d(7)]);
        Booking::factory()->for($b)->create(['check_in' => $this->d(5), 'check_out' => $this->d(7), 'status' => Booking::STATUS_CHECKED_IN]);
        $api = $this->assertConsistent('cancelled frees, checked-in blocks', $a, $this->d(5), $this->d(7), true);
        $this->assertSame(1, $api['free_rooms']);
    }

    public function test_multiple_rooms_and_room_states(): void
    {
        $a = $this->room();
        $b = $this->room();

        $this->assertConsistent('two rooms, two free', $a, $this->d(5), $this->d(7), true, ['rooms_count' => 2]);

        Booking::factory()->for($b)->confirmed()->create(['check_in' => $this->d(5), 'check_out' => $this->d(7)]);
        $this->assertConsistent('two rooms, one free', $a, $this->d(5), $this->d(7), false, ['rooms_count' => 2]);
        Booking::query()->delete();

        $b->update(['availability_status' => Room::AVAILABILITY_MAINTENANCE]);
        $this->assertConsistent('sibling under maintenance', $a, $this->d(5), $this->d(7), true);
        $this->assertConsistent('room itself under maintenance', $b->fresh(), $this->d(5), $this->d(7), false);

        $a->update(['is_active' => false]);
        $this->assertConsistent('inactive room', $a->fresh(), $this->d(5), $this->d(7), false);
    }
}
