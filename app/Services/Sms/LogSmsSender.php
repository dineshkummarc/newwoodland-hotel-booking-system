<?php

namespace App\Services\Sms;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Local-development driver: writes the SMS to the log instead of sending
 * it, so OTP flows can be exercised without a provider account. In
 * production it never logs the body (that would put live one-time
 * passcodes into log files) and fails like any undeliverable message, so
 * callers don't tell guests a code was sent when nothing left the server.
 */
class LogSmsSender implements SmsSenderInterface
{
    public function supports(string $e164): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return ! app()->isProduction();
    }

    public function deliversToPhones(): bool
    {
        return false;
    }

    public function send(string $e164, string $message): void
    {
        $logger = Log::channel(config('sms.log_channel'));

        if (app()->isProduction()) {
            $logger->error('SMS_DRIVER=log is in use in production; no SMS was sent. Configure a real SMS driver.');

            throw new SmsException('No SMS driver is configured (SMS_DRIVER=log in production).');
        }

        $logger->info("[SMS to {$e164}] {$message}");
    }
}
