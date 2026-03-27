<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientGroupController extends Controller
{
    public function index(): Response
    {
        $groups = ClientGroup::withCount('users')->orderBy('name')->get();

        return Inertia::render('Admin/ClientGroups/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'           => ['required', 'string', 'max:100', 'unique:client_groups'],
            'description'    => ['nullable', 'string', 'max:500'],
            'discount_type'  => ['required', 'in:none,percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
        ]);

        ClientGroup::create($request->only('name', 'description', 'discount_type', 'discount_value'));

        return back()->with('success', 'Group created.');
    }

    public function update(Request $request, ClientGroup $clientGroup): RedirectResponse
    {
        $request->validate([
            'name'           => ['required', 'string', 'max:100', 'unique:client_groups,name,'.$clientGroup->id],
            'description'    => ['nullable', 'string', 'max:500'],
            'discount_type'  => ['required', 'in:none,percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
        ]);

        $clientGroup->update($request->only('name', 'description', 'discount_type', 'discount_value'));

        return back()->with('success', 'Group updated.');
    }

    public function destroy(ClientGroup $clientGroup): RedirectResponse
    {
        // Unassign clients before deleting
        User::where('client_group_id', $clientGroup->id)->update(['client_group_id' => null]);
        $clientGroup->delete();

        return back()->with('success', 'Group deleted.');
    }

    /** Assign a client to a group (or remove from group). */
    public function assignClient(Request $request, User $client): RedirectResponse
    {
        $request->validate([
            'client_group_id' => ['nullable', 'exists:client_groups,id'],
        ]);

        $client->update(['client_group_id' => $request->client_group_id ?: null]);

        return back()->with('success', 'Client group updated.');
    }
}
