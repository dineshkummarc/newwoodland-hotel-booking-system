<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each `rooms` row is one physical, bookable room. `room_number` lets
     * staff tell apart rooms of the same type (e.g. the two VIP single
     * rooms). Price and guest limit become optional so a room the hotel has
     * confirmed can be listed before its exact rate/occupancy is known — a
     * room without a price is shown but cannot be booked (Room::scopeBookable).
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_number', 50)->nullable()->after('name');
            $table->decimal('price_per_night', 10, 2)->nullable()->change();
            $table->unsignedTinyInteger('max_guests')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('room_number');
            $table->decimal('price_per_night', 10, 2)->nullable(false)->change();
            $table->unsignedTinyInteger('max_guests')->nullable(false)->default(2)->change();
        });
    }
};
