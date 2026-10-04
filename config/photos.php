<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site photography (CLIENT INPUT REQUIRED)
    |--------------------------------------------------------------------------
    |
    | Only genuine Newwoodland photographs are shown on the site. Each fixed
    | photo position is a named "slot": it shows its photo only when the file
    | exists on the public disk (storage/app/public/...); until then a labelled
    | "Client Photo Required" placeholder is shown — never a stock photo.
    |
    | Upload the hotel's photos to these paths (JPG, landscape, about 1920px
    | wide for the hero, 1200px for the others, compressed to under ~400 KB):
    |
    |   storage/app/public/site/home-hero.jpg    hotel exterior / main entrance
    |   storage/app/public/site/home-intro.jpg   hotel property, wide view
    |
    | About page slots are in config/about.php. Room photos (Admin → Rooms →
    | Edit) and gallery photos (Admin → Gallery) are uploaded in the admin.
    |
    | No code change is needed; the pages pick the files up automatically.
    |
    */

    'home_hero' => [
        'path' => 'site/home-hero.jpg',
        'label' => 'Hotel Exterior / Main Entrance',
        'alt' => 'Exterior and main entrance of Newwoodland Hotel & Lounge, Bhadrapur',
    ],

    'home_intro' => [
        'path' => 'site/home-intro.jpg',
        'label' => 'Hotel Property / Wide View',
        'alt' => 'Wide view of the Newwoodland Hotel & Lounge property',
    ],

];
