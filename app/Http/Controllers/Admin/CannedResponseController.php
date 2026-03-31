<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CannedResponseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/CannedResponses', [
            'cannedResponses' => CannedResponse::with('department')->orderBy('title')->get(),
            'departments' => Department::active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        CannedResponse::create($data);

        return back()->with('flash', ['success' => 'Canned response created.']);
    }

    public function update(Request $request, CannedResponse $cannedResponse): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $cannedResponse->update($data);

        return back()->with('flash', ['success' => 'Canned response updated.']);
    }

    public function destroy(CannedResponse $cannedResponse): RedirectResponse
    {
        $cannedResponse->delete();

        return back()->with('flash', ['success' => 'Canned response deleted.']);
    }
}
