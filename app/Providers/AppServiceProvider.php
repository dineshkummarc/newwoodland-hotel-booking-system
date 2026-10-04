<?php

namespace App\Providers;

use App\Contracts\SmsSenderInterface;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SparrowSmsSender;
use App\View\Composers\AdminNavComposer;
use App\View\Composers\SettingsComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS provider is chosen by SMS_DRIVER (config/sms.php, docs/SMS.md).
        $this->app->singleton(SmsSenderInterface::class, function ($app) {
            return match (config('sms.driver')) {
                'sparrow' => $app->make(SparrowSmsSender::class),
                'log' => new LogSmsSender,
                'array' => new ArraySmsSender,
                default => throw new InvalidArgumentException('Unsupported SMS_DRIVER ['.config('sms.driver').'].'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A production site served over HTTPS generates https:// links and
        // redirects even if a request reached PHP as plain HTTP from a proxy.
        // (Detecting HTTPS on incoming requests — for secure cookies, HSTS
        // and signed-link checks — still needs TRUSTED_PROXIES, see
        // config/trustedproxy.php.)
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer(['partials.header', 'partials.footer', 'home', 'contact', 'terms-condition', 'privacy-policy', 'rooms-details', 'about', 'booking-bank-transfer', 'booking-cancel-guest', 'account.bookings', 'booking', 'booking-access', 'booking-payment'], SettingsComposer::class);
        View::composer('layouts.admin', AdminNavComposer::class);

        // A "log" mailer delivers nothing, and in production it would write
        // live password-reset links and guest details into the log files.
        // Drop the message instead and flag the misconfiguration loudly.
        Event::listen(function (MessageSending $event) {
            $transport = config('mail.mailers.'.($event->data['mailer'] ?? config('mail.default')).'.transport');

            if ($transport === 'log' && $this->app->isProduction()) {
                Log::error('MAIL_MAILER=log is in use in production; an email was not sent. Configure a real mailer.');

                return false;
            }
        });

        RateLimiter::for('booking-submit', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        // Live availability checks fire on every date/room/guest change of
        // the booking form; generous, but each one runs several queries.
        RateLimiter::for('availability-check', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Guest-facing booking pages (confirmation, payment, bank details).
        // Access is never granted by the code alone (BookingAccess), but a
        // cap still stops anyone hammering the route to probe codes.
        RateLimiter::for('booking-view', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // "Email me my booking link": per IP, plus per typed email address
        // so one guest's inbox can't be flooded from many addresses.
        RateLimiter::for('booking-access-link', function (Request $request) {
            return [
                Limit::perMinute(5)->by('ip|'.$request->ip()),
                Limit::perHour(5)->by('email|'.strtolower(trim((string) $request->input('email')))),
            ];
        });

        // Choosing Bank Transfer extends a room hold from 30 minutes to a
        // day, so it gets its own per-IP cap on top of the per-guest cap of
        // unpaid holds (BookingService::assertWithinUnpaidHoldLimit()).
        RateLimiter::for('offline-hold', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('payment-initiate', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // eSewa/Khalti redirect the customer's own browser back here, but
        // each hit also triggers a server-to-server status call to the
        // gateway — throttle generously (well above any real customer's
        // usage) purely to stop this route being used to hammer that
        // outbound call.
        RateLimiter::for('payment-return', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('booking-cancel-guest', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('contact-submit', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        // Staff password recovery: per IP+email, plus an hourly per-IP cap so
        // it can't be used to spray reset emails across staff addresses.
        RateLimiter::for('admin-password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by('minute|'.$request->ip().'|'.strtolower((string) $request->input('email'))),
                Limit::perHour(10)->by('hour|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-attempt', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip().'|'.$request->input('email'));
        });

        // Signed-in account changes that check the current password: capped
        // per account (not just per IP) so it can't be guessed from a stolen session.
        RateLimiter::for('account-security', function (Request $request) {
            return Limit::perMinute(6)->by('account|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Per-IP caps on requesting SMS codes (each one costs money and can
        // be aimed at someone else's phone). Per-phone cooldown and hourly
        // caps are enforced in PhoneOtpService, keyed on the number itself.
        RateLimiter::for('phone-otp-send', function (Request $request) {
            return [
                Limit::perMinute(3)->by('minute|'.$request->ip()),
                Limit::perHour(20)->by('hour|'.$request->ip()),
            ];
        });

        // Per-IP cap on code guesses, on top of the per-code attempt limit.
        RateLimiter::for('phone-otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
