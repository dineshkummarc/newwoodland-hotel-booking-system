<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImageController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('rooms', RoomController::class)->except(['show']);
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

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });
});
