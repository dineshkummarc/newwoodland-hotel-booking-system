<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::latest()->get();

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        return view('admin.testimonials.create');
    }

    public function store(StoreTestimonialRequest $request)
    {
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('testimonials', 'public');
        }

        Testimonial::create($data);

        Cache::forget('home.page.data');

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(StoreTestimonialRequest $request, Testimonial $testimonial)
    {
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            if ($testimonial->avatar_path && ! str_starts_with($testimonial->avatar_path, 'http')) {
                Storage::disk('public')->delete($testimonial->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('testimonials', 'public');
        }

        $testimonial->update($data);

        Cache::forget('home.page.data');

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial)
    {
        if ($testimonial->avatar_path && ! str_starts_with($testimonial->avatar_path, 'http')) {
            Storage::disk('public')->delete($testimonial->avatar_path);
        }

        $testimonial->delete();

        Cache::forget('home.page.data');

        return back()->with('success', 'Testimonial deleted.');
    }
}
