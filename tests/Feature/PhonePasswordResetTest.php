<?php

namespace Tests\Feature;

use App\Contracts\SmsSenderInterface;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PhoneOtpService;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\SmsException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+9779812345678';

    protected function setUp(): void
    {
        parent::setUp();

        // Reset-code SMS is sent after the response via defer(); run it inline.
        $this->withoutDefer();
    }

    private function sms(): ArraySmsSender
    {
        return $this->app->make(SmsSenderInterface::class);
    }

    private function lastCode(): string
    {
        $last = end($this->sms()->messages);
        $this->assertNotFalse($last, 'No SMS was sent.');
        preg_match('/\b(\d{6})\b/', $last['message'], $m);

        return $m[1];
    }

    private function wrongCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function requestCode(string $phone = '9812345678', string $country = 'NP')
    {
        return $this->post(route('password.phone.send'), [
            'recovery_method' => 'phone',
            'phone_country' => $country,
            'phone' => $phone,
        ]);
    }

    private function userWithVerifiedPhone(): User
    {
        return User::factory()->withVerifiedPhone(self::PHONE)->create(['password' => Hash::make('old-password')]);
    }

    // --- Page / choice -----------------------------------------------------

    public function test_forgot_password_page_offers_email_and_phone_recovery(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Send Reset Link')
            ->assertSee(route('password.email'), false)
            ->assertSee(route('password.phone.send'), false)
            ->assertSee('name="phone_country"', false);
    }

    // --- Phone submission --------------------------------------------------

    public function test_valid_phone_sends_a_six_digit_code_to_the_verified_number(): void
    {
        $user = $this->userWithVerifiedPhone();

        $this->requestCode()
            ->assertRedirect(route('password.phone.verify'))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $this->sms()->sentTo(self::PHONE));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastCode());

        $otp = PhoneOtp::sole();
        $this->assertSame($user->id, $otp->user_id);
        $this->assertSame(PhoneOtp::PURPOSE_PASSWORD_RESET, $otp->purpose);
        $this->assertEqualsWithDelta(now()->addMinutes(5)->timestamp, $otp->expires_at->timestamp, 2);
    }

    public function test_code_is_stored_hashed_not_in_plaintext(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        $code = $this->lastCode();
        $otp = PhoneOtp::sole();

        $this->assertNotSame($code, $otp->code_hash);
        $this->assertStringNotContainsString($code, $otp->code_hash);
        $this->assertSame(64, strlen($otp->code_hash));
    }

    public function test_international_number_in_full_format_is_accepted(): void
    {
        User::factory()->withVerifiedPhone('+447911123456')->create();

        // Country select says NP, but a "+" number carries its own country code.
        $this->requestCode('+44 7911 123456', 'NP')->assertRedirect(route('password.phone.verify'));

        $this->assertCount(1, $this->sms()->sentTo('+447911123456'));
    }

    public function test_invalid_phone_number_is_rejected(): void
    {
        $this->from(route('password.request'))->requestCode('12345')
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('phone');

        $this->assertEmpty($this->sms()->messages);
    }

    public function test_landline_number_is_rejected_because_it_cannot_receive_sms(): void
    {
        $this->requestCode('014412345')->assertSessionHasErrors('phone');
    }

    public function test_unknown_country_code_is_rejected(): void
    {
        $this->requestCode('9812345678', 'XX')->assertSessionHasErrors('phone_country');
    }

    public function test_unregistered_number_gets_the_same_response_but_no_sms(): void
    {
        $registered = $this->userWithVerifiedPhone();
        $known = $this->requestCode();
        $knownMessage = session('success');

        $this->app['session']->flush();
        $unknown = $this->requestCode('9800000001');

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $unknown->assertSessionHas('success', $knownMessage);
        $this->assertCount(1, $this->sms()->messages);
        $this->assertSame(1, PhoneOtp::count());
        $this->assertTrue($registered->is(PhoneOtp::sole()->user));
    }

    public function test_unverified_phone_cannot_be_used_for_recovery(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'phone_e164' => null, 'phone_verified_at' => null]);

        $this->requestCode()->assertRedirect(route('password.phone.verify'));

        $this->assertEmpty($this->sms()->messages);
        $this->assertSame(0, PhoneOtp::count());
    }

    // --- Resend / cooldown -------------------------------------------------

    public function test_resend_is_blocked_during_the_cooldown(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        $this->post(route('password.phone.resend'))
            ->assertRedirect(route('password.phone.verify'))
            ->assertSessionHasErrors('code');

        $this->assertCount(1, $this->sms()->messages);
    }

    public function test_cooldown_applies_equally_to_unregistered_numbers(): void
    {
        $this->requestCode('9800000001');

        $this->from(route('password.request'))->requestCode('9800000001')
            ->assertSessionHasErrors(['phone' => 'Please wait 60 seconds before requesting another code.']);
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();
        $firstCode = $this->lastCode();

        $this->travel(61)->seconds();
        $this->post(route('password.phone.resend'))->assertRedirect(route('password.phone.verify'));
        $secondCode = $this->lastCode();

        $this->assertCount(2, $this->sms()->messages);
        $this->assertSame(1, PhoneOtp::pending()->count());

        if ($firstCode !== $secondCode) {
            $this->post(route('password.phone.verify.store'), ['code' => $firstCode])->assertSessionHasErrors('code');
        }

        $this->post(route('password.phone.verify.store'), ['code' => $secondCode])
            ->assertRedirect(route('password.phone.reset'));
    }

    public function test_hourly_send_limit_per_phone(): void
    {
        $this->userWithVerifiedPhone();

        for ($i = 0; $i < 5; $i++) {
            $this->requestCode()->assertSessionHasNoErrors();
            $this->travel(61)->seconds();
        }

        $this->requestCode()->assertSessionHasErrors('phone');
        $this->assertCount(5, $this->sms()->messages);
    }

    public function test_code_requests_are_rate_limited_per_ip_across_numbers(): void
    {
        foreach (['9800000001', '9800000002', '9800000003'] as $phone) {
            $this->requestCode($phone)->assertRedirect(route('password.phone.verify'));
        }

        $this->requestCode('9800000004')->assertTooManyRequests();
    }

    public function test_code_guesses_are_rate_limited_per_ip(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('password.phone.verify.store'), ['code' => '000000']);
        }

        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()])->assertTooManyRequests();
    }

    // --- Verification ------------------------------------------------------

    public function test_correct_code_is_accepted_and_leads_to_the_reset_form(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        $this->get(route('password.phone.verify'))->assertOk()->assertSee('+977 98');

        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()])
            ->assertRedirect(route('password.phone.reset'));

        $this->assertNotNull(PhoneOtp::sole()->verified_at);
        $this->get(route('password.phone.reset'))->assertOk()->assertSee('New Password');
    }

    public function test_incorrect_code_is_rejected_and_counted(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        $this->post(route('password.phone.verify.store'), ['code' => $this->wrongCode($this->lastCode())])
            ->assertSessionHasErrors(['code' => 'That code is incorrect or has expired.']);

        $this->assertSame(1, PhoneOtp::sole()->attempts);
        $this->get(route('password.phone.reset'))->assertRedirect(route('password.request', ['method' => 'phone']));
    }

    public function test_malformed_code_is_rejected_without_using_an_attempt(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        $this->post(route('password.phone.verify.store'), ['code' => '12ab'])->assertSessionHasErrors('code');

        $this->assertSame(0, PhoneOtp::sole()->attempts);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();
        $code = $this->lastCode();

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->post(route('password.phone.verify.store'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'That code is incorrect or has expired.']);
    }

    public function test_too_many_wrong_attempts_lock_the_code(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();
        $code = $this->lastCode();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.phone.verify.store'), ['code' => $this->wrongCode($code)]);
        }

        // Even the right code no longer works.
        $this->post(route('password.phone.verify.store'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Too many incorrect attempts. Please request a new code.']);

        $otp = PhoneOtp::sole();
        $this->assertSame(5, $otp->attempts);
        $this->assertNotNull($otp->consumed_at);
        $this->assertNull($otp->verified_at);
    }

    public function test_lockout_message_is_identical_for_unregistered_numbers(): void
    {
        $this->requestCode('9800000001');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.phone.verify.store'), ['code' => '000000']);
        }

        $this->post(route('password.phone.verify.store'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'Too many incorrect attempts. Please request a new code.']);
    }

    public function test_a_new_code_restores_attempts_after_lockout(): void
    {
        $this->userWithVerifiedPhone();
        $this->requestCode();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.phone.verify.store'), ['code' => $this->wrongCode($this->lastCode())]);
        }

        $this->travel(61)->seconds();
        $this->post(route('password.phone.resend'));

        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()])
            ->assertRedirect(route('password.phone.reset'));
    }

    public function test_verified_code_cannot_be_verified_again(): void
    {
        $user = $this->userWithVerifiedPhone();
        $otps = $this->app->make(PhoneOtpService::class);

        $otps->issue($user, self::PHONE, PhoneOtp::PURPOSE_PASSWORD_RESET);
        $code = $this->lastCode();

        $this->assertNotNull($otps->verify(self::PHONE, PhoneOtp::PURPOSE_PASSWORD_RESET, $code));
        $this->assertNull($otps->verify(self::PHONE, PhoneOtp::PURPOSE_PASSWORD_RESET, $code));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $restart = route('password.request', ['method' => 'phone']);

        $this->get(route('password.phone.verify'))->assertRedirect($restart);
        $this->post(route('password.phone.verify.store'), ['code' => '123456'])->assertRedirect($restart);
        $this->get(route('password.phone.reset'))->assertRedirect($restart);
        $this->post(route('password.phone.update'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect($restart);
    }

    // --- Password reset ----------------------------------------------------

    public function test_password_can_be_reset_after_successful_verification(): void
    {
        Event::fake([PasswordReset::class]);

        $user = $this->userWithVerifiedPhone();
        $oldRememberToken = $user->remember_token;

        $this->requestCode();
        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()]);

        $this->post(route('password.phone.update'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password123', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertNotNull(PhoneOtp::sole()->consumed_at);
        Event::assertDispatched(PasswordReset::class, fn ($e) => $e->user->is($user));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password123']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_requires_a_confirmed_password_of_minimum_length(): void
    {
        $user = $this->userWithVerifiedPhone();
        $this->requestCode();
        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()]);

        $this->post(route('password.phone.update'), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post(route('password.phone.update'), ['password' => 'new-password123', 'password_confirmation' => 'different123'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        // The verified code is still usable after a validation error.
        $this->assertNull(PhoneOtp::sole()->consumed_at);
    }

    public function test_verified_code_can_only_reset_the_password_once(): void
    {
        $user = $this->userWithVerifiedPhone();
        $this->requestCode();
        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()]);
        $otpId = PhoneOtp::sole()->id;

        $this->post(route('password.phone.update'), [
            'password' => 'first-password1',
            'password_confirmation' => 'first-password1',
        ])->assertRedirect(route('login'));

        // Replaying the reset with the same (now consumed) grant fails.
        $this->withSession(['phone_reset.otp_id' => $otpId])
            ->post(route('password.phone.update'), [
                'password' => 'second-password2',
                'password_confirmation' => 'second-password2',
            ])->assertRedirect(route('password.request', ['method' => 'phone']));

        $this->assertTrue(Hash::check('first-password1', $user->fresh()->password));
    }

    public function test_verified_code_expires_if_the_password_is_not_set_in_time(): void
    {
        $user = $this->userWithVerifiedPhone();
        $this->requestCode();
        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()]);

        $this->travel(11)->minutes();

        $this->post(route('password.phone.update'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('password.request', ['method' => 'phone']))->assertSessionHas('error');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_is_refused_if_the_verified_phone_changed_after_the_code_was_sent(): void
    {
        $user = $this->userWithVerifiedPhone();
        $this->requestCode();
        $this->post(route('password.phone.verify.store'), ['code' => $this->lastCode()]);

        $user->forceFill(['phone_e164' => '+9779800000002'])->save();

        $this->post(route('password.phone.update'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('password.request', ['method' => 'phone']));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    // --- Provider failure --------------------------------------------------

    public function test_sms_failure_is_not_revealed_and_the_undelivered_code_is_invalidated(): void
    {
        $this->app->instance(SmsSenderInterface::class, new class implements SmsSenderInterface
        {
            public function supports(string $e164): bool
            {
                return true;
            }

            // Configured, but the provider is down when it is called.
            public function isConfigured(): bool
            {
                return true;
            }

            public function deliversToPhones(): bool
            {
                return true;
            }

            public function send(string $e164, string $message): void
            {
                throw new SmsException('Provider down');
            }
        });

        $this->userWithVerifiedPhone();

        $this->requestCode()->assertRedirect(route('password.phone.verify'))->assertSessionHasNoErrors();

        $this->assertNotNull(PhoneOtp::sole()->consumed_at);
    }

    // --- Signed-in users ---------------------------------------------------

    public function test_signed_in_users_cannot_use_the_recovery_flow(): void
    {
        $this->actingAs($this->userWithVerifiedPhone());

        $this->requestCode()->assertRedirect(route('account.bookings'));
        $this->assertEmpty($this->sms()->messages);
    }
}
