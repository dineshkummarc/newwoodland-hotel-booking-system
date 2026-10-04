<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPhoneController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GuestCancellationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhonePasswordResetController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SitemapController;
use App\Support\BookingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');

Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room:id}/availability', [RoomController::class, 'availability'])
    ->middleware('throttle:availability-check')
    ->name('rooms.availability');

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
// Guest-facing booking pages. The confirmation code in the URL is a
// reference, never a credential: every route here goes through
// booking.access (App\Support\BookingAccess), and an unknown code gets the
// very same "open the link from your email" page as someone else's booking,
// so codes can't be probed or brute-forced into guest details.
$bookingDenied = fn (Request $request) => BookingAccess::deniedResponse($request);

Route::get('/booking/confirmation/{booking:confirmation_code}', [BookingController::class, 'confirmation'])
    ->middleware(['throttle:booking-view', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.confirmation');

// "Email me my booking link": sends the signed link to the email address on
// the booking — never shows anything on screen, whatever is typed.
Route::post('/booking/access-link', [BookingController::class, 'sendAccessLink'])
    ->middleware('throttle:booking-access-link')
    ->name('booking.access-link');

// Self-cancellation for bookings made without an account: signed,
// purpose-specific, expiring links from the guest's own emails (or shown on
// the confirmation page to someone who already has access to it).
Route::get('/booking/{booking:confirmation_code}/cancel', [GuestCancellationController::class, 'show'])
    ->middleware(['signed', 'throttle:booking-cancel-guest'])
    ->name('booking.cancel.guest');
Route::post('/booking/{booking:confirmation_code}/cancel', [GuestCancellationController::class, 'cancel'])
    ->middleware(['signed', 'throttle:booking-cancel-guest'])
    ->name('booking.cancel.guest.store');

Route::get('/booking/{booking:confirmation_code}/invoice', [InvoiceController::class, 'guest'])
    ->middleware(['signed', 'throttle:booking-cancel-guest'])
    ->name('booking.invoice.guest');

Route::get('/booking/{booking:confirmation_code}/pay', [PaymentController::class, 'show'])
    ->middleware(['throttle:booking-view', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay');
Route::post('/booking/{booking:confirmation_code}/pay', [PaymentController::class, 'initiate'])
    ->middleware(['throttle:payment-initiate', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.initiate');
// "Continue Payment" on the payment page: dispatches to the chosen method's
// own initiate action (same checks as posting to that route directly).
Route::post('/booking/{booking:confirmation_code}/pay/continue', [PaymentController::class, 'continue'])
    ->middleware(['throttle:payment-initiate', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.continue');

Route::get('/payment/esewa/success', [PaymentController::class, 'success'])
    ->middleware('throttle:payment-return')
    ->name('payment.esewa.success');
Route::get('/payment/esewa/failure', [PaymentController::class, 'failure'])
    ->middleware('throttle:payment-return')
    ->name('payment.esewa.failure');

Route::post('/booking/{booking:confirmation_code}/pay/khalti', [PaymentController::class, 'khaltiInitiate'])
    ->middleware(['throttle:payment-initiate', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.khalti.initiate');
Route::get('/payment/khalti/return', [PaymentController::class, 'khaltiReturn'])
    ->middleware('throttle:payment-return')
    ->name('payment.khalti.return');

Route::post('/booking/{booking:confirmation_code}/pay/fonepay', [PaymentController::class, 'fonepayInitiate'])
    ->middleware(['throttle:payment-initiate', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.fonepay.initiate');
Route::get('/payment/fonepay/return', [PaymentController::class, 'fonepayReturn'])
    ->middleware('throttle:payment-return')
    ->name('payment.fonepay.return');

Route::post('/booking/{booking:confirmation_code}/pay/cash', [PaymentController::class, 'cashInitiate'])
    ->middleware(['throttle:payment-initiate', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.cash.initiate');

Route::post('/booking/{booking:confirmation_code}/pay/bank-transfer', [PaymentController::class, 'bankTransferInitiate'])
    ->middleware(['throttle:payment-initiate', 'throttle:offline-hold', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.bank.initiate');
Route::get('/booking/{booking:confirmation_code}/pay/bank-transfer', [PaymentController::class, 'bankTransferShow'])
    ->middleware(['throttle:booking-view', 'booking.access'])
    ->missing($bookingDenied)
    ->name('booking.pay.bank.show');

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

    // Password recovery by SMS code (verified phone numbers only).
    Route::post('/forgot-password/phone', [PhonePasswordResetController::class, 'sendCode'])
        ->middleware('throttle:phone-otp-send')
        ->name('password.phone.send');
    Route::post('/forgot-password/phone/resend', [PhonePasswordResetController::class, 'resendCode'])
        ->middleware('throttle:phone-otp-send')
        ->name('password.phone.resend');
    Route::get('/forgot-password/phone/verify', [PhonePasswordResetController::class, 'showVerify'])->name('password.phone.verify');
    Route::post('/forgot-password/phone/verify', [PhonePasswordResetController::class, 'verifyCode'])
        ->middleware('throttle:phone-otp-verify')
        ->name('password.phone.verify.store');
    Route::get('/forgot-password/phone/reset', [PhonePasswordResetController::class, 'showReset'])->name('password.phone.reset');
    Route::post('/forgot-password/phone/reset', [PhonePasswordResetController::class, 'reset'])
        ->middleware('throttle:auth-attempt')
        ->name('password.phone.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/my-bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::post('/my-bookings/{booking:confirmation_code}/cancel', [AccountController::class, 'cancelBooking'])
        ->name('account.bookings.cancel');

    Route::get('/my-bookings/{booking:confirmation_code}/invoice', [InvoiceController::class, 'account'])
        ->name('account.bookings.invoice');
    Route::get('/my-account/profile', [AccountProfileController::class, 'edit'])->name('account.profile');
    Route::put('/my-account/profile', [AccountProfileController::class, 'update'])
        ->middleware('throttle:account-security')
        ->name('account.profile.update');
    Route::put('/my-account/password', [AccountProfileController::class, 'updatePassword'])
        ->middleware('throttle:account-security')
        ->name('account.password.update');
    Route::get('/my-account/phone', [AccountPhoneController::class, 'edit'])->name('account.phone');
    Route::post('/my-account/phone', [AccountPhoneController::class, 'sendCode'])
        ->middleware('throttle:phone-otp-send')
        ->name('account.phone.send');
    Route::post('/my-account/phone/resend', [AccountPhoneController::class, 'resendCode'])
        ->middleware('throttle:phone-otp-send')
        ->name('account.phone.resend');
    Route::post('/my-account/phone/verify', [AccountPhoneController::class, 'verifyCode'])
        ->middleware('throttle:phone-otp-verify')
        ->name('account.phone.verify');
});

Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms-conditions', [PageController::class, 'terms'])->name('terms');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', function () {
    return response()
        ->view('robots')
        ->header('Content-Type', 'text/plain');
})->name('robots');
