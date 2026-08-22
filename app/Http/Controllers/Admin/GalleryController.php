<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryImageRequest;
use App\Models\GalleryImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index()
    {
        $images = GalleryImage::orderBy('sort_order')->paginate(12);

        return view('admin.gallery.index', compact('images'));
    }

    public function create()
    {
        return view('admin.gallery.create');
    }

    public function store(StoreGalleryImageRequest $request)
    {
        $path = $request->file('image')->store('gallery', 'public');

        GalleryImage::create([
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
            'path' => $path,
            'sort_order' => (GalleryImage::max('sort_order') ?? 0) + 1,
        ]);

        Cache::forget('home.page.data');

        return redirect()->route('admin.gallery.index')->with('success', 'Image added to gallery.');
    }

    public function edit(GalleryImage $gallery)
    {
        return view('admin.gallery.edit', ['image' => $gallery]);
    }

    public function update(StoreGalleryImageRequest $request, GalleryImage $gallery)
    {
        $data = [
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
        ];

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($gallery->path);
            $data['path'] = $request->file('image')->store('gallery', 'public');
        }

        $gallery->update($data);

        Cache::forget('home.page.data');

        return redirect()->route('admin.gallery.index')->with('success', 'Gallery image updated.');
    }

    public function destroy(GalleryImage $gallery)
    {
        Storage::disk('public')->delete($gallery->path);
        $gallery->delete();

        Cache::forget('home.page.data');

        return back()->with('success', 'Gallery image deleted.');
    }

    public function move(GalleryImage $gallery, string $direction)
    {
        $this->moveSortOrder($gallery, $direction);

        Cache::forget('home.page.data');

        return back();
    }

    protected function moveSortOrder($model, string $direction): void
    {
        $swap = $direction === 'up'
            ? $model->newQuery()->where('sort_order', '<', $model->sort_order)->orderBy('sort_order', 'desc')->first()
            : $model->newQuery()->where('sort_order', '>', $model->sort_order)->orderBy('sort_order')->first();

        if ($swap) {
            [$a, $b] = [$model->sort_order, $swap->sort_order];
            $model->update(['sort_order' => $b]);
            $swap->update(['sort_order' => $a]);
        }
    }
}
