@extends('layouts.admin')

@section('title', 'Activity Log')

@section('content')
<div class="table-section">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="type" class="form-select">
                <option value="">All areas</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search description or action">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <table class="table align-middle">
        <thead><tr><th>When</th><th>Staff</th><th>Action</th><th>Details</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td class="small text-nowrap">{{ $log->created_at->format('d M Y, H:i') }}</td>
                <td class="small">{{ $log->admin?->name ?? 'System' }}</td>
                <td><code class="small">{{ $log->action }}</code></td>
                <td class="small">
                    {{ $log->description }}
                    @if($log->meta)
                        <details class="mt-1">
                            <summary class="text-muted">Changes</summary>
                            <pre class="small mb-0 bg-light p-2">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-muted">No activity recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection
