<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function paidBooking(string $status = Booking::STATUS_CANCELLED, float $paid = 4000): array
    {
        $booking = Booking::factory()->for(Room::factory())->create([
            'status' => $status, 'nightly_rate' => 2000, 'nights' => 2, 'total_price' => 4000,
            'cancelled_at' => $status === Booking::STATUS_CANCELLED ? now() : null,
        ]);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id, 'provider' => 'esewa', 'amount' => $paid,
            'status' => Payment::STATUS_COMPLETED, 'paid_at' => now(),
        ]);

        return [$booking, $payment];
    }

    protected function refund(Admin $admin, Booking $booking, array $overrides = [])
    {
        return $this->actingAs($admin, 'admin')->post(route('admin.bookings.refunds.store', $booking), array_merge([
            'amount' => 3000, 'method' => 'esewa', 'reference' => 'ESW-REF-1',
        ], $overrides));
    }

    public function test_admin_can_record_a_pending_refund_and_later_mark_it_paid(): void
    {
        Setting::factory()->create(['cancellation_deduction_percent' => 25]);
        [$booking, $payment] = $this->paidBooking();
        $admin = Admin::factory()->admin()->create();
        $this->assertSame(3000.0, $booking->refundDue(25));

        $this->refund($admin, $booking, ['payment_id' => $payment->id])->assertSessionHas('success');

        $refund = Refund::sole();
        $this->assertSame(Refund::STATUS_PENDING, $refund->status);
        $this->assertSame($admin->id, $refund->recorded_by);
        $this->assertSame($payment->id, $refund->payment_id);
        $this->assertNull($refund->refunded_at);
        $this->assertSame(3000.0, $booking->refundDue(25), 'A pending refund has not been paid out yet.');

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.refunds.complete', [$booking, $refund]), ['reference' => 'ESW-RETURN-9'])
            ->assertSessionHas('success');

        $refund->refresh();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertSame('ESW-RETURN-9', $refund->reference);
        $this->assertSame($admin->id, $refund->processed_by);
        $this->assertNotNull($refund->refunded_at);
        $this->assertSame(0.0, $booking->refundDue(25));
        $this->assertSame(1000.0, $booking->netPaid());
        // The original payment row is never modified.
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame(4000.0, (float) $payment->fresh()->amount);

        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.refund_recorded', 'target_id' => $booking->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.refund_completed', 'target_id' => $booking->id]);
    }

    public function test_a_refund_already_paid_out_can_be_recorded_as_completed(): void
    {
        [$booking] = $this->paidBooking();

        $this->refund(Admin::factory()->create(), $booking, ['amount' => 4000, 'method' => 'cash', 'completed' => '1']);

        $refund = Refund::sole();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertNotNull($refund->refunded_at);
        $this->assertSame(0.0, $booking->refundableAmount());
    }

    public function test_refunds_can_never_exceed_what_was_paid(): void
    {
        [$booking] = $this->paidBooking(Booking::STATUS_CANCELLED, 4000);
        $admin = Admin::factory()->create();

        $this->refund($admin, $booking, ['amount' => 4000.01])->assertSessionHasErrorsIn('refund', 'amount');

        $this->refund($admin, $booking, ['amount' => 2500, 'reference' => 'ESW-REF-A']);
        // The pending 2,500 already counts against the 4,000 paid.
        $this->refund($admin, $booking, ['amount' => 1600, 'reference' => 'ESW-REF-B'])->assertSessionHasErrorsIn('refund', 'amount');
        $this->refund($admin, $booking, ['amount' => 1500, 'reference' => 'ESW-REF-B'])->assertSessionHas('success');

        $this->assertSame(0.0, $booking->refundableAmount());
        $this->assertSame(4000.0, (float) Refund::sum('amount'));
    }

    public function test_cancelled_pending_refunds_free_up_the_refundable_amount(): void
    {
        [$booking] = $this->paidBooking();
        $admin = Admin::factory()->create();
        $this->refund($admin, $booking, ['amount' => 4000]);
        $refund = Refund::sole();

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.refunds.cancel', [$booking, $refund]))->assertSessionHas('success');

        $this->assertSame(Refund::STATUS_CANCELLED, $refund->fresh()->status);
        $this->assertSame(4000.0, $booking->refundableAmount());
        // A finished refund can no longer be changed.
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.refunds.complete', [$booking, $refund]))->assertSessionHas('error');
    }

    public function test_no_refund_on_an_unpaid_booking(): void
    {
        $booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CANCELLED]);

        $this->refund(Admin::factory()->create(), $booking, ['amount' => 100])->assertSessionHasErrorsIn('refund', 'amount');
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_overpayment_refund_brings_the_balance_back_to_zero(): void
    {
        [$booking] = $this->paidBooking(Booking::STATUS_CONFIRMED, 4000);
        $booking->update(['total_price' => 3000]);
        $this->assertSame(-1000.0, $booking->fresh()->outstandingBalance());

        $this->refund(Admin::factory()->create(), $booking, ['amount' => 1000, 'completed' => '1']);

        $this->assertSame(0.0, $booking->fresh()->outstandingBalance());
    }

    public function test_front_desk_cannot_record_or_complete_refunds(): void
    {
        [$booking] = $this->paidBooking();
        $frontDesk = Admin::factory()->frontDesk()->create();

        $this->refund($frontDesk, $booking)->assertForbidden();
        $this->assertDatabaseCount('refunds', 0);

        $refund = Refund::create(['booking_id' => $booking->id, 'amount' => 100, 'method' => 'cash', 'status' => Refund::STATUS_PENDING]);
        $this->actingAs($frontDesk, 'admin')->patch(route('admin.bookings.refunds.complete', [$booking, $refund]))->assertForbidden();
        $this->actingAs($frontDesk, 'admin')->patch(route('admin.bookings.refunds.cancel', [$booking, $refund]))->assertForbidden();
    }

    public function test_a_refund_cannot_be_changed_through_another_booking(): void
    {
        [$booking] = $this->paidBooking();
        [$other] = $this->paidBooking();
        $refund = Refund::create(['booking_id' => $booking->id, 'amount' => 100, 'method' => 'cash', 'status' => Refund::STATUS_PENDING]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->patch(route('admin.bookings.refunds.complete', [$other, $refund]))->assertNotFound();
    }

    public function test_refund_validation_and_payment_must_belong_to_the_booking(): void
    {
        [$booking] = $this->paidBooking();
        [, $otherPayment] = $this->paidBooking();
        $admin = Admin::factory()->create();

        $this->refund($admin, $booking, ['amount' => '', 'method' => 'bitcoin'])->assertSessionHasErrors(['amount', 'method']);
        $this->refund($admin, $booking, ['payment_id' => $otherPayment->id])->assertSessionHasErrorsIn('refund', 'payment_id');
    }

    public function test_refunds_appear_on_the_booking_page_and_reduce_net_revenue(): void
    {
        [$booking] = $this->paidBooking();
        $admin = Admin::factory()->create();
        $this->refund($admin, $booking, ['amount' => 1500, 'completed' => '1', 'reference' => 'BANK-777']);

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('BANK-777')->assertSee('Refunded');

        $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'))
            ->assertOk()
            ->assertViewHas('refundsTotal', 1500.0)
            ->assertViewHas('netRevenue', 2500.0);
    }

    public function test_the_same_refund_reference_cannot_be_recorded_twice_for_a_booking(): void
    {
        [$booking] = $this->paidBooking();
        $admin = Admin::factory()->create();

        $this->refund($admin, $booking, ['amount' => 1000, 'reference' => 'BANK-1'])->assertSessionHas('success');
        $this->refund($admin, $booking, ['amount' => 500, 'reference' => 'bank-1'])->assertSessionHasErrorsIn('refund', 'reference');

        $this->assertSame(1, Refund::count());
    }

    public function test_an_identical_resubmitted_refund_is_refused_but_a_distinct_one_is_allowed(): void
    {
        [$booking] = $this->paidBooking();
        $admin = Admin::factory()->create();
        $payload = ['amount' => 1000, 'method' => 'cash', 'reference' => ''];

        $this->refund($admin, $booking, $payload)->assertSessionHas('success');
        // Double click / resubmitted form.
        $this->refund($admin, $booking, $payload)->assertSessionHasErrorsIn('refund', 'amount');
        $this->assertSame(1, Refund::count());

        // A genuinely separate refund carries its own reference.
        $this->refund($admin, $booking, array_merge($payload, ['reference' => 'RECEIPT-2']))->assertSessionHas('success');
        // …or is recorded later.
        $this->travel(6)->minutes();
        $this->refund($admin, $booking, $payload)->assertSessionHas('success');
        $this->assertSame(3, Refund::count());
    }

    public function test_a_cancelled_refund_does_not_block_re_recording_it(): void
    {
        [$booking] = $this->paidBooking();
        $admin = Admin::factory()->create();
        $this->refund($admin, $booking, ['amount' => 1000, 'reference' => 'BANK-9']);
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.refunds.cancel', [$booking, Refund::sole()]));

        $this->refund($admin, $booking, ['amount' => 1000, 'reference' => 'BANK-9'])->assertSessionHas('success');
    }

    public function test_after_arrival_cancellation_refund_uses_the_fixed_50_percent_and_payments_stay_untouched(): void
    {
        [$booking, $payment] = $this->paidBooking(Booking::STATUS_CHECKED_IN, 4000);
        app(\App\Services\BookingService::class)->cancelAfterArrival($booking);
        $admin = Admin::factory()->create();

        $booking->refresh();
        $this->assertSame(2000.0, $booking->refundDue(0));
        $this->refund($admin, $booking, ['amount' => 2000, 'completed' => '1']);

        $booking->refresh();
        $this->assertSame(50, $booking->cancellation_charge_percent);
        $this->assertSame(0.0, $booking->refundDue(0));
        $this->assertSame(0.0, $booking->outstandingBalance());
        $this->assertSame(4000.0, (float) $payment->fresh()->amount);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
    }

    public function test_refunds_show_on_the_invoice(): void
    {
        [$booking] = $this->paidBooking();
        $this->refund(Admin::factory()->create(), $booking, ['amount' => 3000, 'completed' => '1', 'reference' => 'RF-777']);

        $html = view('pdf.invoice', [
            'booking' => $booking->load(['room', 'additionalRooms', 'payments', 'charges', 'refunds']),
            'hotel' => Setting::first() ?? new Setting, 'invoiceNumber' => 'INV-X', 'issuedAt' => now(),
        ])->render();

        $this->assertStringContainsString('RF-777', $html);
        $this->assertStringContainsString('Refunded', $html);
        $this->assertStringContainsString('NPR 3,000', $html);
    }
}
