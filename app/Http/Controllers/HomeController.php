<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\Room;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        $data = Cache::remember('home.page.data', now()->addMinutes(30), function () {
            return [
                'featuredRooms' => Room::active()->with(['images', 'amenities'])
                    ->where('is_featured', true)
                    ->orderBy('created_at', 'desc')
                    ->take(6)
                    ->get(),
                'facilities' => Facility::homepage()->orderBy('sort_order')->get(),
                'testimonials' => Testimonial::orderBy('created_at', 'desc')->take(6)->get(),
                'faqs' => Faq::orderBy('sort_order')->get(),
                'galleryPreview' => GalleryImage::orderBy('sort_order')->take(6)->get(),
            ];
        });

        return view('home', $data);
    }
}
