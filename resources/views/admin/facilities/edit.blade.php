@extends('layouts.admin')

@section('title', 'Edit Facility')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.facilities.update', $facility) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.facilities._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Changes</button>
            <a href="{{ route('admin.facilities.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
