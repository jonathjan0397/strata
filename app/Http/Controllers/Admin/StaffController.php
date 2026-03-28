<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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

    private const ROLES = ['super-admin', 'admin', 'staff'];

    public function index(): Response
    {
        $roleOrder = ['super-admin' => 0, 'admin' => 1, 'staff' => 2];

        $team = User::role(self::ROLES)
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'role'        => $u->roles->first()?->name ?? 'staff',
                'permissions' => $u->permissions->pluck('name')->values(),
                'created_at'  => $u->created_at->toDateString(),
            ])
            ->sortBy(fn ($m) => $roleOrder[$m['role']] ?? 99)
            ->values();

        return Inertia::render('Admin/Staff/Index', [
            'team'                 => $team,
            'availablePermissions' => self::STAFF_PERMISSIONS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Staff/Create', [
            'availablePermissions' => self::STAFF_PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'role'                  => ['required', 'in:admin,staff'],
            'permissions'           => ['array'],
            'permissions.*'         => ['string', 'in:' . implode(',', self::STAFF_PERMISSIONS)],
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);

        if ($data['role'] === 'staff') {
            $this->ensurePermissionsExist();
            $user->syncPermissions($data['permissions'] ?? []);
        }

        AuditLogger::log('staff.created', $user, ['role' => $data['role']]);

        return redirect()->route('admin.staff.index')
            ->with('success', $data['name'] . ' has been added to the team.');
    }

    public function edit(User $staff): Response
    {
        abort_unless($staff->hasAnyRole(self::ROLES), 404);

        return Inertia::render('Admin/Staff/Edit', [
            'member' => [
                'id'          => $staff->id,
                'name'        => $staff->name,
                'email'       => $staff->email,
                'role'        => $staff->roles->first()?->name ?? 'staff',
                'permissions' => $staff->permissions->pluck('name')->values(),
            ],
            'availablePermissions' => self::STAFF_PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->hasAnyRole(self::ROLES), 404);

        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email,' . $staff->id],
            'role'          => ['required', 'in:admin,staff,super-admin'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'in:' . implode(',', self::STAFF_PERMISSIONS)],
        ];

        if ($request->filled('password')) {
            $rules['password']              = ['string', 'min:8', 'confirmed'];
            $rules['password_confirmation'] = ['required'];
        }

        $data = $request->validate($rules);

        // Only super-admin can promote to super-admin
        if ($data['role'] === 'super-admin' && ! $request->user()->hasRole('super-admin')) {
            $data['role'] = 'admin';
        }

        $updates = ['name' => $data['name'], 'email' => $data['email']];
        if ($request->filled('password')) {
            $updates['password'] = Hash::make($data['password']);
        }

        $staff->update($updates);
        $staff->syncRoles([$data['role']]);

        $this->ensurePermissionsExist();
        $staff->syncPermissions($data['role'] === 'staff' ? ($data['permissions'] ?? []) : []);

        AuditLogger::log('staff.updated', $staff, ['role' => $data['role']]);

        return redirect()->route('admin.staff.index')
            ->with('success', $staff->name . ' has been updated.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->hasAnyRole(self::ROLES), 404);

        if ($staff->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $staff->name;
        $staff->delete();

        AuditLogger::log('staff.deleted', $staff, ['name' => $name]);

        return redirect()->route('admin.staff.index')
            ->with('success', $name . ' has been removed from the team.');
    }

    private function ensurePermissionsExist(): void
    {
        foreach (self::STAFF_PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }
}
