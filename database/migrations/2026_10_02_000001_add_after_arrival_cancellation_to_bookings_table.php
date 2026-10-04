<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cancellation charge (% of the booking amount) applied when a stay
     * was cancelled AFTER the guest arrived (checked in) — frozen on the
     * booking (config bookings.after_arrival_cancellation_percent, 50%).
     * Null for every other booking: pre-arrival cancellations keep using the
     * Settings deduction exactly as before.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('cancellation_charge_percent')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancellation_charge_percent');
        });
    }
};
