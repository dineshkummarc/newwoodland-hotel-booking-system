<?php

namespace App\Providers;

use App\View\Composers\AdminNavComposer;
use App\View\Composers\SettingsComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['partials.header', 'partials.footer', 'home', 'contact', 'terms-condition', 'privacy-policy'], SettingsComposer::class);
        View::composer('layouts.admin', AdminNavComposer::class);

        RateLimiter::for('booking-submit', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('contact-submit', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        RateLimiter::for('auth-attempt', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip().'|'.$request->input('email'));
        });
    }
}
