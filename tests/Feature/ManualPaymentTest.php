<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create(array_merge([
            'nightly_rate' => 1500,
            'total_price' => 3000,
            'nights' => 2,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    public function test_admin_can_record_a_cash_payment_and_booking_confirms(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
            'reference' => 'Receipt #42',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);

        $payment = Payment::first();
        $this->assertEquals('cash', $payment->provider);
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertEquals(3000, (float) $payment->amount);
        $this->assertEquals('Receipt #42', $payment->reference_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_front_desk_can_record_a_cash_payment(): void
    {
        $admin = Admin::factory()->frontDesk()->create();
        $booking = $this->pendingBooking();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_amount_is_always_the_bookings_total_price_not_client_supplied(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['total_price' => 3000]);

        // Client-confirmed (2026-10-04): front-desk staff record the ACTUAL
        // amount received in person (e.g. part QR, part cash) — but never
        // more than the server-calculated balance, and with no amount given
        // the whole balance is recorded.
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
            'amount' => 3000.01,
        ])->assertSessionHas('error');
        $this->assertSame(0, Payment::count());

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash']);
        $this->assertEquals(3000, (float) Payment::first()->amount);
    }

    public function test_already_paid_booking_cannot_be_recorded_as_paid_again(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['amount' => $booking->total_price]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, Payment::count());
    }

    public function test_cancelled_booking_cannot_be_recorded_as_paid(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, Payment::count());
    }

    public function test_manual_cash_payment_confirms_via_the_same_availability_recheck_as_online_payments(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        $booking = $this->pendingBooking([
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
        ]);
        // Someone else already confirmed the same room/dates in the meantime.
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
        ]);

        // Payment is recorded (money was "collected"), but the booking
        // cannot double-book the room — same rule as an online gateway.
        $this->assertEquals(1, Payment::count());
        $this->assertEquals(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_cash_payment_appears_in_revenue_report(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['total_price' => 2500]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'cash',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'));

        $this->assertEquals(2500, (float) $response->viewData('totalRevenue'));
    }

    public function test_guest_cannot_record_a_payment(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash']);

        $response->assertRedirect(route('admin.login'));
        $this->assertEquals(0, Payment::count());
    }
}
