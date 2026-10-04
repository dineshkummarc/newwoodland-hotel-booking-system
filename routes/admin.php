<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FrontDeskController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\GuestDocumentController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Admin\PhonePasswordResetController;
use App\Http\Controllers\Admin\ProfilePhoneController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImageController;
use App\Http\Controllers\Admin\RoomRateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.store');

        Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:admin-password-reset')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:admin-password-reset')->name('password.update');

        // Password recovery by SMS code (active staff, verified phone + email).
        Route::post('forgot-password/phone', [PhonePasswordResetController::class, 'sendCode'])->middleware('throttle:phone-otp-send')->name('password.phone.send');
        Route::post('forgot-password/phone/resend', [PhonePasswordResetController::class, 'resendCode'])->middleware('throttle:phone-otp-send')->name('password.phone.resend');
        Route::get('forgot-password/phone/verify', [PhonePasswordResetController::class, 'showVerify'])->name('password.phone.verify');
        Route::post('forgot-password/phone/verify', [PhonePasswordResetController::class, 'verifyCode'])->middleware('throttle:phone-otp-verify')->name('password.phone.verify.store');
        Route::get('forgot-password/phone/reset', [PhonePasswordResetController::class, 'showReset'])->name('password.phone.reset');
        Route::post('forgot-password/phone/reset', [PhonePasswordResetController::class, 'reset'])->middleware('throttle:admin-password-reset')->name('password.phone.update');
    });

    Route::middleware(['auth:admin', 'admin.active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('profile/phone', [ProfilePhoneController::class, 'sendCode'])->middleware('throttle:phone-otp-send')->name('profile.phone.send');
        Route::post('profile/phone/resend', [ProfilePhoneController::class, 'resendCode'])->middleware('throttle:phone-otp-send')->name('profile.phone.resend');
        Route::post('profile/phone/verify', [ProfilePhoneController::class, 'verifyCode'])->middleware('throttle:phone-otp-verify')->name('profile.phone.verify');

        // Front desk role gets full access to bookings/check-in-out/guests —
        // everything below is also reachable by admin/super_admin.
        Route::middleware('admin.role:super_admin,admin,front_desk')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
            Route::get('bookings/create', [BookingController::class, 'create'])->name('bookings.create');
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
            Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
            Route::put('bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
            Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
            Route::patch('bookings/{booking}/check-in', [BookingController::class, 'checkIn'])->name('bookings.check-in');
            Route::patch('bookings/{booking}/check-out', [BookingController::class, 'checkOut'])->name('bookings.check-out');
            Route::patch('bookings/{booking}/cancel-stay', [BookingController::class, 'cancelStay'])->name('bookings.cancel-stay');
            Route::patch('bookings/{booking}/no-show', [BookingController::class, 'markNoShow'])->name('bookings.no-show');
            Route::patch('bookings/{booking}/complete', [BookingController::class, 'markCompleted'])->name('bookings.complete');
            Route::patch('bookings/{booking}/record-payment', [BookingController::class, 'recordPayment'])->name('bookings.record-payment');
            Route::patch('bookings/{booking}/reassign-room', [BookingController::class, 'reassignRoom'])->name('bookings.reassign-room');
            Route::post('bookings/{booking}/charges', [BookingController::class, 'addCharge'])->name('bookings.charges.store');
            Route::get('bookings/{booking}/invoice', [BookingController::class, 'invoice'])->name('bookings.invoice');
            Route::post('bookings/{booking}/documents', [GuestDocumentController::class, 'store'])->name('bookings.documents.store');
            Route::get('bookings/{booking}/documents/{document}/file', [GuestDocumentController::class, 'download'])->name('bookings.documents.download');

            Route::get('front-desk', [FrontDeskController::class, 'index'])->name('front-desk.index');

            Route::get('guests', [GuestController::class, 'index'])->name('guests.index');
            Route::get('guests/profile', [GuestController::class, 'show'])->name('guests.show');
            Route::put('guests/profile', [GuestController::class, 'update'])->name('guests.update');
        });

        // Everything else (catalog/content management, settings, reports)
        // is restricted to full back-office roles — front_desk is refused
        // with a 403 server-side, not merely hidden from the sidebar.
        Route::middleware('admin.role:super_admin,admin')->group(function () {
            // Money-reducing booking actions are not front-desk actions.
            Route::put('bookings/{booking}/charges/{charge}', [BookingController::class, 'updateCharge'])->name('bookings.charges.update');
            Route::patch('bookings/{booking}/charges/{charge}/void', [BookingController::class, 'voidCharge'])->name('bookings.charges.void');
            Route::delete('bookings/{booking}/documents/{document}', [GuestDocumentController::class, 'destroy'])->name('bookings.documents.destroy');
            Route::post('bookings/{booking}/refunds', [RefundController::class, 'store'])->name('bookings.refunds.store');
            Route::patch('bookings/{booking}/refunds/{refund}/complete', [RefundController::class, 'complete'])->name('bookings.refunds.complete');
            Route::patch('bookings/{booking}/refunds/{refund}/cancel', [RefundController::class, 'cancel'])->name('bookings.refunds.cancel');
            // Payment review queue (late/duplicate/unverifiable payments).
            Route::patch('payments/{payment}/resolve-review', [PaymentReviewController::class, 'resolve'])->name('payments.resolve-review');

            Route::resource('rooms', RoomController::class)->except(['show']);
            Route::resource('room-rates', RoomRateController::class)->except(['show']);
            Route::patch('rooms/{room}/toggle-active', [RoomController::class, 'toggleActive'])->name('rooms.toggle-active');
            Route::post('rooms/{room}/images', [RoomImageController::class, 'store'])->name('rooms.images.store');
            Route::delete('rooms/{room}/images/{image}', [RoomImageController::class, 'destroy'])->name('rooms.images.destroy');
            Route::patch('rooms/{room}/images/{image}/primary', [RoomImageController::class, 'makePrimary'])->name('rooms.images.primary');
            Route::post('rooms/{room}/images/reorder', [RoomImageController::class, 'reorder'])->name('rooms.images.reorder');

            Route::resource('gallery', GalleryController::class)->except(['show']);
            Route::post('gallery/{gallery}/move/{direction}', [GalleryController::class, 'move'])->name('gallery.move');

            Route::resource('facilities', FacilityController::class)->except(['show']);
            Route::post('facilities/{facility}/move/{direction}', [FacilityController::class, 'move'])->name('facilities.move');

            Route::resource('testimonials', TestimonialController::class)->except(['show']);

            Route::resource('faqs', FaqController::class)->except(['show']);
            Route::post('faqs/{faq}/move/{direction}', [FaqController::class, 'move'])->name('faqs.move');

            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/bookings', [ReportController::class, 'bookings'])->name('reports.bookings');
            Route::get('reports/bookings/export', [ReportController::class, 'bookingsExport'])->name('reports.bookings.export');
            Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');
            Route::get('reports/room-performance', [ReportController::class, 'roomPerformance'])->name('reports.room-performance');
            Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
            Route::get('reports/payments/export', [ReportController::class, 'paymentsExport'])->name('reports.payments.export');

            Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
            Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
            Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });

        // Staff account management: super_admin only, not even the
        // full-access 'admin' role — assigning roles/deactivating staff is
        // reserved for the top tier.
        Route::middleware('admin.role:super_admin')->prefix('admin-users')->name('admin-users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('create', [AdminUserController::class, 'create'])->name('create');
            Route::post('/', [AdminUserController::class, 'store'])->name('store');
            Route::get('{adminUser}/edit', [AdminUserController::class, 'edit'])->name('edit');
            Route::put('{adminUser}', [AdminUserController::class, 'update'])->name('update');
            Route::patch('{adminUser}/deactivate', [AdminUserController::class, 'deactivate'])->name('deactivate');
            Route::patch('{adminUser}/reactivate', [AdminUserController::class, 'reactivate'])->name('reactivate');
        });
    });
});
