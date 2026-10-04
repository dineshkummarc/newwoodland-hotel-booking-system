<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledAdminMail;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

class GuestSelfCancellationTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function guestBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create();

        return Booking::factory()->for($room)->create(array_merge([
            'user_id' => null,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ], $overrides));
    }

    public function test_valid_signed_link_shows_the_confirmation_page(): void
    {
        $booking = $this->guestBooking();

        $response = $this->get($booking->guestCancellationUrl());

        $response->assertOk();
        $response->assertSee($booking->confirmation_code);
    }

    public function test_valid_signed_link_cancels_the_booking(): void
    {
        Mail::fake();
        $booking = $this->guestBooking();
        $url = $booking->guestCancellationUrl();

        $response = $this->post($url);

        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        Mail::assertQueued(BookingCancelledMail::class);
    }

    public function test_cancellation_notifies_admin_too(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $booking = $this->guestBooking();

        $this->post($booking->guestCancellationUrl());

        Mail::assertQueued(BookingCancelledAdminMail::class);
    }

    public function test_link_without_a_valid_signature_is_rejected(): void
    {
        $booking = $this->guestBooking();
        $url = $booking->guestCancellationUrl();
        $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=0000000000000000000000000000000000000000000000000000000000000000', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_expired_link_is_rejected(): void
    {
        $booking = $this->guestBooking();

        $expiredUrl = URL::temporarySignedRoute(
            'booking.cancel.guest',
            now()->subMinute(),
            ['booking' => $booking->confirmation_code]
        );

        $response = $this->get($expiredUrl);

        $response->assertForbidden();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_already_cancelled_booking_cannot_be_cancelled_again_via_guest_link(): void
    {
        Mail::fake();
        $booking = $this->guestBooking([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->post($booking->guestCancellationUrl());

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Mail::assertNothingOutgoing();
    }

    public function test_cancellation_window_is_respected_via_guest_link(): void
    {
        Mail::fake();
        Setting::factory()->create(['cancellation_window_hours' => 48]);
        // Check-in ~24h away — less than the required 48h notice.
        $booking = $this->guestBooking([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->post($booking->guestCancellationUrl());

        $response->assertSessionHas('error');
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_guest_cancellation_link_is_not_offered_for_an_account_booking(): void
    {
        $room = Room::factory()->create();
        $user = \App\Models\User::factory()->create();
        $booking = Booking::factory()->for($room)->create([
            'user_id' => $user->id,
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
        ]);

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertDontSee('Need to cancel this booking?');
    }

    public function test_guest_cancellation_link_is_offered_on_the_confirmation_page_for_a_guest_booking(): void
    {
        $booking = $this->guestBooking();

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertSee('Need to cancel this booking?');
    }

    public function test_cancelling_frees_the_room_for_other_guests(): void
    {
        Mail::fake();
        $room = Room::factory()->create();
        $booking = $this->guestBooking(['room_id' => $room->id]);

        $this->post($booking->guestCancellationUrl());

        $blocked = $this->getJson(route('rooms.availability', $room).'?check_in='.$booking->check_in->toDateString().'&check_out='.$booking->check_out->toDateString());
        $blocked->assertJson(['available' => true]);
    }
}
