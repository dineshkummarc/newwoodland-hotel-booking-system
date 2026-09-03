<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_loads(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_link_is_sent_for_a_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_request_fails_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));

        $login = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'new-password123',
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
