<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Support\Facades\Cache;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Cache::remember('facilities.page.data', now()->addMinutes(30), function () {
            return Facility::orderBy('sort_order')->get();
        });

        return view('facilities', compact('facilities'));
    }
}
