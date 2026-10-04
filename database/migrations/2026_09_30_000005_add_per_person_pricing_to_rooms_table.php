<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some rooms are charged per guest rather than per room (e.g. the
     * Package Room: NPR 1,000 per person per night for groups of 4–5).
     * `price_per_night` stays the rate; `price_per_person` says whether it is
     * multiplied by the party size. `min_guests` pairs with the existing
     * `max_guests` for rooms only offered to a party of a certain size.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('price_per_person')->default(false)->after('price_per_night');
            $table->unsignedTinyInteger('min_guests')->nullable()->after('max_guests');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['price_per_person', 'min_guests']);
        });
    }
};
