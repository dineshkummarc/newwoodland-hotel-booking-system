<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryImageRequest;
use App\Models\GalleryImage;
use App\Services\AuditLogService;
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

    public function store(StoreGalleryImageRequest $request, AuditLogService $auditLog)
    {
        $path = $request->file('image')->store('gallery', 'public');

        $image = GalleryImage::create([
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
            'path' => $path,
            'sort_order' => (GalleryImage::max('sort_order') ?? 0) + 1,
        ]);

        $auditLog->log('gallery.created', 'gallery_image', $image->id, "Added gallery image \"{$image->title}\".", [
            'category' => $image->category,
            'path' => $path,
        ]);

        Cache::forget('home.page.data');

        return redirect()->route('admin.gallery.index')->with('success', 'Image added to gallery.');
    }

    public function edit(GalleryImage $gallery)
    {
        return view('admin.gallery.edit', ['image' => $gallery]);
    }

    public function update(StoreGalleryImageRequest $request, GalleryImage $gallery, AuditLogService $auditLog)
    {
        $before = $gallery->getAttributes();
        $data = [
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
        ];

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($gallery->path);
            $data['path'] = $request->file('image')->store('gallery', 'public');
        }

        $gallery->update($data);

        $auditLog->logChanges('gallery.updated', 'gallery_image', $gallery, "Updated gallery image \"{$gallery->title}\".", $before);

        Cache::forget('home.page.data');

        return redirect()->route('admin.gallery.index')->with('success', 'Gallery image updated.');
    }

    public function destroy(GalleryImage $gallery, AuditLogService $auditLog)
    {
        Storage::disk('public')->delete($gallery->path);
        $gallery->delete();

        $auditLog->log('gallery.deleted', 'gallery_image', $gallery->id, "Deleted gallery image \"{$gallery->title}\".", [
            'path' => $gallery->path,
        ]);

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
