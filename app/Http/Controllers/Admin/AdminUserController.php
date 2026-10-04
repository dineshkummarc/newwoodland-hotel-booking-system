<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Staff account management — super_admin only (enforced by route
 * middleware, see routes/admin.php). Accounts are deactivated, never
 * deleted: there is no destroy() here, since bookings/audit entries may
 * already reference an admin and hard-deleting would either orphan or
 * cascade-wipe that history for no operational benefit.
 */
class AdminUserController extends Controller
{
    public function index()
    {
        $admins = Admin::orderBy('name')->paginate(20);

        return view('admin.admin-users.index', compact('admins'));
    }

    public function create()
    {
        return view('admin.admin-users.create');
    }

    public function store(StoreAdminUserRequest $request, AuditLogService $auditLog)
    {
        $admin = Admin::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role'),
            'is_active' => true,
        ]);

        $auditLog->log('admin.created', 'admin', $admin->id, "Created staff account {$admin->email} with role {$admin->role}.");

        return redirect()->route('admin.admin-users.index')->with('success', 'Staff account created.');
    }

    public function edit(Admin $adminUser)
    {
        return view('admin.admin-users.edit', ['adminUser' => $adminUser]);
    }

    public function update(UpdateAdminUserRequest $request, Admin $adminUser, AuditLogService $auditLog)
    {
        $data = $request->safe()->only(['name', 'email', 'role']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $adminUser->update($data);

        $auditLog->log('admin.updated', 'admin', $adminUser->id, "Updated staff account {$adminUser->email} (role: {$adminUser->role}).");

        return redirect()->route('admin.admin-users.index')->with('success', 'Staff account updated.');
    }

    public function deactivate(Admin $adminUser, AuditLogService $auditLog)
    {
        if ($adminUser->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'You cannot deactivate your own account while logged in as it.');
        }

        if ($adminUser->isLastActiveSuperAdmin()) {
            return back()->with('error', 'You cannot deactivate the last active super admin.');
        }

        $adminUser->update(['is_active' => false]);

        $auditLog->log('admin.deactivated', 'admin', $adminUser->id, "Deactivated staff account {$adminUser->email}.");

        return back()->with('success', 'Staff account deactivated.');
    }

    public function reactivate(Admin $adminUser, AuditLogService $auditLog)
    {
        $adminUser->update(['is_active' => true]);

        $auditLog->log('admin.reactivated', 'admin', $adminUser->id, "Reactivated staff account {$adminUser->email}.");

        return back()->with('success', 'Staff account reactivated.');
    }
}
