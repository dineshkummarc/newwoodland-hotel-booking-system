<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Earlier development seeded stock photos (not Newwoodland) as room,
     * gallery and site images, and Unsplash portraits as testimonial avatars.
     * Remove only those exact seeded records and files; anything uploaded
     * through the admin (stored under hashed file names) is left untouched.
     * Pages show a labelled "Client Photo Required" placeholder instead.
     */
    protected const ROOM_IMAGES = [
        'rooms/deluxe-room.jpg',
        'rooms/executive-room.jpg',
        'rooms/family-suite.jpg',
    ];

    protected const GALLERY_IMAGES = [
        'gallery/deluxe-room.jpg',
        'gallery/executive-room.jpg',
        'gallery/family-suite.jpg',
        'gallery/pool.jpg',
        'gallery/restaurant.jpg',
        'gallery/garden.jpg',
    ];

    protected const SITE_IMAGES = [
        'site/hero-1.jpg',
        'site/about.jpg',
    ];

    public function up(): void
    {
        DB::table('room_images')->whereIn('path', self::ROOM_IMAGES)->delete();
        DB::table('gallery_images')->whereIn('path', self::GALLERY_IMAGES)->delete();
        DB::table('testimonials')->where('avatar_path', 'like', 'https://images.unsplash.com/%')->update(['avatar_path' => null]);

        Storage::disk('public')->delete([...self::ROOM_IMAGES, ...self::GALLERY_IMAGES, ...self::SITE_IMAGES]);
    }

    public function down(): void
    {
        // Nothing to restore: the removed images were stock photos, not hotel data.
    }
};
