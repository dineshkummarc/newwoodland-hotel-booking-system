@extends('layouts.admin')

@section('title', 'Add Pricing Rule')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.room-rates.store') }}" method="POST">
        @csrf
        @include('admin.room-rates._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Create Rule</button>
            <a href="{{ route('admin.room-rates.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
