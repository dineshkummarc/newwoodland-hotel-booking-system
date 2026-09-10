<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('category')->nullable()->after('icon');
        });

        // Backfill the hotel's existing, real facility records (seeded by
        // FacilitySeeder) with their correct category — matched by exact
        // title so this is safe to run against any environment that has
        // these seeded rows, and a no-op otherwise.
        $categories = [
            'Free High-Speed Wi-Fi' => 'connectivity',
            'Outdoor Swimming Pool' => 'wellness_recreation',
            'Restaurant & Bar' => 'dining',
            'Luxury Rooms' => 'guest_services',
            'Free Safe Parking' => 'transportation_parking',
            'Airport Transportation' => 'transportation_parking',
        ];

        foreach ($categories as $title => $category) {
            DB::table('facilities')->where('title', $title)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
