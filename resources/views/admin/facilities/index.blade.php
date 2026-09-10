@extends('layouts.admin')

@section('title', 'Facilities')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.facilities.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add Facility</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Icon</th><th>Title</th><th>Category</th><th>Homepage</th><th>Order</th><th></th></tr></thead>
        <tbody>
            @forelse($facilities as $facility)
            <tr>
                <td><i class="bi {{ $facility->icon }} fs-4 text-emerald"></i></td>
                <td><strong>{{ $facility->title }}</strong><br><span class="text-muted small">{{ Str::limit($facility->description, 60) }}</span></td>
                <td><span class="text-muted small">{{ $facility->categoryLabel() }}</span></td>
                <td>{{ $facility->show_on_homepage ? 'Yes' : 'No' }}</td>
                <td>
                    <form action="{{ route('admin.facilities.move', [$facility, 'up']) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-up"></i></button></form>
                    <form action="{{ route('admin.facilities.move', [$facility, 'down']) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-down"></i></button></form>
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.facilities.destroy', $facility) }}" method="POST" class="d-inline" data-confirm-delete="Delete this facility?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-muted">No facilities yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
