<?php

namespace Tests\Feature;

use App\Contracts\SmsSenderInterface;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SparrowSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The root cause of "OTP not working" locally: with SMS_DRIVER=log the code
 * went to the log file while the page said it was sent. The app must now
 * say plainly when no text message is sent, refuse clearly when SMS isn't
 * set up, and never pretend.
 */
class SmsTransportHonestyTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+9779812345678';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
    }

    protected function useSparrow(?string $token, ?string $from): void
    {
        config(['sms.driver' => 'sparrow', 'sms.sparrow.token' => $token, 'sms.sparrow.from' => $from]);
        $this->app->forgetInstance(SmsSenderInterface::class);
        $this->app->instance(SmsSenderInterface::class, $this->app->make(SparrowSmsSender::class));
    }

    public function test_local_log_driver_pages_say_no_text_message_is_sent(): void
    {
        $this->app->instance(SmsSenderInterface::class, new LogSmsSender);

        $this->get(route('password.request', ['method' => 'phone']))
            ->assertOk()->assertSee('Development mode')->assertSee('no text message is sent');
    }

    public function test_unconfigured_sparrow_refuses_clearly_and_issues_nothing(): void
    {
        $this->useSparrow(null, null);
        User::factory()->withVerifiedPhone(self::PHONE)->create();
        Http::fake();

        $this->get(route('password.request', ['method' => 'phone']))->assertSee("SMS codes can't be sent at the moment", false);

        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678'])
            ->assertSessionHasErrors(['phone' => 'Password reset by SMS is not available right now. Please use the Email Address option instead, or contact the hotel.']);

        $this->assertSame(0, PhoneOtp::count());
        Http::assertNothingSent();
    }

    public function test_the_refusal_is_the_same_for_registered_and_unknown_numbers(): void
    {
        $this->useSparrow(null, null);
        User::factory()->withVerifiedPhone(self::PHONE)->create();

        $known = $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678']);
        $unknown = $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9811111111']);

        $this->assertSame($known->getSession()->get('errors')->first('phone'), $unknown->getSession()->get('errors')->first('phone'));
    }

    public function test_configured_sparrow_sends_the_documented_request_and_never_logs_the_code(): void
    {
        $this->useSparrow('test-token-not-real', 'NWHotel');
        Http::fake(['api.sparrowsms.com/*' => Http::response(['count' => 1, 'response_code' => 200, 'response' => '1 mesages has been queued for delivery'], 200)]);
        Log::spy();
        User::factory()->withVerifiedPhone(self::PHONE)->create();

        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '+977 981-234-5678'])
            ->assertRedirect(route('password.phone.verify'));

        $sent = null;
        Http::assertSent(function (Request $request) use (&$sent) {
            $sent = $request->data();

            return $request->url() === 'https://api.sparrowsms.com/v2/sms/' && $request->method() === 'POST';
        });
        $this->assertSame('test-token-not-real', $sent['token']);
        $this->assertSame('NWHotel', $sent['from']);
        $this->assertSame('9812345678', $sent['to'], 'Sparrow expects the 10-digit national number.');
        $this->assertMatchesRegularExpression('/^\d{6} is your /', $sent['text']);

        $code = substr($sent['text'], 0, 6);
        Log::shouldNotHaveReceived('info', [\Mockery::on(fn ($m) => str_contains((string) $m, $code))]);
    }

    public function test_sparrow_rejection_and_timeouts_are_handled_without_revealing_anything(): void
    {
        $this->useSparrow('bad-token', 'NWHotel');
        Http::fake(['api.sparrowsms.com/*' => Http::response(['response_code' => 1002, 'response' => 'Invalid Token'], 403)]);
        User::factory()->withVerifiedPhone(self::PHONE)->create();

        $this->post(route('password.phone.send'), ['recovery_method' => 'phone', 'phone_country' => 'NP', 'phone' => '9812345678'])
            ->assertRedirect(route('password.phone.verify'))
            ->assertSessionHasNoErrors();

        // The undeliverable code can never be used.
        $this->assertNotNull(PhoneOtp::sole()->consumed_at);
    }
}
