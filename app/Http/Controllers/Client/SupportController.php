<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Client/Support/Index', [
            'tickets' => $request->user()
                ->tickets()
                ->with('department')
                ->latest('last_reply_at')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Client/Support/Create', [
            'departments' => Department::active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'subject'       => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'priority'      => ['required', 'in:low,medium,high,urgent'],
            'message'       => ['required', 'string'],
        ]);

        $dept = $request->department_id
            ? Department::find($request->department_id)
            : null;

        $ticket = $request->user()->tickets()->create([
            'subject'       => $request->subject,
            'department_id' => $request->department_id,
            'department'    => $dept?->name ?? 'General',
            'priority'      => $request->priority,
            'status'        => 'open',
            'last_reply_at' => now(),
        ]);

        $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $request->message,
            'is_staff' => false,
        ]);

        return redirect()->route('client.support.show', $ticket)
            ->with('success', 'Ticket submitted.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        // Client only sees non-internal replies
        $ticket->load(['department', 'replies' => function ($q) {
            $q->where('internal', false)->with('user');
        }]);

        return Inertia::render('Client/Support/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $request->validate(['message' => ['required', 'string']]);

        $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $request->message,
            'is_staff' => false,
            'internal' => false,
        ]);

        $ticket->update([
            'status'        => 'customer_reply',
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'Reply sent.');
    }
}
