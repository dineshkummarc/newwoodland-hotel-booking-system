<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('site_phone_secondary')->nullable()->after('site_phone');
            $table->string('hotel_name')->nullable()->after('site_phone_secondary');
            $table->text('hotel_description')->nullable()->after('hotel_name');
            $table->text('cancellation_policy_text')->nullable()->after('cancellation_window_hours');
            $table->string('bank_name')->nullable()->after('cancellation_policy_text');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_branch')->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'site_phone_secondary', 'hotel_name', 'hotel_description',
                'cancellation_policy_text', 'bank_name', 'bank_account_name',
                'bank_account_number', 'bank_branch',
            ]);
        });
    }
};
