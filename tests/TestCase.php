<?php

namespace Tests;

use App\Services\BookingService;
use App\Services\Payments\GatewayEnvironmentGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wraps each test in a transaction; inventory
        // transactions opened by the code under test are "outermost" one
        // level deeper than in the real application.
        BookingService::$baseTransactionLevel = $this->app->bound('db') ? DB::transactionLevel() : 0;

        // No test may ever reach a real payment gateway, SMS or HTTP API:
        // every outgoing request must be faked explicitly.
        Http::preventStrayRequests();
        GatewayEnvironmentGuard::resetLogged();
    }

    /**
     * Switching to another user mid-test stands in for a fresh sign-in, so
     * forget the previous user's password hash that AuthenticateSession keeps
     * in the session — otherwise the new user would be logged straight out.
     */
    public function be(Authenticatable $user, $guard = null)
    {
        $this->app['session']->forget('password_hash_'.($guard ?? $this->app['auth']->getDefaultDriver()));

        return parent::be($user, $guard);
    }
}
