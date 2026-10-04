<?php

namespace Tests\Feature;

use App\Contracts\SmsSenderInterface;
use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Models\Booking;
use App\Models\PhoneOtp;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The delivery paths that real SMTP / Sparrow credentials will plug into,
 * exercised end to end with fakes — nothing leaves the test run.
 */
class TransactionalDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+9779812345678';

    /** Swap in the real Sparrow driver, with fake credentials and HTTP. */
    private function useSparrow(): void
    {
        config(['sms.driver' => 'sparrow', 'sms.sparrow.token' => 'test-token', 'sms.sparrow.from' => 'TestSender']);
        $this->app->forgetInstance(SmsSenderInterface::class);
    }

    /** The 6-digit code from the one SMS sent through the fake Sparrow API. */
    private function codeSentBySparrow(): string
    {
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        $this->assertTrue((bool) preg_match('/\b(\d{6})\b/', $recorded[0][0]['text'], $m));

        return $m[1];
    }

    public function test_tests_never_use_a_real_mail_or_sms_transport(): void
    {
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('array', config('sms.driver'));
    }

    // --- email ----------------------------------------------------------------

    public function test_booking_confirmation_email_reflects_the_confirmed_hotel_policies(): void
    {
        Mail::fake();
        Setting::factory()->create(['cancellation_deduction_percent' => 0, 'site_email' => 'frontdesk@example.test']);
        $booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CONFIRMED]);

        app(BookingService::class)->sendNotifications($booking, 'frontdesk@example.test');

        Mail::assertQueued(BookingConfirmationMail::class, fn ($mail) => $mail->hasTo($booking->email));
        Mail::assertQueued(NewBookingNotificationMail::class, fn ($mail) => $mail->hasTo('frontdesk@example.test'));
        Mail::assertNothingSent(); // queued, never sent inline

        $html = (new BookingConfirmationMail($booking))->render();
        $this->assertStringContainsString('Newwoodland Hotel & Lounge', $html);
        $this->assertStringContainsString('Cancellation policy: free before arrival', $html);
        $this->assertStringContainsString('Please bring a valid photo ID for check-in: Citizenship Certificate, Student ID Card or Office / Company ID Card.', $html);
        $this->assertStringContainsString('50% of the booking amount is charged if the stay is cancelled after check-in', $html);
        foreach (['Hotel and Lounge', 'satisfied', 'Cash on Arrival', 'in cash at the hotel'] as $outdated) {
            $this->assertStringNotContainsString($outdated, $html);
        }
    }

    // --- SMS through the Sparrow driver (HTTP faked) ---------------------------

    public function test_phone_verification_code_is_delivered_through_sparrow_and_verifies_the_phone(): void
    {
        Http::fake(['api.sparrowsms.com/*' => Http::response(['count' => 1, 'response_code' => 200, 'response' => '1 mesages has been queued for delivery'])]);
        $this->useSparrow();
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);

        $this->actingAs($user)->post(route('account.phone.send'), [
            'phone_country' => 'NP', 'phone' => '981-234-5678', 'current_password' => 'secret-pass',
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sparrowsms.com/v2/sms/'
            && $request['token'] === 'test-token'
            && $request['from'] === 'TestSender'
            && $request['to'] === '9812345678'
            && preg_match('/^\d{6} is your '.preg_quote(config('app.name'), '/').' phone verification code\. It expires in 5 minutes\./', $request['text']));

        $this->post(route('account.phone.verify'), ['code' => $this->codeSentBySparrow()])->assertSessionHas('success');
        $this->assertSame(self::PHONE, $user->fresh()->phone_e164);
        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    public function test_phone_password_reset_code_is_delivered_through_sparrow(): void
    {
        $this->withoutDefer();
        Http::fake(['api.sparrowsms.com/*' => Http::response(['count' => 1, 'response_code' => 200, 'response' => 'queued'])]);
        $this->useSparrow();
        $user = User::factory()->withVerifiedPhone(self::PHONE)->create(['password' => Hash::make('old-password')]);

        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678'])
            ->assertRedirect(route('password.phone.verify'));

        Http::assertSent(fn (Request $request) => $request['to'] === '9812345678'
            && str_contains($request['text'], 'password reset code'));

        $this->post(route('password.phone.verify.store'), ['code' => $this->codeSentBySparrow()])
            ->assertRedirect(route('password.phone.reset'));
        $this->post(route('password.phone.update'), ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_sparrow_being_unreachable_is_reported_and_the_undelivered_code_is_unusable(): void
    {
        Http::fake(['api.sparrowsms.com/*' => Http::failedConnection()]);
        $this->useSparrow();
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);

        $this->actingAs($user)->post(route('account.phone.send'), [
            'phone_country' => 'NP', 'phone' => '9812345678', 'current_password' => 'secret-pass',
        ])->assertSessionHasErrors(['phone' => 'We could not send the SMS right now. Please try again in a few minutes.']);

        $this->assertNotNull(PhoneOtp::sole()->consumed_at);
        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    public function test_sparrow_rejecting_the_message_does_not_reveal_anything_on_phone_recovery(): void
    {
        $this->withoutDefer();
        Http::fake(['api.sparrowsms.com/*' => Http::response(['response_code' => 1002, 'response' => 'Invalid Token'], 403)]);
        $this->useSparrow();
        User::factory()->withVerifiedPhone(self::PHONE)->create();

        // Same response as for an unknown number: the failure is only logged.
        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678'])
            ->assertRedirect(route('password.phone.verify'))
            ->assertSessionHas('success', 'If an account uses this verified phone number, we have sent it a 6-digit code.');

        $this->assertNotNull(PhoneOtp::sole()->consumed_at);
    }
}
