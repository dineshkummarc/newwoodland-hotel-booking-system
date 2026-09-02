<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function bookings()
    {
        $bookings = Auth::user()->bookings()->with('room')->latest()->paginate(10);

        return view('account.bookings', compact('bookings'));
    }
}
