<?php

namespace Tests\Feature;

use App\Mail\BookingAccessLinkMail;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * B1 — the booking code is a reference, never a credential. Deliberately
 * does NOT use ActsAsBookingGuest: every request here starts with no
 * access unless the test grants it the way the real app does.
 */
class BookingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function guestBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 2000]);

        return Booking::factory()->for($room)->create(array_merge([
            'customer_name' => 'Sita Sharma',
            'email' => 'sita@example.com',
            'phone' => '+9779812345678',
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'nights' => 2,
            'total_price' => 4000,
        ], $overrides));
    }

    // --- the code alone gets nothing --------------------------------------

    public function test_booking_code_alone_does_not_show_any_guest_information(): void
    {
        $booking = $this->guestBooking();
        Payment::factory()->for($booking)->completed()->create(['reference_id' => 'REF-SECRET-1']);

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertForbidden();
        $response->assertDontSee('Sita Sharma');
        $response->assertDontSee('sita@example.com');
        $response->assertDontSee('9812345678');
        $response->assertDontSee('REF-SECRET-1');
        $response->assertDontSee('/cancel', false);
    }

    public function test_unknown_and_real_codes_get_an_identical_response(): void
    {
        $booking = $this->guestBooking();

        $real = $this->get(route('booking.confirmation', $booking->confirmation_code));
        $fake = $this->get(route('booking.confirmation', 'NW-2026-ZZZZZZ'));

        $real->assertForbidden();
        $fake->assertForbidden();

        // Identical apart from the CSRF token and the requested URL echoed in
        // the canonical/og:url tags.
        $normalise = fn (string $html, string $code) => str_replace($code, 'CODE', preg_replace('/name="_token" value="[^"]+"/', '', $html));
        $this->assertSame(
            $normalise($real->getContent(), $booking->confirmation_code),
            $normalise($fake->getContent(), 'NW-2026-ZZZZZZ'),
        );
    }

    public function test_booking_code_alone_cannot_open_payment_pages_or_start_a_payment(): void
    {
        $booking = $this->guestBooking(['status' => Booking::STATUS_PENDING]);

        $this->get(route('booking.pay', $booking->confirmation_code))->assertForbidden()->assertDontSee('Sita Sharma');
        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'bank_transfer'])->assertForbidden();
        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code))->assertForbidden();
        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($booking->refresh()->deposit_percent);
    }

    public function test_booking_code_alone_cannot_cancel(): void
    {
        $booking = $this->guestBooking();

        // The cancel routes require a signed, purpose-specific link.
        $this->get('/booking/'.$booking->confirmation_code.'/cancel')->assertForbidden();
        $this->post('/booking/'.$booking->confirmation_code.'/cancel')->assertForbidden();

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_a_view_link_cannot_be_reused_as_a_cancel_link(): void
    {
        $booking = $this->guestBooking();
        $viewQuery = parse_url($booking->viewUrl(), PHP_URL_QUERY);

        $this->post('/booking/'.$booking->confirmation_code.'/cancel?'.$viewQuery)->assertForbidden();

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_a_signed_link_for_one_booking_does_not_open_another(): void
    {
        $mine = $this->guestBooking();
        $theirs = $this->guestBooking(['customer_name' => 'Ram Thapa', 'email' => 'ram@example.com']);
        $myQuery = parse_url($mine->viewUrl(), PHP_URL_QUERY);

        $this->get(route('booking.confirmation', $theirs->confirmation_code).'?'.$myQuery)
            ->assertForbidden()
            ->assertDontSee('Ram Thapa');
    }

    public function test_expired_or_tampered_signed_links_fail(): void
    {
        $booking = $this->guestBooking();

        $expired = URL::temporarySignedRoute('booking.confirmation', now()->subMinute(), ['booking' => $booking->confirmation_code]);
        $this->get($expired)->assertForbidden()->assertDontSee('Sita Sharma');

        $tampered = str_replace('signature=', 'signature=0', $booking->viewUrl());
        $this->get($tampered)->assertForbidden()->assertDontSee('Sita Sharma');

        $expiredCancel = URL::temporarySignedRoute('booking.cancel.guest', now()->subMinute(), ['booking' => $booking->confirmation_code]);
        $this->post($expiredCancel)->assertForbidden();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    // --- legitimate guests keep working -------------------------------------

    public function test_the_signed_link_from_the_guests_email_shows_the_booking(): void
    {
        $booking = $this->guestBooking();

        $this->get($booking->viewUrl())
            ->assertOk()
            ->assertSee('Sita Sharma')
            ->assertSee($booking->confirmation_code);

        // ...and the session it opened can now pay / navigate without the signature.
        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertOk();
    }

    public function test_the_browser_that_made_the_booking_can_see_and_pay_for_it(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9812345678',
        ]);

        $booking = Booking::firstOrFail();
        $this->get(route('booking.pay', $booking->confirmation_code))->assertOk()->assertSee('Jane Doe');
        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertOk();
    }

    public function test_a_signed_cancel_link_still_cancels_a_guest_booking(): void
    {
        Mail::fake();
        $booking = $this->guestBooking(['user_id' => null]);

        $this->post(URL::temporarySignedRoute('booking.cancel.guest.store', now()->addDay(), ['booking' => $booking->confirmation_code]))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        // The link holder is the guest: the confirmation page now opens for them.
        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertOk();
    }

    public function test_cancel_links_never_work_for_account_bookings(): void
    {
        $owner = User::factory()->create();
        $booking = $this->guestBooking(['user_id' => $owner->id]);

        $this->post(URL::temporarySignedRoute('booking.cancel.guest.store', now()->addDay(), ['booking' => $booking->confirmation_code]))
            ->assertForbidden();

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    // --- accounts / IDOR ------------------------------------------------------

    public function test_the_account_owner_can_view_their_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->guestBooking(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('booking.confirmation', $booking->confirmation_code))->assertOk()->assertSee('Sita Sharma');
    }

    public function test_another_customer_cannot_view_pay_cancel_or_download_someone_elses_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->guestBooking(['user_id' => $owner->id, 'status' => Booking::STATUS_PENDING]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder);
        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertForbidden()->assertDontSee('Sita Sharma');
        $this->get(route('booking.pay', $booking->confirmation_code))->assertForbidden();
        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'bank_transfer'])->assertForbidden();
        $this->post(route('account.bookings.cancel', $booking->confirmation_code))->assertForbidden();
        $this->get(route('account.bookings.invoice', $booking->confirmation_code))->assertNotFound();

        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_session_grant_for_one_booking_does_not_open_another(): void
    {
        $mine = $this->guestBooking();
        $theirs = $this->guestBooking(['customer_name' => 'Ram Thapa']);

        $this->withSession([BookingAccess::SESSION_KEY => [$mine->id]]);

        $this->get(route('booking.confirmation', $mine->confirmation_code))->assertOk();
        $this->get(route('booking.confirmation', $theirs->confirmation_code))->assertForbidden()->assertDontSee('Ram Thapa');
    }

    // --- "email me my link" -----------------------------------------------

    public function test_access_link_is_emailed_only_to_the_address_on_the_booking(): void
    {
        Mail::fake();
        $booking = $this->guestBooking();

        $this->post(route('booking.access-link'), ['confirmation_code' => strtolower($booking->confirmation_code), 'email' => 'SITA@example.com'])
            ->assertSessionHas('success');

        Mail::assertQueued(BookingAccessLinkMail::class, fn ($mail) => $mail->hasTo('sita@example.com') && $mail->booking->is($booking));
    }

    public function test_access_link_request_reveals_nothing_for_wrong_details(): void
    {
        Mail::fake();
        $booking = $this->guestBooking();

        $wrongEmail = $this->post(route('booking.access-link'), ['confirmation_code' => $booking->confirmation_code, 'email' => 'attacker@example.com']);
        $unknownCode = $this->post(route('booking.access-link'), ['confirmation_code' => 'NW-2026-NOPE00', 'email' => 'sita@example.com']);

        $this->assertSame($wrongEmail->getSession()->get('success'), $unknownCode->getSession()->get('success'));
        Mail::assertNothingQueued();
    }

    public function test_access_link_requests_are_rate_limited(): void
    {
        Mail::fake();
        $booking = $this->guestBooking();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('booking.access-link'), ['confirmation_code' => $booking->confirmation_code, 'email' => 'x'.$i.'@example.com']);
        }

        $this->post(route('booking.access-link'), ['confirmation_code' => $booking->confirmation_code, 'email' => 'sita@example.com'])->assertStatus(429);

        // And a single booking's guest is never sent more than 3 links an hour.
        Mail::fake();
        $this->travel(2)->minutes();
        for ($i = 0; $i < 4; $i++) {
            $this->travel(1)->minutes();
            $this->post(route('booking.access-link'), ['confirmation_code' => $booking->confirmation_code, 'email' => 'sita@example.com']);
        }
        Mail::assertQueued(BookingAccessLinkMail::class, 3);
    }

    public function test_confirmation_page_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get(route('booking.confirmation', 'NW-2026-AAAA'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
        }

        $this->get(route('booking.confirmation', 'NW-2026-ZZZZZZ'))->assertStatus(429);
    }

    public function test_confirmation_emails_carry_a_signed_view_link_not_the_bare_code_url(): void
    {
        $booking = $this->guestBooking();

        $html = (new BookingConfirmationMail($booking))->render();

        $this->assertStringContainsString('signature=', $html);
        $this->assertStringNotContainsString('href="'.route('booking.confirmation', $booking->confirmation_code).'"', $html);
    }
}
