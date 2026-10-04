<?php

namespace App\Services\Sms;

use App\Contracts\SmsSenderInterface;

/**
 * Test driver: keeps sent messages in memory so tests can assert on them.
 */
class ArraySmsSender implements SmsSenderInterface
{
    /** @var array<int, array{to: string, message: string}> */
    public array $messages = [];

    /** Tests can flip these to simulate an unconfigured / real transport. */
    public bool $configured = true;

    public bool $real = false;

    public function supports(string $e164): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function deliversToPhones(): bool
    {
        return $this->real;
    }

    public function send(string $e164, string $message): void
    {
        $this->messages[] = ['to' => $e164, 'message' => $message];
    }

    /**
     * @return array<int, array{to: string, message: string}>
     */
    public function sentTo(string $e164): array
    {
        return array_values(array_filter($this->messages, fn (array $sms) => $sms['to'] === $e164));
    }
}
