<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `discount_percent` / `discount_amount`: the first-booking discount
     * applied when the booking was made (total_price is after discount).
     * `deposit_percent`: set when the guest chose to pay part now (e.g. 50%)
     * and the rest in cash at the hotel; null = pay in full.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('nightly_rate');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
            $table->unsignedTinyInteger('deposit_percent')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'deposit_percent']);
        });
    }
};
