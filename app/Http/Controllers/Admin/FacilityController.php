<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacilityRequest;
use App\Models\Facility;
use Illuminate\Support\Facades\Cache;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::orderBy('sort_order')->get();

        return view('admin.facilities.index', compact('facilities'));
    }

    public function create()
    {
        return view('admin.facilities.create');
    }

    public function store(StoreFacilityRequest $request)
    {
        $data = $request->validated();
        $data['show_on_homepage'] = $request->boolean('show_on_homepage');
        $data['sort_order'] = (Facility::max('sort_order') ?? 0) + 1;

        Facility::create($data);

        Cache::forget('home.page.data');
        Cache::forget('facilities.page.data');

        return redirect()->route('admin.facilities.index')->with('success', 'Facility created.');
    }

    public function edit(Facility $facility)
    {
        return view('admin.facilities.edit', compact('facility'));
    }

    public function update(StoreFacilityRequest $request, Facility $facility)
    {
        $data = $request->validated();
        $data['show_on_homepage'] = $request->boolean('show_on_homepage');

        $facility->update($data);

        Cache::forget('home.page.data');
        Cache::forget('facilities.page.data');

        return redirect()->route('admin.facilities.index')->with('success', 'Facility updated.');
    }

    public function destroy(Facility $facility)
    {
        $facility->delete();

        Cache::forget('home.page.data');
        Cache::forget('facilities.page.data');

        return back()->with('success', 'Facility deleted.');
    }

    public function move(Facility $facility, string $direction)
    {
        $swap = $direction === 'up'
            ? Facility::where('sort_order', '<', $facility->sort_order)->orderBy('sort_order', 'desc')->first()
            : Facility::where('sort_order', '>', $facility->sort_order)->orderBy('sort_order')->first();

        if ($swap) {
            [$a, $b] = [$facility->sort_order, $swap->sort_order];
            $facility->update(['sort_order' => $b]);
            $swap->update(['sort_order' => $a]);
        }

        Cache::forget('home.page.data');
        Cache::forget('facilities.page.data');

        return back();
    }
}
