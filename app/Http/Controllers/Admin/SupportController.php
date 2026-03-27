<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::with(['user', 'assignedTo'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->search, fn ($q, $s) =>
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->orderByRaw("FIELD(status, 'open', 'customer_reply', 'answered', 'on_hold', 'closed')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'filters' => $request->only('search', 'status', 'priority'),
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['user', 'assignedTo', 'replies.user']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
            'staff'  => User::whereHas('roles', fn ($q) =>
                $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['message' => ['required', 'string']]);

        $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $request->message,
            'is_staff' => true,
        ]);

        $ticket->update([
            'status'        => 'answered',
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'Reply sent.');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $ticket->update(['assigned_to' => $request->assigned_to]);

        return back()->with('success', 'Ticket assigned.');
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'closed']);

        return back()->with('success', 'Ticket closed.');
    }
}
