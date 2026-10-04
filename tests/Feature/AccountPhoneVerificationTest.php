<?php

namespace Tests\Feature;

use App\Contracts\SmsSenderInterface;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SparrowSmsSender;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AccountPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function sms(): ArraySmsSender
    {
        return $this->app->make(SmsSenderInterface::class);
    }

    private function lastCode(): string
    {
        $last = end($this->sms()->messages);
        preg_match('/\b(\d{6})\b/', $last['message'], $m);

        return $m[1];
    }

    private function user(): User
    {
        return User::factory()->create(['password' => Hash::make('secret-pass'), 'phone' => '9812345678']);
    }

    private function sendCode(array $overrides = [])
    {
        return $this->post(route('account.phone.send'), $overrides + [
            'phone_country' => 'NP',
            'phone' => '9812345678',
            'current_password' => 'secret-pass',
        ]);
    }

    public function test_guests_cannot_access_phone_settings(): void
    {
        $this->get(route('account.phone'))->assertRedirect(route('login'));
    }

    public function test_page_prefills_the_registration_phone(): void
    {
        $this->actingAs($this->user())
            ->get(route('account.phone'))
            ->assertOk()
            ->assertSee('No verified phone number')
            ->assertSee('value="9812345678"', false);
    }

    public function test_user_can_verify_their_phone(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->sendCode()->assertRedirect(route('account.phone'))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->sms()->sentTo('+9779812345678'));

        $this->post(route('account.phone.verify'), ['code' => $this->lastCode()])
            ->assertRedirect(route('account.phone'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('+9779812345678', $user->phone_e164);
        $this->assertSame('+9779812345678', $user->phone);
        $this->assertTrue($user->hasVerifiedPhone());

        $otp = PhoneOtp::sole();
        $this->assertNotNull($otp->verified_at);
        $this->assertNotNull($otp->consumed_at);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->user());

        $this->sendCode(['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertEmpty($this->sms()->messages);
    }

    public function test_incorrect_code_does_not_verify(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $this->sendCode();

        $wrong = str_pad((string) (((int) $this->lastCode() + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->post(route('account.phone.verify'), ['code' => $wrong])->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    public function test_a_phone_verified_by_another_account_cannot_be_reused(): void
    {
        User::factory()->withVerifiedPhone('+9779812345678')->create();
        $this->actingAs($this->user());

        $this->sendCode()->assertSessionHasErrors(['phone' => 'This phone number is already linked to another account.']);
        $this->assertEmpty($this->sms()->messages);
    }

    public function test_changing_verified_phone_invalidates_outstanding_reset_codes(): void
    {
        $user = User::factory()->withVerifiedPhone('+9779800000001')->create(['password' => Hash::make('secret-pass')]);
        $resetOtp = PhoneOtp::create([
            'user_id' => $user->id,
            'phone' => '+9779800000001',
            'purpose' => PhoneOtp::PURPOSE_PASSWORD_RESET,
            'code_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($user);
        $this->sendCode();
        $this->post(route('account.phone.verify'), ['code' => $this->lastCode()]);

        $this->assertSame('+9779812345678', $user->fresh()->phone_e164);
        $this->assertNotNull($resetOtp->fresh()->consumed_at);
    }

    public function test_log_driver_in_production_reports_failure_instead_of_claiming_the_code_was_sent(): void
    {
        // Leaving the testing env also disables the test-suite CSRF bypass.
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->instance(SmsSenderInterface::class, new LogSmsSender);

        // No message (and so no OTP) is ever written to the log.
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning', 'error')->zeroOrMoreTimes();

        $this->actingAs($this->user());

        // The log driver can't deliver in production, so no code is issued
        // at all and the guest is told SMS is unavailable — never "sent".
        $this->sendCode()
            ->assertSessionHasErrors(['phone' => 'SMS codes can\'t be sent at the moment, so your number can\'t be verified right now. Please try again later or contact the hotel.'])
            ->assertSessionMissing('success');

        $this->assertSame(0, PhoneOtp::count());
    }

    public function test_country_unsupported_by_the_sms_provider_is_reported(): void
    {
        config(['sms.driver' => 'sparrow', 'sms.sparrow.token' => 'test-token', 'sms.sparrow.from' => 'Hotel']);
        $this->app->forgetInstance(SmsSenderInterface::class);
        $this->app->instance(SmsSenderInterface::class, $this->app->make(SparrowSmsSender::class));

        $this->actingAs($this->user());

        $this->sendCode(['phone_country' => 'GB', 'phone' => '07911 123456'])
            ->assertSessionHasErrors(['phone' => "Sorry, we can't send SMS codes to numbers in this country yet."]);
    }

    public function test_registration_normalizes_the_phone_to_e164_without_verifying_it(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'phone_country' => 'NP',
            'phone' => '981-234-5678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.bookings'));

        $user = User::where('email', 'guest@example.com')->sole();
        $this->assertSame('+9779812345678', $user->phone);
        $this->assertNull($user->phone_e164);
        $this->assertFalse($user->hasVerifiedPhone());
    }

    public function test_registration_rejects_an_invalid_phone(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'phone' => 'not-a-phone',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('phone');
    }
}
