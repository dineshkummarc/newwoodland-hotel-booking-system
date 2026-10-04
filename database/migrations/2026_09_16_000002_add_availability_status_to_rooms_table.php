<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_active` controls public visibility/catalog listing. This is a
     * separate concern: whether the room can currently take NEW bookings.
     * A room can stay listed (is_active) while temporarily unbookable
     * (maintenance / out_of_service) without losing its booking history.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('availability_status')->default('available')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('availability_status');
        });
    }
};
