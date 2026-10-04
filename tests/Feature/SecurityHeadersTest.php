<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::factory()->create();
    }

    public function test_baseline_security_headers_are_always_sent(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_is_not_sent_by_default(): void
    {
        $this->get('https://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_over_https_when_enabled(): void
    {
        config(['security.hsts' => ['enabled' => true, 'max_age' => 31536000, 'include_subdomains' => false, 'preload' => false]]);

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_is_never_sent_over_plain_http_even_when_enabled(): void
    {
        config(['security.hsts.enabled' => true]);

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_optional_directives_are_opt_in(): void
    {
        config(['security.hsts' => ['enabled' => true, 'max_age' => 300, 'include_subdomains' => true, 'preload' => true]]);

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=300; includeSubDomains; preload');
    }
}
