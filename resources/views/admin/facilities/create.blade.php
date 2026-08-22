@extends('layouts.admin')

@section('title', 'Add Facility')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.facilities.store') }}" method="POST">
        @csrf
        @include('admin.facilities._form', ['facility' => new \App\Models\Facility])
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Create Facility</button>
            <a href="{{ route('admin.facilities.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
