@extends('layouts.admin')

@section('title', 'Edit Pricing Rule')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.room-rates.update', $rate) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.room-rates._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Rule</button>
            <a href="{{ route('admin.room-rates.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
