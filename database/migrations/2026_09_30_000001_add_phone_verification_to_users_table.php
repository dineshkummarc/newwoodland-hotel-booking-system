<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: the existing free-form `phone` contact column is left
     * untouched. `phone_e164` holds a number the user has proven they own
     * (via SMS code), which is what phone password recovery trusts — it is
     * only ever set on successful verification, so an unverified signup
     * can't squat someone else's number. Unique, so one verified phone maps
     * to exactly one account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_e164', 20)->nullable()->unique()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_e164');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verified_at']);
        });
    }
};
