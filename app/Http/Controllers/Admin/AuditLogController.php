<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Read-only view of the staff activity trail written by AuditLogService.
 * There is deliberately no edit/delete: the log is append-only.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('admin')->latest('id');

        if ($type = $request->string('type')->toString()) {
            $query->where('target_type', $type);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();
        $types = AuditLog::query()->whereNotNull('target_type')->distinct()->orderBy('target_type')->pluck('target_type');

        return view('admin.audit-logs.index', compact('logs', 'types'));
    }
}
