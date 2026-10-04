<?php

namespace Tests\Feature;

use App\Contracts\SmsSenderInterface;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\PhoneOtp;
use App\Services\Sms\ArraySmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff OTP: verify a phone in Admin → Profile, then reset the password by
 * SMS code (email + verified phone required). Fake SMS transport only.
 */
class AdminPhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+9779812345678';

    protected function setUp(): void
    {
        parent::setUp();
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

    private function staff(array $overrides = []): Admin
    {
        $admin = Admin::factory()->create(array_merge(['email' => 'manager@example.com', 'password' => 'old-password-1'], $overrides));
        $admin->forceFill(['phone_e164' => self::PHONE, 'phone_verified_at' => now()])->save();

        return $admin;
    }

    private function requestCode(string $email = 'manager@example.com', string $phone = '9812345678')
    {
        return $this->post(route('admin.password.phone.send'), ['email' => $email, 'phone_country' => 'NP', 'phone' => $phone]);
    }

    // --- verifying a staff phone --------------------------------------------

    public function test_staff_can_verify_their_phone_with_a_code(): void
    {
        $admin = Admin::factory()->create(['password' => 'secret-pass-1']);

        $this->actingAs($admin, 'admin')->post(route('admin.profile.phone.send'), [
            'phone_country' => 'NP', 'phone' => '9812345678', 'current_password' => 'secret-pass-1',
        ])->assertRedirect(route('admin.profile.edit'))->assertSessionHasNoErrors();

        $this->assertSame(self::PHONE, $this->sms()->messages[0]['to']);
        $this->assertNull($admin->fresh()->phone_verified_at, 'Not verified until the code is entered.');

        $this->actingAs($admin, 'admin')->post(route('admin.profile.phone.verify'), ['code' => $this->lastCode()])
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertTrue($admin->hasVerifiedPhone());
        $this->assertSame(self::PHONE, $admin->phone_e164);
        $this->assertTrue(AuditLog::where('action', 'admin.phone_verified')->exists());
    }

    public function test_changing_the_staff_phone_requires_the_current_password(): void
    {
        $admin = Admin::factory()->create(['password' => 'secret-pass-1']);

        $this->actingAs($admin, 'admin')->post(route('admin.profile.phone.send'), [
            'phone_country' => 'NP', 'phone' => '9812345678', 'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame([], $this->sms()->messages);
    }

    public function test_a_wrong_code_does_not_verify_the_staff_phone(): void
    {
        $admin = Admin::factory()->create(['password' => 'secret-pass-1']);
        $this->actingAs($admin, 'admin')->post(route('admin.profile.phone.send'), [
            'phone_country' => 'NP', 'phone' => '9812345678', 'current_password' => 'secret-pass-1',
        ]);
        $wrong = str_pad((string) (((int) $this->lastCode() + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->actingAs($admin, 'admin')->post(route('admin.profile.phone.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->assertFalse($admin->fresh()->hasVerifiedPhone());
    }

    // --- forgot password by SMS -----------------------------------------------

    public function test_full_staff_reset_by_sms_code(): void
    {
        $admin = $this->staff();

        $this->requestCode()->assertRedirect(route('admin.password.phone.verify'));
        $this->assertCount(1, $this->sms()->sentTo(self::PHONE));
        $this->assertStringNotContainsString($this->lastCode(), (string) PhoneOtp::sole()->code_hash, 'Only a hash is stored.');

        $this->post(route('admin.password.phone.verify.store'), ['code' => $this->lastCode()])
            ->assertRedirect(route('admin.password.phone.reset'));

        $this->post(route('admin.password.phone.update'), ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'admin.password_reset')->where('target_id', $admin->id)->exists());

        // The new password works; the code can't be used again.
        $this->post(route('admin.login.store'), ['email' => 'manager@example.com', 'password' => 'brand-new-pass'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_phone_alone_is_not_enough_the_email_must_match(): void
    {
        $this->staff();

        $this->requestCode(email: 'someone-else@example.com')->assertRedirect(route('admin.password.phone.verify'));

        $this->assertSame([], $this->sms()->messages, 'No code for a phone/email pair that does not match.');
    }

    public function test_responses_are_identical_for_unknown_numbers_and_inactive_staff(): void
    {
        $this->staff(['is_active' => false]);

        $inactive = $this->requestCode();
        $this->flushSession();
        $unknown = $this->requestCode(phone: '9811111111');

        $inactive->assertRedirect(route('admin.password.phone.verify'));
        $unknown->assertRedirect(route('admin.password.phone.verify'));
        $this->assertSame($inactive->getSession()->get('status'), $unknown->getSession()->get('status'));
        $this->assertSame([], $this->sms()->messages);
    }

    public function test_unverified_staff_phones_cannot_reset(): void
    {
        $admin = Admin::factory()->create(['email' => 'manager@example.com']);
        $admin->forceFill(['phone_e164' => self::PHONE, 'phone_verified_at' => null])->save();

        $this->requestCode();

        $this->assertSame([], $this->sms()->messages);
    }

    public function test_wrong_codes_are_limited_and_then_the_code_dies(): void
    {
        $this->staff();
        $this->requestCode();
        $code = $this->lastCode();
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.phone.verify.store'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Even the right code is refused now.
        $this->post(route('admin.password.phone.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse(session()->has('admin_phone_reset.otp_id'));
    }

    public function test_expired_codes_are_refused(): void
    {
        $this->staff();
        $this->requestCode();
        $code = $this->lastCode();

        $this->travel(config('sms.otp.ttl_minutes') + 1)->minutes();

        $this->post(route('admin.password.phone.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_resend_is_throttled_and_a_new_code_replaces_the_old_one(): void
    {
        $this->staff();
        $this->requestCode();
        $first = $this->lastCode();

        // Too soon.
        $this->post(route('admin.password.phone.resend'))->assertSessionHasErrors('code');
        $this->assertCount(1, $this->sms()->messages);

        $this->travel(61)->seconds();
        $this->post(route('admin.password.phone.resend'))->assertRedirect(route('admin.password.phone.verify'));
        $second = $this->lastCode();
        $this->assertCount(2, $this->sms()->messages);

        if ($first !== $second) {
            $this->post(route('admin.password.phone.verify.store'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('admin.password.phone.verify.store'), ['code' => $second])->assertRedirect(route('admin.password.phone.reset'));
    }

    public function test_the_reset_page_cannot_be_reached_without_a_verified_code(): void
    {
        $this->get(route('admin.password.phone.reset'))->assertRedirect(route('admin.password.request'));
        $this->post(route('admin.password.phone.update'), ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('admin.password.request'));
    }

    public function test_a_staff_member_deactivated_mid_reset_cannot_finish(): void
    {
        $admin = $this->staff();
        $this->requestCode();
        $this->post(route('admin.password.phone.verify.store'), ['code' => $this->lastCode()]);

        $admin->update(['is_active' => false]);

        $this->post(route('admin.password.phone.update'), ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass']);
        $this->assertFalse(Hash::check('brand-new-pass', $admin->fresh()->password));
    }

    public function test_customer_and_staff_codes_never_cross_over(): void
    {
        $this->staff();
        \App\Models\User::factory()->withVerifiedPhone(self::PHONE)->create();

        // A customer code for the same phone...
        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678']);
        $customerCode = $this->lastCode();
        $this->flushSession();

        // ...cannot be used on the staff reset.
        $this->requestCode();
        $this->sms()->messages = [];
        $this->post(route('admin.password.phone.verify.store'), ['code' => $customerCode]);
        $this->assertFalse(session()->has('admin_phone_reset.otp_id'));
    }

    public function test_sms_reset_is_refused_clearly_when_sms_is_not_set_up(): void
    {
        $this->sms()->configured = false;
        $this->staff();

        $this->requestCode()->assertSessionHasErrors('phone');

        $this->assertSame([], $this->sms()->messages);
        $this->assertSame(0, PhoneOtp::count());
    }
}
