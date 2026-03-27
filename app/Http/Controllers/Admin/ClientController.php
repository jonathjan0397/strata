<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientEmail;
use App\Models\ClientCredit;
use App\Models\ClientNote;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            'notes.author:id,name',
            'group',
        ]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
            'groups' => \App\Models\ClientGroup::orderBy('name')->get(['id', 'name']),
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

        AuditLogger::log('client.created', $user);

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

        AuditLogger::log('client.updated', $client);

        return back()->with('success', 'Client updated.');
    }

    public function suspend(User $client): RedirectResponse
    {
        $client->services()->where('status', 'active')->update(['status' => 'suspended']);

        return back()->with('success', 'Client services suspended.');
    }

    /** Add credit to a client's balance. */
    public function addCredit(Request $request, User $client): RedirectResponse
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $client) {
            ClientCredit::create([
                'user_id'     => $client->id,
                'amount'      => $request->amount,
                'description' => $request->description,
            ]);

            $client->increment('credit_balance', (float) $request->amount);
        });

        return back()->with('success', 'Credit of $'.number_format((float) $request->amount, 2).' added.');
    }

    public function storeNote(Request $request, User $client): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        ClientNote::create([
            'user_id'   => $client->id,
            'author_id' => $request->user()->id,
            'body'      => $request->body,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function destroyNote(User $client, ClientNote $note): RedirectResponse
    {
        abort_if($note->user_id !== $client->id, 404);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    public function sendEmail(Request $request, User $client): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        Mail::to($client->email, $client->name)
            ->send(new ClientEmail($data['subject'], $data['body']));

        AuditLogger::log('client.email_sent', $client, ['subject' => $data['subject']]);

        return back()->with('success', 'Email sent to ' . $client->email);
    }
}
