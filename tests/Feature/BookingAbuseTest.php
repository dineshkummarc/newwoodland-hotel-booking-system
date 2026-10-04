<?php

namespace Tests\Feature;

use App\Mail\BankTransferInstructionsMail;
use App\Mail\OfflinePaymentIntentAdminMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * B4 — unpaid holds (especially 24-hour bank-transfer holds) can't be used
 * to tie up the hotel's inventory, while families/groups can still book.
 */
class BookingAbuseTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.methods.bank_transfer.enabled' => true]);
        Setting::query()->delete();
        Setting::factory()->create(['site_email' => 'frontdesk@example.com', 'bank_account_number' => '0123456789']);
    }

    protected function rooms(int $count): Collection
    {
        return collect(range(1, $count))->map(fn () => Room::factory()->create([
            'name' => 'Deluxe Room', 'price_per_night' => 1500, 'max_guests' => 4,
            'availability_status' => Room::AVAILABILITY_AVAILABLE,
        ]));
    }

    protected function book(Room $room, array $overrides = []): TestResponse
    {
        return $this->post(route('booking.store'), array_merge([
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Hold Abuser',
            'email' => 'abuser@example.com',
            'phone' => '9812345678',
        ], $overrides));
    }

    public function test_a_family_or_group_can_book_several_rooms_in_one_booking(): void
    {
        $rooms = $this->rooms(5);

        $this->book($rooms[0], ['rooms_count' => 5])->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
        $this->assertSame(5, Booking::first()->load('additionalRooms')->roomCount());
    }

    public function test_one_guest_cannot_stack_unpaid_holds_beyond_the_room_limit(): void
    {
        $rooms = $this->rooms(9);

        $this->book($rooms[0], ['rooms_count' => 5])->assertSessionHasNoErrors();
        // A second hold with the same email is refused…
        $this->book($rooms[0], ['rooms_count' => 1])->assertSessionHasErrors('email');
        // …and so is switching to a new email but keeping the phone number.
        $this->book($rooms[0], ['email' => 'other@example.com'])->assertSessionHasErrors('email');

        $this->assertSame(1, Booking::count());
    }

    public function test_a_guest_can_book_again_once_their_earlier_hold_is_paid_or_has_expired(): void
    {
        $rooms = $this->rooms(9);
        $this->book($rooms[0], ['rooms_count' => 5])->assertSessionHasNoErrors();
        $first = Booking::firstOrFail();

        // Paid: no longer an unpaid hold.
        Payment::factory()->for($first)->completed()->create(['amount' => 100]);
        $this->book($rooms[0], ['rooms_count' => 2])->assertSessionHasNoErrors();

        // Expired holds don't count either.
        Booking::query()->update(['created_at' => now()->subHour()]);
        $this->book($rooms[0], ['rooms_count' => 4])->assertSessionHasNoErrors();
    }

    public function test_staff_walk_in_bookings_are_not_limited(): void
    {
        $rooms = $this->rooms(9);
        $this->book($rooms[0], ['rooms_count' => 5])->assertSessionHasNoErrors();

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), [
            'room_id' => $rooms[8]->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Hold Abuser',
            'email' => 'abuser@example.com',
            'phone' => '9812345678',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Booking::count());
    }

    // --- bank transfer holds ---------------------------------------------------

    public function test_bank_transfer_sends_the_guest_their_reference_and_tells_staff(): void
    {
        Mail::fake();
        $rooms = $this->rooms(1);
        $this->book($rooms[0]);
        $booking = Booking::firstOrFail();

        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code))
            ->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));

        Mail::assertQueued(BankTransferInstructionsMail::class, function ($mail) use ($booking) {
            $html = $mail->render();

            return $mail->hasTo('abuser@example.com')
                && str_contains($html, $booking->confirmation_code)
                && str_contains($html, '0123456789')
                && str_contains($html, 'signature=');
        });
        Mail::assertQueued(OfflinePaymentIntentAdminMail::class, fn ($mail) => $mail->hasTo('frontdesk@example.com'));

        // Re-selecting it doesn't extend the hold or resend the emails.
        Mail::fake();
        $createdAt = Payment::firstOrFail()->created_at;
        $this->travel(10)->minutes();
        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code));
        $this->assertSame(1, Payment::count());
        $this->assertEquals($createdAt, Payment::firstOrFail()->created_at);
        Mail::assertNothingQueued();
    }

    public function test_an_expired_booking_cannot_be_revived_by_choosing_bank_transfer(): void
    {
        Mail::fake();
        $rooms = $this->rooms(1);
        $this->book($rooms[0]);
        $booking = Booking::firstOrFail();

        $this->travel(31)->minutes();
        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));

        $this->assertSame(0, Payment::count());
        Mail::assertNothingQueued();

        // ...and the room is free for someone else.
        $this->book($rooms[0], ['email' => 'next@example.com', 'phone' => '9811111111'])->assertSessionHasNoErrors();
    }

    public function test_bank_transfer_cannot_be_chosen_for_a_room_another_guest_now_holds(): void
    {
        $rooms = $this->rooms(1);
        $this->book($rooms[0]);
        $booking = Booking::firstOrFail();
        $booking->forceFill(['created_at' => now()->subMinutes(31)])->save();
        // Someone else books the freed room.
        $this->book($rooms[0], ['email' => 'next@example.com', 'phone' => '9811111111'])->assertSessionHasNoErrors();
        $booking->forceFill(['created_at' => now()->subMinutes(29)])->save(); // even if the clock says "still held"

        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::where('booking_id', $booking->id)->count());
    }

    public function test_expired_bank_transfer_holds_release_the_room(): void
    {
        Mail::fake();
        $rooms = $this->rooms(1);
        $this->book($rooms[0]);
        $booking = Booking::firstOrFail();
        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code));

        $this->travel(23)->hours();
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);

        $this->travel(2)->hours();
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);

        $this->book($rooms[0], ['email' => 'next@example.com', 'phone' => '9811111111'])->assertSessionHasNoErrors();
    }

    public function test_offline_holds_are_rate_limited_per_ip(): void
    {
        Mail::fake();
        $rooms = $this->rooms(1);
        $this->book($rooms[0]);
        $booking = Booking::firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code));
        }

        $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code))->assertStatus(429);
    }
}
