<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');

Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room:id}/availability', [RoomController::class, 'availability'])->name('rooms.availability');

Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact-submit')
    ->name('contact.store');

Route::get('/booking', [BookingController::class, 'create'])->name('booking');
Route::post('/booking', [BookingController::class, 'store'])
    ->middleware('throttle:booking-submit')
    ->name('booking.store');
Route::get('/booking/confirmation/{booking:confirmation_code}', [BookingController::class, 'confirmation'])
    ->name('booking.confirmation');

Route::get('/booking/{booking:confirmation_code}/pay', [PaymentController::class, 'show'])
    ->name('booking.pay');
Route::post('/booking/{booking:confirmation_code}/pay', [PaymentController::class, 'initiate'])
    ->middleware('throttle:payment-initiate')
    ->name('booking.pay.initiate');

Route::get('/payment/esewa/success', [PaymentController::class, 'success'])->name('payment.esewa.success');
Route::get('/payment/esewa/failure', [PaymentController::class, 'failure'])->name('payment.esewa.failure');

Route::post('/booking/{booking:confirmation_code}/pay/khalti', [PaymentController::class, 'khaltiInitiate'])
    ->middleware('throttle:payment-initiate')
    ->name('booking.pay.khalti.initiate');
Route::get('/payment/khalti/return', [PaymentController::class, 'khaltiReturn'])->name('payment.khalti.return');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-attempt')
        ->name('register.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-attempt')
        ->name('login.store');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
        ->middleware('throttle:auth-attempt')
        ->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:auth-attempt')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/my-bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::post('/my-bookings/{booking:confirmation_code}/cancel', [AccountController::class, 'cancelBooking'])
        ->name('account.bookings.cancel');
});

Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms-conditions', [PageController::class, 'terms'])->name('terms');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', function () {
    return response()
        ->view('robots')
        ->header('Content-Type', 'text/plain');
})->name('robots');
