<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * AuthenticateSession (web middleware group): a password change or reset
 * logs the account out on every other device, while the device that made
 * the change stays signed in. Each "device" is a separate session, signed
 * in through the real login routes.
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Start a fresh, signed-out device (empty session, no cached user). */
    private function newDevice(): void
    {
        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();
    }

    /** @return array<string, mixed> the current device's session */
    private function currentDevice(): array
    {
        return $this->app['session']->all();
    }

    private function switchTo(array $device): void
    {
        $this->newDevice();
        $this->app['session']->put($device);
    }

    private function customerSignedIn(User $user, string $password = 'old-password'): array
    {
        $this->newDevice();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password])->assertRedirect();
        $this->get(route('account.bookings'))->assertOk();

        return $this->currentDevice();
    }

    private function staffSignedIn(Admin $admin, string $password = 'password'): array
    {
        $this->newDevice();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => $password])->assertRedirect();
        $this->get(route('admin.profile.edit'))->assertOk();

        return $this->currentDevice();
    }

    public function test_changing_the_password_logs_out_other_customer_devices_but_not_this_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $phone = $this->customerSignedIn($user);
        $laptop = $this->customerSignedIn($user);

        $this->put(route('account.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $laptop = $this->currentDevice();

        $this->switchTo($phone);
        $this->get(route('account.bookings'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->switchTo($laptop);
        $this->get(route('account.bookings'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_email_password_reset_logs_out_an_existing_customer_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $attacker = $this->customerSignedIn($user);

        // The real owner resets the password from another (signed-out) device.
        $this->newDevice();
        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->switchTo($attacker);
        $this->get(route('account.bookings'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_stale_customer_session_is_also_logged_out_on_public_pages(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $stale = $this->customerSignedIn($user);

        $user->forceFill(['password' => Hash::make('changed-elsewhere')])->save();

        $this->switchTo($stale);
        $this->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_a_staff_password_logs_out_other_staff_devices_but_not_this_one(): void
    {
        $admin = Admin::factory()->create();
        $desk = $this->staffSignedIn($admin);
        $office = $this->staffSignedIn($admin);

        $this->put(route('admin.profile.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $office = $this->currentDevice();

        $this->switchTo($desk);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        $this->switchTo($office);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_staff_password_reset_logs_out_an_existing_staff_session(): void
    {
        $admin = Admin::factory()->create();
        $stale = $this->staffSignedIn($admin);

        $this->newDevice();
        $this->post(route('admin.password.update'), [
            'token' => Password::broker('admins')->createToken($admin),
            'email' => $admin->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));

        $this->switchTo($stale);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_customer_and_staff_sessions_in_one_browser_are_checked_independently(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $admin = Admin::factory()->create();
        $this->customerSignedIn($user);
        $both = $this->currentDevice();
        $this->switchTo($both);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $both = $this->currentDevice();

        // Only the customer's password changes elsewhere.
        $user->forceFill(['password' => Hash::make('changed-elsewhere')])->save();

        $this->switchTo($both);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
    }
}
