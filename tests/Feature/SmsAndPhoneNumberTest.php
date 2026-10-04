<?php

namespace Tests\Feature;

use App\Services\PhoneNumberService;
use App\Services\Sms\SmsException;
use App\Services\Sms\SparrowSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsAndPhoneNumberTest extends TestCase
{
    public function test_numbers_are_normalized_to_e164(): void
    {
        $phones = new PhoneNumberService;

        $this->assertSame('+9779812345678', $phones->toE164('9812345678', 'NP'));
        $this->assertSame('+9779812345678', $phones->toE164('+977 981-234-5678'));
        $this->assertSame('+447911123456', $phones->toE164('07911 123456', 'GB'));
        $this->assertSame('+14155552671', $phones->toE164('(415) 555-2671', 'US'));
        $this->assertNull($phones->toE164('12345', 'NP'));
        $this->assertNull($phones->toE164('abc'));
        $this->assertNull($phones->toE164(''));
    }

    public function test_sms_capability_and_masking(): void
    {
        $phones = new PhoneNumberService;

        $this->assertTrue($phones->isSmsCapable('+9779812345678'));
        $this->assertFalse($phones->isSmsCapable('014412345', 'NP'));
        $this->assertSame('9812345678', $phones->nationalNumber('+9779812345678'));
        $this->assertSame('+977 98•••••678', $phones->mask('+9779812345678'));
        $this->assertSame('Nepal (+977)', array_values($phones->countryOptions())[0]);
    }

    public function test_sparrow_sends_the_national_number_and_accepts_success(): void
    {
        config(['sms.sparrow.token' => 'secret-token', 'sms.sparrow.from' => 'TheAlert']);
        Http::fake(['api.sparrowsms.com/*' => Http::response(['count' => 1, 'response_code' => 200, 'response' => '1 mesages has been queued for delivery'])]);

        app(SparrowSmsSender::class)->send('+9779812345678', 'Hello');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sparrowsms.com/v2/sms/'
            && $request['token'] === 'secret-token'
            && $request['from'] === 'TheAlert'
            && $request['to'] === '9812345678'
            && $request['text'] === 'Hello');
    }

    public function test_sparrow_acceptance_is_logged_without_the_token_or_message(): void
    {
        config(['sms.sparrow.token' => 'secret-token', 'sms.sparrow.from' => 'TheAlert']);
        Http::fake(['api.sparrowsms.com/*' => Http::response(['count' => 1, 'response_code' => 200, 'response' => '1 mesages has been queued for delivery'])]);
        Log::spy();

        app(SparrowSmsSender::class)->send('+9779812345678', '123456 is your code');

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) {
            $logged = $message.json_encode($context);

            return $message === 'Sparrow SMS accepted the message.'
                && $context['to'] === '+977 98•••••678'
                && $context['count'] === 1
                && ! str_contains($logged, 'secret-token')
                && ! str_contains($logged, '123456');
        });
    }

    public function test_sparrow_error_response_throws(): void
    {
        config(['sms.sparrow.token' => 'bad-token', 'sms.sparrow.from' => 'TheAlert']);
        Http::fake(['api.sparrowsms.com/*' => Http::response(['response_code' => 1002, 'response' => 'Invalid Token'], 403)]);

        $this->expectException(SmsException::class);

        app(SparrowSmsSender::class)->send('+9779812345678', 'Hello');
    }

    public function test_sparrow_refuses_foreign_numbers_and_missing_credentials(): void
    {
        Http::fake();
        $sparrow = app(SparrowSmsSender::class);

        $this->assertFalse($sparrow->supports('+447911123456'));
        $this->assertTrue($sparrow->supports('+9779812345678'));

        config(['sms.sparrow.token' => null]);

        try {
            $sparrow->send('+9779812345678', 'Hello');
            $this->fail('Expected SmsException');
        } catch (SmsException) {
            Http::assertNothingSent();
        }
    }
}
