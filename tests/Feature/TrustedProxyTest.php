<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 8 — client IP / HTTPS detection behind a proxy, as far as it can be
 * tested in-process. Real proxy behaviour must still be verified on the
 * server (see docs/DEPLOYMENT.md §9).
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TrustProxies::flushState();
        Route::middleware('web')->get('/_test/whoami', fn () => response()->json([
            'ip' => request()->ip(),
            'secure' => request()->isSecure(),
        ]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    protected function viaProxy(string $proxyIp, string $clientIp)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $proxyIp])
            ->withHeaders(['X-Forwarded-For' => $clientIp, 'X-Forwarded-Proto' => 'https'])
            ->getJson('/_test/whoami');
    }

    public function test_by_default_forwarded_headers_are_not_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        // A visitor can't spoof their IP (and escape rate limits) with a header.
        $this->viaProxy('203.0.113.9', '198.51.100.1')
            ->assertJson(['ip' => '203.0.113.9', 'secure' => false]);
    }

    public function test_a_configured_proxy_passes_the_real_client_ip_and_https(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5, 10.0.0.6']);

        $this->viaProxy('10.0.0.5', '198.51.100.1')
            ->assertJson(['ip' => '198.51.100.1', 'secure' => true]);
    }

    public function test_an_unlisted_proxy_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5']);

        $this->viaProxy('203.0.113.9', '198.51.100.1')
            ->assertJson(['ip' => '203.0.113.9', 'secure' => false]);
    }

    public function test_cidr_ranges_work_for_cloudflare_style_configuration(): void
    {
        config(['trustedproxy.proxies' => '173.245.48.0/20,103.21.244.0/22']);

        $this->viaProxy('173.245.48.10', '198.51.100.1')
            ->assertJson(['ip' => '198.51.100.1', 'secure' => true]);
    }

    public function test_rate_limits_apply_to_each_real_client_not_to_the_proxy(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5']);

        // Client A exhausts its limit on the booking page...
        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeaders(['X-Forwarded-For' => '198.51.100.1'])
                ->get(route('booking.confirmation', 'NW-2026-AAAAAA'));
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeaders(['X-Forwarded-For' => '198.51.100.1'])
            ->get(route('booking.confirmation', 'NW-2026-AAAAAA'))->assertStatus(429);

        // ...while client B behind the same proxy is unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeaders(['X-Forwarded-For' => '198.51.100.2'])
            ->get(route('booking.confirmation', 'NW-2026-AAAAAA'))->assertForbidden();
    }

    public function test_signed_links_generated_for_https_validate_when_https_arrives_via_the_proxy(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5', 'app.url' => 'https://hotel.example']);
        // As AppServiceProvider does on a production site whose APP_URL is https.
        URL::forceRootUrl('https://hotel.example');
        URL::forceScheme('https');
        $booking = Booking::factory()->create(['customer_name' => 'Sita Sharma']);
        $url = $booking->viewUrl();
        $this->assertStringStartsWith('https://hotel.example/', $url);

        $path = substr($url, strlen('https://hotel.example'));

        // TLS terminated at the trusted proxy: the signature still matches.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_HOST' => 'hotel.example'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get($path)->assertOk()->assertSee('Sita Sharma');
    }

    public function test_session_cookies_are_secure_by_default_on_an_https_site(): void
    {
        // config/session.php derives the default from APP_URL.
        $config = require base_path('config/session.php');
        $this->assertSame(str_starts_with((string) env('APP_URL', ''), 'https://'), (bool) $config['secure']);
    }
}
