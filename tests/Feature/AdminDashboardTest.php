<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_real_todays_arrivals_count(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
        ]);
        // Not arriving today — should not be counted.
        Booking::factory()->for($room)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('todaysArrivals', 1);
    }

    public function test_dashboard_occupancy_reflects_checked_in_bookings(): void
    {
        $admin = Admin::factory()->create();
        $room1 = Room::factory()->create(['is_active' => true]);
        $room2 = Room::factory()->create(['is_active' => true]);

        Booking::factory()->for($room1)->create(['status' => Booking::STATUS_CHECKED_IN]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertViewHas('occupiedRooms', 1);
        $response->assertViewHas('availableRooms', 1);
        $response->assertViewHas('occupancyPercentage', 50.0);
    }

    public function test_dashboard_excludes_maintenance_rooms_from_available_count(): void
    {
        $admin = Admin::factory()->create();
        Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        Room::factory()->create(['is_active' => true, 'availability_status' => Room::AVAILABILITY_MAINTENANCE]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertViewHas('maintenanceRooms', 1);
        $response->assertViewHas('availableRooms', 1);
    }

    public function test_dashboard_revenue_comes_from_completed_payments_not_booking_totals(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $booking = Booking::factory()->for($room)->create(['status' => Booking::STATUS_CONFIRMED, 'total_price' => 9999]);
        Payment::factory()->for($booking)->completed()->create(['amount' => 3000, 'paid_at' => now()]);

        // A pending payment must never count as revenue.
        $pendingBooking = Booking::factory()->for($room)->create();
        Payment::factory()->for($pendingBooking)->create(['amount' => 5000]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertEquals(3000, (float) $response->viewData('monthlyRevenue'));
    }

    public function test_dashboard_revenue_by_provider_is_grouped_correctly(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $b1 = Booking::factory()->for($room)->create();
        Payment::factory()->for($b1)->completed()->create(['provider' => 'esewa', 'amount' => 1000, 'paid_at' => now()]);

        $b2 = Booking::factory()->for($room)->create();
        Payment::factory()->for($b2)->completed()->create(['provider' => 'khalti', 'amount' => 2000, 'paid_at' => now()]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $revenueByProvider = $response->viewData('revenueByProvider');
        $this->assertEquals(1000, (float) $revenueByProvider['esewa']);
        $this->assertEquals(2000, (float) $revenueByProvider['khalti']);
    }

    public function test_front_desk_role_can_view_dashboard(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
    }
}
