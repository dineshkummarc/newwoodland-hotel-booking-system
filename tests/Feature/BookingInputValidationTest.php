<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Phase 7 (phone numbers), Phase 12 (date limits) and Phase 11
 * (first-booking discount) on the public booking form.
 */
class BookingInputValidationTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function room(): Room
    {
        return Room::factory()->create(['price_per_night' => 2000, 'max_guests' => 4, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
    }

    protected function book(array $overrides = []): TestResponse
    {
        return $this->post(route('booking.store'), array_merge([
            'room_id' => $this->room()->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Guest',
            'email' => 'guest@example.com',
            'phone' => '9812345678',
        ], $overrides));
    }

    // --- phone numbers ---------------------------------------------------------

    public function test_a_nepali_mobile_typed_locally_is_stored_in_international_form(): void
    {
        $this->book(['phone' => '981-234 5678'])->assertSessionHasNoErrors();

        $this->assertSame('+9779812345678', Booking::firstOrFail()->phone);
    }

    public function test_a_nepali_number_with_country_code_is_accepted(): void
    {
        $this->book(['phone' => '+977 9812345678'])->assertSessionHasNoErrors();

        $this->assertSame('+9779812345678', Booking::firstOrFail()->phone);
    }

    public function test_an_indian_guest_can_enter_their_own_number_with_the_country_picker(): void
    {
        $this->book(['phone_country' => 'IN', 'phone' => '98765 43210'])->assertSessionHasNoErrors();

        $this->assertSame('+919876543210', Booking::firstOrFail()->phone);
    }

    public function test_international_numbers_in_full_format_are_accepted_whatever_the_picker_says(): void
    {
        $this->book(['phone' => '+44 7911 123456'])->assertSessionHasNoErrors();
        $this->assertSame('+447911123456', Booking::latest('id')->firstOrFail()->phone);

        $this->book(['phone' => '+1 (415) 555-2671', 'email' => 'us@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('+14155552671', Booking::latest('id')->firstOrFail()->phone);
    }

    public function test_landlines_are_accepted_for_contact(): void
    {
        // A hotel can call a landline; booking does not need SMS.
        $this->book(['phone' => '023 455 123', 'email' => 'landline@example.com'])->assertSessionHasNoErrors();
    }

    public function test_invalid_phone_numbers_are_rejected(): void
    {
        $this->book(['phone' => '12345'])->assertSessionHasErrors('phone');
        $this->book(['phone' => 'call me maybe'])->assertSessionHasErrors('phone');
        $this->book(['phone' => '+977 12'])->assertSessionHasErrors('phone');
        $this->book(['phone_country' => 'XX', 'phone' => '9812345678'])->assertSessionHasErrors('phone_country');

        $this->assertSame(0, Booking::count());
    }

    // --- date limits -----------------------------------------------------------

    public function test_stays_beyond_the_technical_limits_are_refused_with_a_clear_message(): void
    {
        $this->book([
            'check_in' => now()->addDays(800)->toDateString(),
            'check_out' => now()->addDays(802)->toDateString(),
        ])->assertSessionHasErrors('check_in');

        $this->book([
            'check_out' => now()->addDays(5 + 91)->toDateString(),
        ])->assertSessionHasErrors('check_out');

        // A stay of years would overflow the price column on MySQL — refused cleanly instead.
        $this->book(['check_out' => '2999-01-01'])->assertSessionHasErrors('check_out');

        $this->assertSame(0, Booking::count());
    }

    public function test_the_limits_are_configurable_and_normal_stays_pass(): void
    {
        config(['bookings.max_nights' => 120]);

        $this->book(['check_out' => now()->addDays(5 + 100)->toDateString()])->assertSessionHasNoErrors();
    }

    // --- first-booking discount --------------------------------------------------

    public function test_the_first_booking_discount_is_not_given_again_for_the_same_phone_with_a_new_email(): void
    {
        Setting::query()->delete();
        Setting::factory()->create(['first_booking_discount_percent' => 10]);

        $this->book(['email' => 'first@example.com'])->assertSessionHasNoErrors();
        $first = Booking::firstOrFail();
        $this->assertEquals(10, (float) $first->discount_percent);
        Payment::factory()->for($first)->completed()->create(['amount' => $first->total_price]);

        $this->book(['email' => 'throwaway@example.com', 'phone' => '+977 981 234 5678'])->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) Booking::latest('id')->firstOrFail()->discount_percent);
    }

    public function test_a_genuinely_new_guest_still_gets_the_discount(): void
    {
        Setting::query()->delete();
        Setting::factory()->create(['first_booking_discount_percent' => 10]);
        $previous = Booking::factory()->create(['email' => 'someone@example.com', 'phone' => '+9779800000001', 'status' => Booking::STATUS_CONFIRMED]);

        $this->book(['email' => 'new@example.com', 'phone' => '9812345678'])->assertSessionHasNoErrors();

        $this->assertEquals(10, (float) Booking::where('email', 'new@example.com')->firstOrFail()->discount_percent);
    }
}
