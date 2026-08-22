<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function about()
    {
        return view('about');
    }

    public function terms()
    {
        return view('terms-condition');
    }

    public function privacy()
    {
        return view('privacy-policy');
    }
}
