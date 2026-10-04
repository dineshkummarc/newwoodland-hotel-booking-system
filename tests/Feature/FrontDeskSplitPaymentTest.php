<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Client-confirmed payment rules: advance bookings are paid 100% in advance
 * by default, and a guest paying at the front desk partly by QR and partly
 * in cash is recorded by staff with the actual amounts — no gateway needed.
 */
class FrontDeskSplitPaymentTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    public function test_checkout_asks_for_the_full_amount_by_default(): void
    {
        $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING, 'total_price' => 4000]);

        $this->assertSame(4000.0, $booking->amountDueNow());
        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertOk()
            ->assertDontSee('name="payment_plan"', false);
    }

    public function test_staff_record_part_qr_and_part_cash_with_actual_amounts(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED, 'total_price' => 5000]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'hotel_qr', 'amount' => '3000', 'reference' => 'QR-TXN-1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2000.0, $booking->fresh()->outstandingBalance());

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash', 'amount' => '2000',
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame(0.0, $booking->outstandingBalance());
        $this->assertEqualsCanonicalizing(['hotel_qr', 'cash'], $booking->payments()->pluck('provider')->all());
        $this->assertSame(2, $booking->payments()->where('status', Payment::STATUS_COMPLETED)->count());
        $this->assertSame('QR at Hotel (eSewa/Fonepay)', payment_provider_label('hotel_qr'));
    }

    public function test_staff_cannot_record_more_than_is_owed_or_a_zero_amount(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED, 'total_price' => 5000]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash', 'amount' => '5000.01'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no more than the outstanding balance'));
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash', 'amount' => '0'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'paypal', 'amount' => '10'])
            ->assertSessionHasErrors('provider');

        $this->assertSame(0, Payment::count());
    }

    public function test_omitting_the_amount_still_records_the_whole_balance(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING, 'total_price' => 5000]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $booking->fresh()->outstandingBalance());
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }
}
