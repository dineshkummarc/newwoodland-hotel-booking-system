<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $staticRoutes = [
                ['route' => 'home', 'priority' => '1.0'],
                ['route' => 'about', 'priority' => '0.7'],
                ['route' => 'rooms.index', 'priority' => '0.9'],
                ['route' => 'facilities', 'priority' => '0.7'],
                ['route' => 'gallery', 'priority' => '0.6'],
                ['route' => 'contact', 'priority' => '0.6'],
                ['route' => 'booking', 'priority' => '0.9'],
                ['route' => 'privacy', 'priority' => '0.3'],
                ['route' => 'terms', 'priority' => '0.3'],
            ];

            $rooms = Room::active()->get();

            return view('sitemap', compact('staticRoutes', 'rooms'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}
