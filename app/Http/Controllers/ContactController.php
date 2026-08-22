<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\NewContactMessageMail;
use App\Models\ContactMessage;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function index()
    {
        return view('contact');
    }

    public function store(StoreContactRequest $request, SettingsService $settingsService)
    {
        $message = ContactMessage::create($request->validated());

        $adminEmail = $settingsService->current()->site_email;

        if ($adminEmail) {
            Mail::to($adminEmail)->send(new NewContactMessageMail($message));
        }

        return back()->with('success', 'Thank you for reaching out. Our team will contact you shortly.');
    }
}
