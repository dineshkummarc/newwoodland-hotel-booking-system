<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if ($request->isSecure() && config('security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurity());
        }

        return $response;
    }

    /**
     * See config/security.php — only ever sent over HTTPS, and only when
     * explicitly enabled for the environment.
     */
    protected function strictTransportSecurity(): string
    {
        $directives = ['max-age='.max(0, (int) config('security.hsts.max_age'))];

        if (config('security.hsts.include_subdomains')) {
            $directives[] = 'includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }

    /**
     * Deliberately permissive on 'unsafe-inline' for script-src/style-src:
     * the app has no nonce/build pipeline and several pages rely on small
     * inline <script> blocks (e.g. the eSewa auto-submit redirect page) and
     * inline style="" attributes. Tightening that would need a real nonce
     * system — out of scope here, and not worth risking a broken payment
     * redirect over. Everything else is locked to 'self' plus the specific
     * third-party hosts the app actually loads from: Google Fonts,
     * jsDelivr (Bootstrap/Bootstrap Icons/Chart.js), the Google Maps embed
     * on the contact page, and
     * the payment gateways' own domains. eSewa is POSTed to directly; Khalti
     * and Fonepay are reached by a server-side redirect answering the
     * checkout form's POST, which Chromium/Safari also check against
     * form-action — so their domains must be listed too.
     */
    protected function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src https://www.google.com",
            "form-action 'self' https://rc-epay.esewa.com.np https://epay.esewa.com.np https://*.khalti.com https://*.fonepay.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
