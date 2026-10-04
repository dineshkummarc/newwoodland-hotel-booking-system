<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledAdminMail;
use App\Mail\BookingCancelledMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function futureBooking(User $user, array $overrides = []): Booking
    {
        $room = Room::factory()->create();

        return Booking::factory()->for($room)->create(array_merge([
            'user_id' => $user->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    public function test_owner_can_cancel_a_pending_booking(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        Mail::assertQueued(BookingCancelledMail::class);
    }

    public function test_owner_can_cancel_a_confirmed_paid_booking_and_payment_is_untouched(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, ['status' => Booking::STATUS_CONFIRMED]);
        $payment = Payment::factory()->for($booking)->completed()->create(['amount' => $booking->total_price]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertRedirect();
        $booking->refresh();
        $payment->refresh();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        // No refund integration exists — the payment record must stay exactly as it was.
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertEquals($booking->total_price, (string) $payment->amount);
    }

    public function test_non_owner_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $booking = $this->futureBooking($owner);

        $response = $this->actingAs($attacker)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertForbidden();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_guest_booking_with_no_account_cannot_be_cancelled_by_any_user(): void
    {
        $room = Room::factory()->create();
        $booking = Booking::factory()->for($room)->create([
            'user_id' => null,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
        ]);
        $someUser = User::factory()->create();

        $response = $this->actingAs($someUser)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $owner = User::factory()->create();
        $booking = $this->futureBooking($owner);

        $response = $this->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertRedirect(route('login'));
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_already_cancelled_booking_cannot_be_cancelled_again(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Mail::assertNothingOutgoing();
    }

    public function test_booking_can_still_be_cancelled_free_on_the_arrival_day_before_check_in(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, [
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code))
            ->assertSessionHas('success');

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_booking_cannot_be_cancelled_online_once_the_check_in_date_has_passed(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, [
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_booking_after_stay_has_completed_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, [
            'check_in' => now()->subDays(5)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
    }

    public function test_cancelling_a_confirmed_booking_frees_the_room_for_other_guests(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $booking = Booking::factory()->for($room)->create([
            'user_id' => $user->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $blocked = $this->getJson(route('rooms.availability', $room).'?check_in='.now()->addDays(10)->toDateString().'&check_out='.now()->addDays(12)->toDateString());
        $blocked->assertJson(['available' => true]);
    }

    public function test_cancellation_notifies_customer_and_admin(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $user = User::factory()->create();
        $booking = $this->futureBooking($user);

        $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        Mail::assertQueued(BookingCancelledMail::class);
        Mail::assertQueued(BookingCancelledAdminMail::class);
    }

    public function test_configured_cancellation_window_blocks_cancellation_without_enough_notice(): void
    {
        Mail::fake();
        Setting::factory()->create(['cancellation_window_hours' => 48]);
        $user = User::factory()->create();
        // Check-in is ~24h away — less than the required 48h notice.
        $booking = $this->futureBooking($user, [
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_configured_cancellation_window_allows_cancellation_with_enough_notice(): void
    {
        Mail::fake();
        Setting::factory()->create(['cancellation_window_hours' => 1]);
        $user = User::factory()->create();
        // Check-in is ~24h away — comfortably more than the required 1h notice.
        $booking = $this->futureBooking($user, [
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code));

        $response->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_admin_cancelling_a_booking_sets_cancelled_at(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, ['status' => Booking::STATUS_CONFIRMED]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_admin_reversing_a_cancellation_clears_cancelled_at(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $booking = $this->futureBooking($user, [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now()->subHour(),
        ]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.status', $booking), [
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertNull($booking->cancelled_at);
    }
}
