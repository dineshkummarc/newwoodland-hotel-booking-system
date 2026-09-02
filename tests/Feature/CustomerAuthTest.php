<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9800000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('account.bookings'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_valid_login_succeeds(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('account.bookings'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_fails(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_cannot_access_bookings_page(): void
    {
        $response = $this->get(route('account.bookings'));

        $response->assertRedirect(route('login'));
    }

    public function test_logout_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_logged_in_user_only_sees_own_bookings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $room = Room::factory()->create();

        $ownBooking = Booking::factory()->create(['user_id' => $user->id, 'room_id' => $room->id]);
        Booking::factory()->create(['user_id' => $otherUser->id, 'room_id' => $room->id]);

        $response = $this->actingAs($user)->get(route('account.bookings'));

        $response->assertOk();
        $response->assertSee($ownBooking->confirmation_code);
        $response->assertDontSee(Booking::where('user_id', $otherUser->id)->first()->confirmation_code);
    }

    public function test_booking_made_while_logged_in_is_linked_to_the_account(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $this->actingAs($user)->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => '9800000000',
        ]);

        $booking = Booking::first();
        $this->assertEquals($user->id, $booking->user_id);
    }

    public function test_guest_checkout_still_works_without_an_account(): void
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Guest Only',
            'email' => 'guest@example.com',
            'phone' => '9800000000',
        ]);

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertNull($booking->user_id);
    }
}
