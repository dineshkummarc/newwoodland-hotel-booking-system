<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtering_by_payment_status_completed(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $paid = Booking::factory()->for($room)->create(['customer_name' => 'Paid Guest']);
        Payment::factory()->for($paid)->completed()->create();

        $unpaid = Booking::factory()->for($room)->create(['customer_name' => 'Unpaid Guest']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.index', ['payment_status' => 'completed']));

        $response->assertSee('Paid Guest');
        $response->assertDontSee('Unpaid Guest');
    }

    public function test_filtering_by_payment_status_none(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        $noAttempt = Booking::factory()->for($room)->create(['customer_name' => 'No Attempt Guest']);
        $attempted = Booking::factory()->for($room)->create(['customer_name' => 'Attempted Guest']);
        Payment::factory()->for($attempted)->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.index', ['payment_status' => 'none']));

        $response->assertSee('No Attempt Guest');
        $response->assertDontSee('Attempted Guest');
    }

    public function test_filtering_by_room(): void
    {
        $admin = Admin::factory()->create();
        $roomA = Room::factory()->create(['name' => 'Room A Unique']);
        $roomB = Room::factory()->create(['name' => 'Room B Unique']);

        Booking::factory()->for($roomA)->create(['customer_name' => 'Guest In A']);
        Booking::factory()->for($roomB)->create(['customer_name' => 'Guest In B']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.index', ['room_id' => $roomA->id]));

        $response->assertSee('Guest In A');
        $response->assertDontSee('Guest In B');
    }

    public function test_filtering_by_status_still_works(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create(['status' => Booking::STATUS_PENDING, 'customer_name' => 'Pending Guest']);
        Booking::factory()->for($room)->create(['status' => Booking::STATUS_CONFIRMED, 'customer_name' => 'Confirmed Guest']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.index', ['status' => 'pending']));

        $response->assertSee('Pending Guest');
        $response->assertDontSee('Confirmed Guest');
    }

    public function test_csv_export_still_works_alongside_new_filters(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create(['customer_name' => 'Export Guest']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
