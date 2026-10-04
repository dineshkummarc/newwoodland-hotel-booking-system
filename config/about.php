<?php

return [

    /*
    |--------------------------------------------------------------------------
    | About page photography (CLIENT INPUT REQUIRED)
    |--------------------------------------------------------------------------
    |
    | Every image on the About page is a named "slot". A slot shows its photo
    | only when the file exists on the public disk (storage/app/public/...);
    | until then a branded placeholder is shown — never a stock photo passed
    | off as Newwoodland.
    |
    | To add the hotel's real photos, upload them to these paths (JPG,
    | landscape, at least 1600px wide for the hero/visual break):
    |
    |   storage/app/public/about/hero.jpg          shared hotel spaces
    |   storage/app/public/about/story.jpg         wide property view
    |   storage/app/public/about/owners/<name>.jpg owner portraits (square)
    |
    | (Facility photos belong on the Facilities page, not here.)
    |
    | No code change is needed; the page picks them up automatically.
    |
    */

    'slots' => [
        'hero' => 'about/hero.jpg',
        'story' => 'about/story.jpg',
    ],

    // Client-confirmed owners. Names, role and (where confirmed) years of
    // experience only: no biographies,
    // qualifications or quotes have been provided (CLIENT INPUT REQUIRED
    // for portraits and any short personal message).
    'owners' => [
        ['name' => 'Ambika Pokharel', 'role' => 'Founder', 'experience' => '10 years of experience', 'photo' => 'about/owners/ambika-pokharel.jpg', 'message' => null],
        ['name' => 'Raj Kumar Rai', 'role' => 'Founder', 'experience' => '3+ years of experience', 'photo' => 'about/owners/raj-kumar-rai.jpg', 'message' => null],
        ['name' => 'Hem Bahadur Karki', 'role' => 'Founder', 'experience' => '3+ years of experience', 'photo' => 'about/owners/hem-bahadur-karki.jpg', 'message' => null],
    ],

];
