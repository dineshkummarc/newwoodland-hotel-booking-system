<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments that need a human (late, duplicate, unverifiable) are visible to
 * staff until someone records how they were resolved.
 */
class PaymentReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function flaggedPayment(): Payment
    {
        $booking = Booking::factory()->cancelled()->create();
        $payment = Payment::factory()->for($booking)->completed()->create();
        $payment->flagForReview('Payment received after the booking was cancelled.');

        return $payment;
    }

    public function test_flagged_payments_are_shown_on_the_dashboard_and_booking_page(): void
    {
        $admin = Admin::factory()->create();
        $payment = $this->flaggedPayment();

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()->assertSee('need')->assertSee($payment->booking->confirmation_code);

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $payment->booking))
            ->assertOk()->assertSee('Needs review')->assertSee('Payment received after the booking was cancelled.');
    }

    public function test_staff_can_resolve_a_flag_with_a_note_and_it_is_audited(): void
    {
        $admin = Admin::factory()->admin()->create();
        $payment = $this->flaggedPayment();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.payments.resolve-review', $payment), ['note' => 'Refunded via eSewa, ref RF-1'])
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertFalse($payment->needsReview());
        $this->assertSame($admin->id, (int) $payment->review_resolved_by);
        $this->assertTrue(AuditLog::where('action', 'payment.review_resolved')->where('admin_id', $admin->id)->exists());

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertDontSee('Payment received after the booking was cancelled.');
    }

    public function test_front_desk_cannot_resolve_money_reviews(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();
        $payment = $this->flaggedPayment();

        $this->actingAs($frontDesk, 'admin')
            ->patch(route('admin.payments.resolve-review', $payment), ['note' => 'done'])
            ->assertForbidden();

        $this->assertTrue($payment->refresh()->needsReview());
    }

    public function test_the_same_situation_is_not_re_flagged_or_re_announced(): void
    {
        $payment = $this->flaggedPayment();

        $this->assertFalse($payment->flagForReview('Payment received after the booking was cancelled.'));
        $this->assertTrue($payment->flagForReview('A different, newer situation.'));
        $this->assertSame('A different, newer situation.', $payment->refresh()->review_reason);
    }
}
