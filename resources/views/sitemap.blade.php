<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($staticRoutes as $item)
    <url>
        <loc>{{ route($item['route']) }}</loc>
        <priority>{{ $item['priority'] }}</priority>
    </url>
@endforeach
@foreach($rooms as $room)
    <url>
        <loc>{{ route('rooms.show', $room->slug) }}</loc>
        <lastmod>{{ $room->updated_at->toAtomString() }}</lastmod>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
