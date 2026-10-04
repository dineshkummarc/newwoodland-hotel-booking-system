<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client-confirmed hotel figures shown on the public site (About page)
     * and the optional bank QR image for Bank Transfer — all admin-editable
     * on Admin → Settings rather than hard-coded in views.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_rooms')->nullable()->after('hotel_description');
            $table->decimal('room_price_min', 10, 2)->nullable()->after('total_rooms');
            $table->decimal('room_price_max', 10, 2)->nullable()->after('room_price_min');
            $table->string('typical_daily_guests', 50)->nullable()->after('room_price_max');
            $table->string('bank_qr_path')->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['total_rooms', 'room_price_min', 'room_price_max', 'typical_daily_guests', 'bank_qr_path']);
        });
    }
};
