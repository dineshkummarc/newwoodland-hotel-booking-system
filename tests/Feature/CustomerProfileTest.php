<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GuestProfile;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Asha Rai', 'email' => 'asha@example.com', 'password' => 'old-password-1',
        ], $overrides));
    }

    public function test_profile_page_requires_login(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('login'));
        $this->put(route('account.profile.update'), ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect(route('login'));
        $this->put(route('account.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_customer_can_change_their_name_without_a_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha K. Rai', 'email' => 'asha@example.com'])
            ->assertRedirect(route('account.profile'))->assertSessionHas('success');

        $this->assertSame('Asha K. Rai', $user->fresh()->name);
    }

    public function test_changing_email_requires_the_correct_current_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha Rai', 'email' => 'new@example.com'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha Rai', 'email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('asha@example.com', $user->fresh()->email);

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha Rai', 'email' => 'new@example.com', 'current_password' => 'old-password-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_email_must_be_valid_and_not_used_by_another_account(): void
    {
        $this->customer(['email' => 'taken@example.com']);
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email']);
        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha', 'email' => 'taken@example.com', 'current_password' => 'old-password-1'])
            ->assertSessionHasErrors('email');
    }

    public function test_protected_account_fields_cannot_be_changed_from_the_profile_form(): void
    {
        $user = $this->customer(['phone_e164' => '+9779812345678', 'phone_verified_at' => now()->subDay()]);
        $verifiedAt = $user->phone_verified_at->toDateTimeString();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Asha Rai', 'email' => 'asha@example.com',
            'password' => 'hijacked-pass', 'phone_e164' => '+9779800000000', 'phone_verified_at' => null, 'id' => 999,
        ]);

        $fresh = $user->fresh();
        $this->assertSame('+9779812345678', $fresh->phone_e164);
        $this->assertSame($verifiedAt, $fresh->phone_verified_at->toDateTimeString());
        $this->assertTrue(Hash::check('old-password-1', $fresh->password));
    }

    public function test_bookings_keep_their_original_email_and_staff_notes_follow_the_customer(): void
    {
        $user = $this->customer();
        $booking = Booking::factory()->for(Room::factory())->create(['user_id' => $user->id, 'email' => 'asha@example.com']);
        GuestProfile::create(['email' => 'asha@example.com', 'is_vip' => true]);

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Asha Rai', 'email' => 'new@example.com', 'current_password' => 'old-password-1']);

        $this->assertSame('asha@example.com', $booking->fresh()->email);
        $this->assertTrue(GuestProfile::forEmail('new@example.com')->is_vip);
    }

    public function test_customer_can_change_password_with_current_password_and_confirmation(): void
    {
        $user = $this->customer();
        $oldToken = $user->remember_token;

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('account.profile'))->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('brand-new-pass', $fresh->password));
        $this->assertNotSame($oldToken, $fresh->remember_token);
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_after_a_password_change_only_the_new_password_signs_in(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ]);
        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'asha@example.com', 'password' => 'old-password-1'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'asha@example.com', 'password' => 'brand-new-pass'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_password_change_validation(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'different-pass',
        ])->assertSessionHasErrorsIn('password', 'password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrorsIn('password', 'password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'old-password-1', 'password_confirmation' => 'old-password-1',
        ])->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('old-password-1', $user->fresh()->password));
    }

    public function test_password_change_removes_pending_reset_links_and_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->customer();
        $other = $this->customer(['email' => 'other@example.com']);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make('x'), 'created_at' => now()]);
        DB::table('sessions')->insert([
            ['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-else', 'user_id' => $other->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
    }

    public function test_profile_page_shows_current_details(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('account.profile'))
            ->assertOk()->assertSee('asha@example.com')->assertSee('Change password');
    }
}
