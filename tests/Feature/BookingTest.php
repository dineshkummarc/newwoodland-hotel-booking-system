<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(Room $room, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9800000000',
            'country' => 'Nepal',
            'special_requests' => 'Late check-in please.',
        ], $overrides);
    }

    public function test_valid_booking_succeeds(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $response = $this->post(route('booking.store'), $this->validPayload($room));

        $booking = Booking::first();
        $this->assertNotNull($booking);
        // Booking is held (pending) and routed to payment — it is not
        // confirmed, and no confirmation email is sent, until payment
        // succeeds. See PaymentTest for the payment-completes-booking flow.
        $response->assertRedirect(route('booking.pay', $booking->confirmation_code));
        $this->assertEquals(3, $booking->nights);
        $this->assertEquals(4500, (float) $booking->total_price);
        $this->assertEquals('pending', $booking->status);
        Mail::assertNothingSent();
    }

    public function test_invalid_booking_fails_validation(): void
    {
        $room = Room::factory()->create();

        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'email' => 'not-an-email',
            'customer_name' => '',
        ]));

        $response->assertSessionHasErrors(['email', 'customer_name']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_past_check_in_is_rejected(): void
    {
        $room = Room::factory()->create();

        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->addDays(1)->toDateString(),
        ]));

        $response->assertSessionHasErrors('check_in');
    }

    public function test_checkout_before_checkin_is_rejected(): void
    {
        $room = Room::factory()->create();

        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]));

        $response->assertSessionHasErrors('check_out');
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Overlaps: existing 10-15, new 12-18
        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(18)->toDateString(),
        ]));

        $response->assertSessionHasErrors('check_in');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_back_to_back_booking_is_allowed(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Existing 10-15, new 15-20: check-out day of one = check-in day of next, must be allowed
        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(15)->toDateString(),
            'check_out' => now()->addDays(20)->toDateString(),
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_cancelled_booking_does_not_block_availability(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $response = $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(14)->toDateString(),
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_price_is_calculated_server_side_and_tampered_price_is_ignored(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 2000]);

        $payload = $this->validPayload($room, [
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);

        // Attempt to inject a client-side price/total — these fields are not
        // in the FormRequest's validated() set and must never reach the model.
        $payload['nightly_rate'] = 1;
        $payload['total_price'] = 1;
        $payload['price'] = 1;

        $this->post(route('booking.store'), $payload);

        $booking = Booking::first();
        $this->assertEquals(2000, (float) $booking->nightly_rate);
        $this->assertEquals(2000, (float) $booking->total_price);
    }

    public function test_confirmation_code_is_unique_and_correctly_formatted(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]));

        $this->post(route('booking.store'), $this->validPayload($room, [
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
        ]));

        $codes = Booking::pluck('confirmation_code');
        $this->assertCount(2, $codes->unique());

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^NW-\d{4}-[A-Z0-9]{6}$/', $code);
        }
    }

    public function test_booking_confirmation_page_loads_after_creation(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        $this->post(route('booking.store'), $this->validPayload($room));
        $booking = Booking::first();

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertOk();
        $response->assertSee($booking->confirmation_code);
    }

    public function test_inactive_room_cannot_be_booked(): void
    {
        $room = Room::factory()->inactive()->create();

        $response = $this->post(route('booking.store'), $this->validPayload($room));

        $response->assertSessionHasErrors('room_id');
    }

    public function test_availability_endpoint_reflects_overlap(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $blocked = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(12)->toDateString().'&check_out='.now()->addDays(13)->toDateString());
        $blocked->assertJson(['available' => false]);

        $free = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(20)->toDateString().'&check_out='.now()->addDays(21)->toDateString());
        $free->assertJson(['available' => true]);
    }
}
