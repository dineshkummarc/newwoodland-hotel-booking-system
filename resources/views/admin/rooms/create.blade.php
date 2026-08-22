@extends('layouts.admin')

@section('title', 'Add Room')

@section('content')
<div class="admin-panel">
    <form id="roomForm" action="{{ route('admin.rooms.store') }}" method="POST">
        @csrf
        @include('admin.rooms._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Create Room</button>
            <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
