<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Departments', [
            'departments' => Department::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:departments,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'active'      => ['boolean'],
        ]);

        Department::create($data);

        return back()->with('flash', ['success' => 'Department created.']);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:departments,name,' . $department->id],
            'description' => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'active'      => ['boolean'],
        ]);

        $department->update($data);

        return back()->with('flash', ['success' => 'Department updated.']);
    }

    public function destroy(Department $department): RedirectResponse
    {
        // Detach tickets before deleting (nullOnDelete handles the FK)
        $department->delete();

        return back()->with('flash', ['success' => 'Department deleted.']);
    }
}
