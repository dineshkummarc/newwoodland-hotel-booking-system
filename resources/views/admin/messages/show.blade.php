@extends('layouts.admin')

@section('title', 'Message from ' . $message->name)

@section('content')
<div class="table-section">
    <table class="table">
        <tr><td class="text-muted">Name</td><td>{{ $message->name }}</td></tr>
        <tr><td class="text-muted">Email</td><td><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></td></tr>
        <tr><td class="text-muted">Phone</td><td>{{ $message->phone ?: '—' }}</td></tr>
        <tr><td class="text-muted">Subject</td><td>{{ $message->subject ?: '—' }}</td></tr>
        <tr><td class="text-muted">Received</td><td>{{ $message->created_at->format('d M Y, g:i A') }}</td></tr>
    </table>
    <hr>
    <p style="white-space: pre-line;">{{ $message->message }}</p>

    <div class="mt-4">
        <a href="mailto:{{ $message->email }}" class="btn-admin-primary">Reply by Email</a>
        <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary">Back to Messages</a>
        <form action="{{ route('admin.messages.destroy', $message) }}" method="POST" class="d-inline" data-confirm-delete="Delete this message?">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    </div>
</div>
@endsection
