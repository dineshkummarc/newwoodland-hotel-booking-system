<?php

namespace App\Contracts;

use App\Services\Sms\SmsException;

/**
 * A transport that can deliver a single text message. Implementations are
 * selected by config/sms.php's "driver" and bound in AppServiceProvider,
 * so the provider can change without touching any calling code.
 */
interface SmsSenderInterface
{
    /**
     * Whether this provider can deliver to the given E.164 number (e.g. a
     * Nepal-only gateway returns false for foreign numbers).
     */
    public function supports(string $e164): bool;

    /**
     * Whether the transport is set up well enough to attempt a send here
     * (credentials present; the log driver outside production). When this
     * is false, no code is issued and the guest is told SMS is unavailable
     * — the app never claims a code was sent when it could not have been.
     */
    public function isConfigured(): bool;

    /**
     * Whether messages actually reach a phone. False for the local "log"
     * driver and the test "array" driver, so the UI can say plainly that
     * no text message was sent.
     */
    public function deliversToPhones(): bool;

    /**
     * @throws SmsException if the message could not be handed to the provider
     */
    public function send(string $e164, string $message): void;
}
