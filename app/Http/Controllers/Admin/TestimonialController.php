<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Models\Testimonial;
use App\Services\AuditLogService;
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

    public function store(StoreTestimonialRequest $request, AuditLogService $auditLog)
    {
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('testimonials', 'public');
        }

        $testimonial = Testimonial::create($data);

        $auditLog->log('testimonial.created', 'testimonial', $testimonial->id, "Created testimonial from {$testimonial->name}.");

        Cache::forget('home.page.data');

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(StoreTestimonialRequest $request, Testimonial $testimonial, AuditLogService $auditLog)
    {
        $before = $testimonial->getAttributes();
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            if ($testimonial->avatar_path && ! str_starts_with($testimonial->avatar_path, 'http')) {
                Storage::disk('public')->delete($testimonial->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('testimonials', 'public');
        }

        $testimonial->update($data);

        $auditLog->logChanges('testimonial.updated', 'testimonial', $testimonial, "Updated testimonial from {$testimonial->name}.", $before);

        Cache::forget('home.page.data');

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial, AuditLogService $auditLog)
    {
        if ($testimonial->avatar_path && ! str_starts_with($testimonial->avatar_path, 'http')) {
            Storage::disk('public')->delete($testimonial->avatar_path);
        }

        $testimonial->delete();

        $auditLog->log('testimonial.deleted', 'testimonial', $testimonial->id, "Deleted testimonial from {$testimonial->name}.");

        Cache::forget('home.page.data');

        return back()->with('success', 'Testimonial deleted.');
    }
}
