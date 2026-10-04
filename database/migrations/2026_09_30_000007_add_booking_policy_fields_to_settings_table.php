<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-editable booking policy figures: the first-booking discount,
     * how much of a booking is kept when it is cancelled, and the guest
     * satisfaction figure shown on the website.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('first_booking_discount_percent', 5, 2)->nullable()->after('cancellation_policy_text');
            $table->unsignedTinyInteger('cancellation_deduction_percent')->nullable()->after('first_booking_discount_percent');
            $table->string('guest_satisfaction', 50)->nullable()->after('typical_daily_guests');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['first_booking_discount_percent', 'cancellation_deduction_percent', 'guest_satisfaction']);
        });
    }
};
