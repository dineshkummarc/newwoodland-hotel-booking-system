@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th></th><th>Name</th><th>Subject</th><th>Received</th><th></th></tr></thead>
        <tbody>
            @forelse($messages as $message)
            <tr class="{{ $message->is_read ? '' : 'fw-bold' }}">
                <td>@if(!$message->is_read)<span class="status pending">New</span>@endif</td>
                <td>{{ $message->name }}<br><span class="text-muted small fw-normal">{{ $message->email }}</span></td>
                <td>{{ $message->subject ?: '—' }}</td>
                <td class="fw-normal">{{ $message->created_at->diffForHumans() }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.messages.show', $message) }}" class="btn btn-sm btn-outline-dark">View</a>
                    <form action="{{ route('admin.messages.destroy', $message) }}" method="POST" class="d-inline" data-confirm-delete="Delete this message?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted">No messages yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $messages->links() }}
</div>
@endsection
