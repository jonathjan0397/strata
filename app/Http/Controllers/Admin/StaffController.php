<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class StaffController extends Controller
{
    private const STAFF_PERMISSIONS = [
        'access.billing',
        'access.support',
        'access.technical',
        'access.clients',
        'access.reports',
    ];

    public function index(): Response
    {
        $staff = User::role('staff')
            ->select('id', 'name', 'email', 'created_at')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'permissions' => $u->permissions->pluck('name'),
                'created_at'  => $u->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/Staff/Index', [
            'staff'              => $staff,
            'availablePermissions' => self::STAFF_PERMISSIONS,
        ]);
    }

    public function edit(User $staff): Response
    {
        abort_unless($staff->hasRole('staff'), 404);

        return Inertia::render('Admin/Staff/Edit', [
            'staff' => [
                'id'          => $staff->id,
                'name'        => $staff->name,
                'email'       => $staff->email,
                'permissions' => $staff->permissions->pluck('name'),
            ],
            'availablePermissions' => self::STAFF_PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->hasRole('staff'), 404);

        $data = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'in:' . implode(',', self::STAFF_PERMISSIONS)],
        ]);

        // Ensure all permission records exist
        foreach (self::STAFF_PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $staff->syncPermissions($data['permissions'] ?? []);

        AuditLogger::log('staff.permissions_updated', $staff, [
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Permissions updated for ' . $staff->name);
    }
}
