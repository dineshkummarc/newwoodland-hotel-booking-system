<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_linked_from_admin_login(): void
    {
        $this->get(route('admin.login'))->assertSee(route('admin.password.request'));
        $this->get(route('admin.password.request'))->assertOk();
    }

    public function test_active_staff_member_receives_a_staff_reset_link(): void
    {
        Notification::fake();
        $admin = Admin::factory()->frontDesk()->create();

        $this->post(route('admin.password.email'), ['email' => $admin->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($admin, AdminResetPasswordNotification::class, function ($notification) use ($admin) {
            $mail = $notification->toMail($admin);

            return str_contains($mail->actionUrl, '/admin/reset-password/');
        });
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_response_is_identical_for_unknown_and_deactivated_accounts(): void
    {
        Notification::fake();
        $inactive = Admin::factory()->create(['is_active' => false]);

        $unknown = $this->post(route('admin.password.email'), ['email' => 'nobody@example.com']);
        $unknown->assertSessionHasNoErrors();
        $unknownStatus = session('status');

        $deactivated = $this->post(route('admin.password.email'), ['email' => $inactive->email]);
        $deactivated->assertSessionHasNoErrors();

        $this->assertNotEmpty($unknownStatus);
        $this->assertSame($unknownStatus, session('status'));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
    }

    public function test_customer_email_never_triggers_a_staff_reset_and_tokens_do_not_collide(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'shared@example.com']);
        $admin = Admin::factory()->create(['email' => 'shared@example.com']);

        $this->post(route('password.email'), ['email' => 'shared@example.com']);
        $this->post(route('admin.password.email'), ['email' => 'shared@example.com']);

        Notification::assertSentTo($customer, ResetPassword::class);
        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'shared@example.com']);
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => 'shared@example.com']);
    }

    public function test_staff_can_reset_password_with_a_valid_token(): void
    {
        $admin = Admin::factory()->create(['password' => 'old-password-1']);
        $oldRemember = $admin->remember_token;
        $token = Password::broker('admins')->createToken($admin);

        $this->get(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]))->assertOk();

        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => 'new-staff-pass', 'password_confirmation' => 'new-staff-pass',
        ])->assertRedirect(route('admin.login'));

        $fresh = $admin->fresh();
        $this->assertTrue(Hash::check('new-staff-pass', $fresh->password));
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password_reset', 'target_id' => $admin->id]);

        // The token is single-use.
        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_invalid_expired_and_mismatched_tokens_are_rejected(): void
    {
        $admin = Admin::factory()->create(['password' => 'old-password-1']);
        $payload = ['email' => $admin->email, 'password' => 'new-staff-pass', 'password_confirmation' => 'new-staff-pass'];

        $this->post(route('admin.password.update'), $payload + ['token' => 'not-a-real-token'])
            ->assertSessionHasErrors('email');

        $token = Password::broker('admins')->createToken($admin);
        $this->post(route('admin.password.update'), ['token' => $token] + array_merge($payload, ['password_confirmation' => 'mismatch']))
            ->assertSessionHasErrors('password');

        // A customer reset token cannot be used on the staff form.
        $customerToken = Password::broker('users')->createToken(User::factory()->create(['email' => $admin->email]));
        $this->post(route('admin.password.update'), $payload + ['token' => $customerToken])
            ->assertSessionHasErrors('email');

        $this->travel(31)->minutes();
        $this->post(route('admin.password.update'), $payload + ['token' => $token])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));
    }

    public function test_deactivated_staff_cannot_use_an_existing_token(): void
    {
        $admin = Admin::factory()->create(['password' => 'old-password-1']);
        $token = Password::broker('admins')->createToken($admin);
        $admin->update(['is_active' => false]);

        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => 'new-staff-pass', 'password_confirmation' => 'new-staff-pass',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $i) {
            $this->post(route('admin.password.email'), ['email' => 'someone@example.com'])->assertRedirect();
        }

        $this->post(route('admin.password.email'), ['email' => 'someone@example.com'])->assertStatus(429);
    }

    public function test_signed_in_staff_are_redirected_away_from_reset_pages(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.password.request'))
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame(0, DB::table('admin_password_reset_tokens')->count());
    }
}
