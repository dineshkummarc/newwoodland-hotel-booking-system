<?php

namespace Tests\Feature;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PhoneOtpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function test_unknown_email_gets_the_same_response_but_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);
        $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@example.com']);

        $unknown->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
        $this->assertSame($known->getSession()->get('success'), $unknown->getSession()->get('success'));
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_repeat_request_for_a_known_email_does_not_reveal_the_throttle(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
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

    private function resetWith(string $email, string $token)
    {
        return $this->post(route('password.update'), [
            'token' => $token,
            'email' => $email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->resetWith($user->email, $token)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_form_errors_do_not_reveal_whether_an_email_is_registered(): void
    {
        $user = User::factory()->create();

        $known = $this->resetWith($user->email, 'forged-token');
        $unknown = $this->resetWith('nobody@example.com', 'forged-token');

        $this->assertSame(
            $known->getSession()->get('errors')->first('email'),
            $unknown->getSession()->get('errors')->first('email'),
        );
    }

    public function test_email_reset_invalidates_outstanding_phone_reset_codes(): void
    {
        $user = User::factory()->withVerifiedPhone()->create();
        $otp = app(PhoneOtpService::class)->issue($user, '+9779812345678', PhoneOtp::PURPOSE_PASSWORD_RESET);

        $this->resetWith($user->email, Password::createToken($user))->assertRedirect(route('login'));

        $this->assertNotNull($otp->fresh()->consumed_at);
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('password.email'), ['email' => 'someone@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertTooManyRequests();
    }

    public function test_log_mailer_in_production_drops_the_email_instead_of_logging_the_reset_link(): void
    {
        // Leaving the testing env also disables the test-suite CSRF bypass.
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['mail.default' => 'log']);
        Event::fake([MessageSent::class]);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(fn ($message) => str_contains($message, 'MAIL_MAILER=log'));
        Log::shouldReceive('debug')->never();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_log_mailer_still_works_outside_production(): void
    {
        config(['mail.default' => 'log']);
        Event::fake([MessageSent::class]);

        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Event::assertDispatched(MessageSent::class);
    }
}
