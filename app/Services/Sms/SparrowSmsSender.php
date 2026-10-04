<?php

namespace App\Services\Sms;

use App\Contracts\SmsSenderInterface;
use App\Services\PhoneNumberService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sparrow SMS push (MT) API. Source of truth:
 * https://docs.sparrowsms.com/sms/outgoing_sendsms/
 *
 * POST token/from/to/text; "to" is a 10-digit Nepali mobile number. A
 * success is HTTP 200 with {"response_code": 200, ...}; failures are HTTP
 * 403 with a numeric response_code (1002 invalid token, 1008 invalid
 * sender, 1012/1013 out of credits, ...). Sparrow only delivers to Nepal.
 */
class SparrowSmsSender implements SmsSenderInterface
{
    public function __construct(protected PhoneNumberService $phones) {}

    public function supports(string $e164): bool
    {
        return $this->phones->regionFor($e164) === 'NP';
    }

    public function isConfigured(): bool
    {
        return filled(config('sms.sparrow.token')) && filled(config('sms.sparrow.from'));
    }

    public function deliversToPhones(): bool
    {
        return true;
    }

    public function send(string $e164, string $message): void
    {
        $token = config('sms.sparrow.token');
        $from = config('sms.sparrow.from');

        if (! filled($token) || ! filled($from)) {
            throw new SmsException('Sparrow SMS is not configured (missing SPARROW_SMS_TOKEN / SPARROW_SMS_FROM).');
        }

        if (! $this->supports($e164)) {
            throw new SmsException('Sparrow SMS only delivers to Nepal numbers.');
        }

        // The message body (which holds the OTP) and the token are never logged.
        $context = ['to' => $this->phones->mask($e164)];

        try {
            $response = Http::asForm()
                ->timeout(config('sms.sparrow.timeout', 10))
                ->post(config('sms.sparrow.url'), [
                    'token' => $token,
                    'from' => $from,
                    'to' => $this->phones->nationalNumber($e164),
                    'text' => $message,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Sparrow SMS could not be reached.', $context);

            throw new SmsException('Sparrow SMS could not be reached.');
        }

        if (! $response->successful() || (int) $response->json('response_code') !== 200) {
            Log::error('Sparrow SMS rejected the message.', $context + [
                'status' => $response->status(),
                'response_code' => $response->json('response_code'),
                'response' => $response->json('response'),
            ]);

            throw new SmsException('Sparrow SMS rejected the message.');
        }

        // Accepted = queued by Sparrow, not yet delivered to the handset.
        Log::info('Sparrow SMS accepted the message.', $context + [
            'count' => $response->json('count'),
            'response' => $response->json('response'),
        ]);
    }
}
