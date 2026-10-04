<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingChargesTest extends TestCase
{
    use RefreshDatabase;

    protected function stay(string $status = Booking::STATUS_CHECKED_IN, array $overrides = []): Booking
    {
        return Booking::factory()->for(Room::factory())->create(array_merge([
            'status' => $status,
            'nightly_rate' => 2000,
            'nights' => 2,
            'total_price' => 4000,
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
        ], $overrides));
    }

    protected function paid(Booking $booking, float $amount): void
    {
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => $amount, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);
    }

    protected function addCharge(Admin $staff, Booking $booking, array $overrides = [])
    {
        return $this->actingAs($staff, 'admin')->post(route('admin.bookings.charges.store', $booking), array_merge([
            'category' => 'restaurant', 'description' => 'Dinner for 2', 'unit_price' => 750, 'quantity' => 2,
        ], $overrides));
    }

    public function test_front_desk_can_add_a_charge_and_the_balance_increases(): void
    {
        $booking = $this->stay();
        $this->paid($booking, 4000);
        $staff = Admin::factory()->frontDesk()->create();

        $this->addCharge($staff, $booking)->assertSessionHas('success');

        $charge = BookingCharge::sole();
        $this->assertSame(1500.0, (float) $charge->amount);
        $this->assertSame($staff->id, $charge->added_by);

        $booking->refresh();
        $this->assertSame(4000.0, (float) $booking->total_price, 'The agreed room price is never changed by charges.');
        $this->assertSame(5500.0, $booking->grandTotal());
        $this->assertSame(1500.0, $booking->outstandingBalance());
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.charge_added', 'target_id' => $booking->id, 'admin_id' => $staff->id]);
    }

    public function test_amount_is_computed_server_side_not_taken_from_input(): void
    {
        $booking = $this->stay();

        $this->addCharge(Admin::factory()->create(), $booking, ['unit_price' => 100, 'quantity' => 3, 'amount' => 1]);

        $this->assertSame(300.0, (float) BookingCharge::sole()->amount);
    }

    public function test_charges_are_refused_before_arrival_and_on_cancelled_or_completed_stays(): void
    {
        $admin = Admin::factory()->create();

        foreach ([Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW] as $status) {
            $this->addCharge($admin, $this->stay($status))->assertSessionHas('error');
        }

        $this->assertDatabaseCount('booking_charges', 0);
    }

    public function test_charge_validation(): void
    {
        $booking = $this->stay();
        $admin = Admin::factory()->create();

        $this->addCharge($admin, $booking, ['category' => 'casino', 'unit_price' => -5, 'quantity' => 0, 'description' => ''])
            ->assertSessionHasErrors(['category', 'unit_price', 'quantity', 'description']);
    }

    public function test_recording_cash_collects_the_charges_with_the_balance(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_OUT);
        $this->paid($booking, 4000);
        $admin = Admin::factory()->frontDesk()->create();
        $this->addCharge($admin, $booking);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash'])
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(5500.0, $booking->amountPaid());
        $this->assertSame(0.0, $booking->outstandingBalance());
    }

    public function test_deposit_and_cancellation_figures_are_still_based_on_the_room_price(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_IN, ['deposit_percent' => 50]);
        $this->addCharge(Admin::factory()->create(), $booking);
        $booking->refresh();

        $this->assertSame(2000.0, $booking->amountDueNow(), 'The deposit is 50% of the room price, not of the folio.');
        $this->assertSame(1000.0, $booking->cancellationCharge(25));
    }

    public function test_only_admins_can_void_a_charge_and_voided_charges_stop_counting(): void
    {
        $booking = $this->stay();
        $this->addCharge(Admin::factory()->create(), $booking);
        $charge = BookingCharge::sole();

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')
            ->patch(route('admin.bookings.charges.void', [$booking, $charge]), ['void_reason' => 'Mistake'])
            ->assertForbidden();
        $this->assertFalse($charge->fresh()->isVoided());

        $admin = Admin::factory()->admin()->create();
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.charges.void', [$booking, $charge]), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.charges.void', [$booking, $charge]), ['void_reason' => 'Entered twice'])
            ->assertSessionHas('success');

        $charge->refresh();
        $this->assertTrue($charge->isVoided());
        $this->assertSame($admin->id, $charge->voided_by);
        $this->assertSame(0.0, $booking->fresh()->chargesTotal());
        // Voided charges are kept, never deleted.
        $this->assertDatabaseHas('booking_charges', ['id' => $charge->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.charge_voided', 'admin_id' => $admin->id]);
    }

    public function test_a_charge_cannot_be_voided_through_another_booking(): void
    {
        $booking = $this->stay();
        $this->addCharge(Admin::factory()->create(), $booking);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->patch(route('admin.bookings.charges.void', [$this->stay(), BookingCharge::sole()]), ['void_reason' => 'x'])
            ->assertNotFound();
    }

    public function test_charges_show_on_the_booking_page_and_in_the_revenue_report(): void
    {
        $booking = $this->stay();
        $admin = Admin::factory()->create();
        $this->addCharge($admin, $booking, ['description' => 'Laundry bag', 'category' => 'laundry', 'unit_price' => 300, 'quantity' => 1]);

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('Laundry bag')->assertSee('Grand Total');

        $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'))
            ->assertOk()->assertSee('Laundry')->assertSee('300');
    }

    public function test_multiple_charges_add_up_and_amounts_are_decimal_safe(): void
    {
        $booking = $this->stay();
        $admin = Admin::factory()->create();

        // 0.1 × 3 is 0.30000000000000004 in floating point; stored exactly as 0.30.
        $this->addCharge($admin, $booking, ['description' => 'Candy', 'unit_price' => '0.10', 'quantity' => 3]);
        $this->addCharge($admin, $booking, ['description' => 'Laundry', 'category' => 'laundry', 'unit_price' => '199.99', 'quantity' => 3]);
        $this->addCharge($admin, $booking, ['description' => 'Water', 'category' => 'minibar', 'unit_price' => '60', 'quantity' => 1]);

        $this->assertSame(['0.30', '599.97', '60.00'], BookingCharge::orderBy('id')->pluck('amount')->map(fn ($a) => (string) $a)->all());
        $this->assertSame(660.27, $booking->fresh()->chargesTotal());
        $this->assertSame(4660.27, $booking->fresh()->grandTotal());
    }

    public function test_admin_can_correct_a_charge_and_the_change_is_audited(): void
    {
        $booking = $this->stay();
        $this->paid($booking, 4000);
        $admin = Admin::factory()->admin()->create();
        $this->addCharge($admin, $booking);
        $charge = BookingCharge::sole();

        $this->actingAs($admin, 'admin')->put(route('admin.bookings.charges.update', [$booking, $charge]), [
            'category' => 'restaurant', 'description' => 'Dinner for 3', 'unit_price' => 750, 'quantity' => 3, 'amount' => 1,
        ])->assertSessionHas('success');

        $charge->refresh();
        $this->assertSame('Dinner for 3', $charge->description);
        $this->assertSame(2250.0, (float) $charge->amount, 'Amount is recomputed, never taken from input.');
        $this->assertSame(2250.0, $booking->fresh()->outstandingBalance());

        $log = \App\Models\AuditLog::firstWhere('action', 'booking.charge_updated');
        $this->assertSame($booking->id, $log->target_id);
        $this->assertSame(2, (int) $log->meta['before']['quantity']);
        $this->assertSame(3, (int) $log->meta['after']['quantity']);
    }

    public function test_front_desk_cannot_correct_charges_and_voided_or_closed_charges_cannot_be_edited(): void
    {
        $booking = $this->stay();
        $this->addCharge(Admin::factory()->create(), $booking);
        $charge = BookingCharge::sole();
        $payload = ['category' => 'restaurant', 'description' => 'Changed', 'unit_price' => 1, 'quantity' => 1];

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')
            ->put(route('admin.bookings.charges.update', [$booking, $charge]), $payload)->assertForbidden();

        $admin = Admin::factory()->admin()->create();
        $this->actingAs($admin, 'admin')->put(route('admin.bookings.charges.update', [$booking, $charge]), ['unit_price' => -1, 'quantity' => 0])
            ->assertSessionHasErrors(['category', 'description', 'unit_price', 'quantity']);

        $charge->update(['voided_at' => now(), 'void_reason' => 'x']);
        $this->actingAs($admin, 'admin')->put(route('admin.bookings.charges.update', [$booking, $charge]), $payload)->assertSessionHas('error');

        $other = $this->stay();
        $this->actingAs($admin, 'admin')->put(route('admin.bookings.charges.update', [$other, $charge]), $payload)->assertNotFound();

        $this->assertSame('Dinner for 2', $charge->fresh()->description);
    }

    public function test_charges_are_owed_on_top_of_the_after_arrival_cancellation_charge(): void
    {
        $booking = $this->stay();
        $this->paid($booking, 4000);
        $this->addCharge(Admin::factory()->create(), $booking); // 1,500

        $booking = app(\App\Services\BookingService::class)->cancelAfterArrival($booking);

        // 4,000 paid − 2,000 (50%) − 1,500 charges = 500 back to the guest.
        $this->assertSame(500.0, $booking->refundDue(0));
        $this->assertSame(3500.0, $booking->grandTotal());
    }
}
