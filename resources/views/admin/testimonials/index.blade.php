@extends('layouts.admin')

@section('title', 'Testimonials')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.testimonials.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add Testimonial</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Guest</th><th>Rating</th><th>Content</th><th></th></tr></thead>
        <tbody>
            @forelse($testimonials as $testimonial)
            <tr>
                <td>
                    <img src="{{ $testimonial->avatar_path ? (str_starts_with($testimonial->avatar_path, 'http') ? $testimonial->avatar_path : asset('storage/'.$testimonial->avatar_path)) : 'https://ui-avatars.com/api/?name='.urlencode($testimonial->name) }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="{{ $testimonial->name }}">
                    {{ $testimonial->name }}<br><span class="text-muted small">{{ $testimonial->location }}</span>
                </td>
                <td>{{ str_repeat('★', $testimonial->rating) }}</td>
                <td>{{ Str::limit($testimonial->content, 60) }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.testimonials.destroy', $testimonial) }}" method="POST" class="d-inline" data-confirm-delete="Delete this testimonial?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-muted">No testimonials yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
