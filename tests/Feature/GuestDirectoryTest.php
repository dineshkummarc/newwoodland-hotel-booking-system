<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_guest_directory(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.guests.index'));

        $response->assertOk();
    }

    public function test_registered_customer_appears_with_booking_stats(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => 'Alice Registered']);
        $room = Room::factory()->create(['price_per_night' => 1000]);
        Booking::factory()->for($room)->create([
            'user_id' => $user->id,
            'status' => Booking::STATUS_CONFIRMED,
            'total_price' => 2000,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.guests.index'));

        $response->assertOk();
        $response->assertSee('Alice Registered');
        $response->assertSee('NPR 2,000');
    }

    public function test_guest_without_account_appears_grouped_by_email_not_as_a_fake_user(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['price_per_night' => 1500]);
        Booking::factory()->for($room)->count(2)->create([
            'user_id' => null,
            'email' => 'walkin@example.com',
            'customer_name' => 'Walkin Guest',
            'status' => Booking::STATUS_CONFIRMED,
            'total_price' => 1500,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.guests.index'));

        $response->assertOk();
        $response->assertSee('Walkin Guest');
        $response->assertSee('walkin@example.com');

        // No User record was created for this guest.
        $this->assertDatabaseMissing('users', ['email' => 'walkin@example.com']);
    }

    public function test_cancelled_bookings_are_excluded_from_total_amount(): void
    {
        $admin = Admin::factory()->create();
        $room = Room::factory()->create(['price_per_night' => 1000]);
        Booking::factory()->for($room)->create([
            'user_id' => null,
            'email' => 'mixed@example.com',
            'customer_name' => 'Mixed Guest',
            'status' => Booking::STATUS_CONFIRMED,
            'total_price' => 1000,
        ]);
        Booking::factory()->for($room)->create([
            'user_id' => null,
            'email' => 'mixed@example.com',
            'customer_name' => 'Mixed Guest',
            'status' => Booking::STATUS_CANCELLED,
            'total_price' => 5000,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.guests.index'));

        $response->assertOk();
        $response->assertSee('NPR 1,000');
        $response->assertDontSee('NPR 6,000');
    }

    public function test_registered_search_filters_by_name_or_email(): void
    {
        $admin = Admin::factory()->create();
        User::factory()->create(['name' => 'Findme Person', 'email' => 'findme@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.com']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.guests.index', ['registered_search' => 'Findme']));

        $response->assertSee('Findme Person');
        $response->assertDontSee('Someone Else');
    }

    public function test_guest_cannot_access_guest_directory(): void
    {
        $response = $this->get(route('admin.guests.index'));

        $response->assertRedirect(route('admin.login'));
    }
}
