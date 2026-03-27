<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = User::role('client')
            ->withCount(['services', 'invoices'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(User $client): Response
    {
        $client->load([
            'services.product',
            'invoices' => fn ($q) => $q->latest()->limit(10),
            'tickets'  => fn ($q) => $q->latest()->limit(10),
            'domains',
        ]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('client');

        return redirect()->route('admin.clients.show', $user)
            ->with('success', 'Client created.');
    }

    public function update(Request $request, User $client): RedirectResponse
    {
        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$client->id],
        ]);

        $client->update($request->only('name', 'email'));

        return back()->with('success', 'Client updated.');
    }

    public function suspend(User $client): RedirectResponse
    {
        $client->services()->where('status', 'active')->update(['status' => 'suspended']);

        return back()->with('success', 'Client services suspended.');
    }
}
