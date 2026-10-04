<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_loads(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.index'));

        $response->assertOk();
    }

    public function test_booking_report_filters_by_date_range_and_status(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'customer_name' => 'In Range Guest',
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
        ]);
        Booking::factory()->for($room)->create([
            'customer_name' => 'Out Of Range Guest',
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(60)->toDateString(),
            'check_out' => now()->addDays(61)->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.bookings', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(10)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('In Range Guest');
        $response->assertDontSee('Out Of Range Guest');
    }

    public function test_booking_report_includes_a_stay_that_started_before_the_range_but_is_still_ongoing(): void
    {
        // Regression test: a booking that checked in 3 days before the
        // report window opens, but is still ongoing on day 1 of the
        // window, must still be counted — whereBetween('check_in', ...)
        // alone used to miss this entirely.
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'customer_name' => 'Spanning Guest',
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.bookings', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Spanning Guest');
    }

    public function test_room_performance_includes_a_stay_that_started_before_the_range_but_is_still_ongoing(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['name' => 'Spanning Room']);

        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'nights' => 5,
            'total_price' => 5000,
            'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.room-performance', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(5)->toDateString(),
        ]));

        $rooms = $response->viewData('rooms');
        $spanning = $rooms->firstWhere('name', 'Spanning Room');

        $this->assertEquals(1, $spanning->bookings_count);
        $this->assertEquals(5000, (float) $spanning->revenue);
    }

    public function test_booking_report_csv_export_works(): void
    {
        $admin = Admin::factory()->create();
        Booking::factory()->for(Room::factory())->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.bookings.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_revenue_report_excludes_cancelled_bookings_and_uncompleted_payments(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $paid = Booking::factory()->for($room)->create(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($paid)->completed()->create(['amount' => 4000, 'paid_at' => now()]);

        $unpaid = Booking::factory()->for($room)->create(['status' => Booking::STATUS_PENDING]);
        Payment::factory()->for($unpaid)->create(['amount' => 9000]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'));

        $response->assertOk();
        $this->assertEquals(4000, (float) $response->viewData('totalRevenue'));
    }

    public function test_revenue_report_breaks_down_by_provider(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $b1 = Booking::factory()->for($room)->create();
        Payment::factory()->for($b1)->completed()->create(['provider' => 'esewa', 'amount' => 1500, 'paid_at' => now()]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'));

        $byProvider = $response->viewData('byProvider');
        $this->assertEquals(1500, (float) $byProvider['esewa']['total']);
        $this->assertEquals(1, $byProvider['esewa']['count']);
    }

    public function test_occupancy_report_computes_percentage_from_real_bookings(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);

        // One night booked, inside a 2-day report window -> 1 of 2 room-nights occupied = 50%.
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.occupancy', [
            'from' => now()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertEquals(50.0, $response->viewData('occupancyPercentage'));
    }

    public function test_room_performance_report_shows_bookings_nights_and_revenue_per_room(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['name' => 'Performance Room']);

        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'nights' => 3,
            'total_price' => 4500,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.room-performance', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Performance Room');
    }

    public function test_payment_report_filters_by_provider_and_status(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $b1 = Booking::factory()->for($room)->create();
        Payment::factory()->for($b1)->completed()->create(['provider' => 'esewa']);
        $b2 = Booking::factory()->for($room)->create();
        Payment::factory()->for($b2)->failed()->create(['provider' => 'khalti']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.payments', ['provider' => 'esewa']));

        $response->assertOk();
        $response->assertSee($b1->confirmation_code);
        $response->assertDontSee($b2->confirmation_code);
    }

    public function test_payment_report_csv_export_works(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->for(Room::factory())->create();
        Payment::factory()->for($booking)->completed()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.payments.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_front_desk_role_cannot_access_reports(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.index'));

        $response->assertForbidden();
    }
}
