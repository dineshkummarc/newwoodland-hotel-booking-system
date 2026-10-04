<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMS password recovery for staff, on the same one-time-code machinery
     * customers use:
     *  - admins.phone_e164 / phone_verified_at: a staff member's own number,
     *    usable for recovery only once proven by an SMS code;
     *  - phone_otps.admin_id: a code can now belong to a staff account
     *    instead of a customer (exactly one of user_id / admin_id is set).
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('phone_e164', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_e164');
        });

        Schema::table('phone_otps', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('admins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('phone_otps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verified_at']);
        });
    }
};
