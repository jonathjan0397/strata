<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\CannedResponse;
use App\Models\Department;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::with(['user', 'assignedTo', 'department'])
            ->when($request->status,     fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority,   fn ($q, $p) => $q->where('priority', $p))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->search, fn ($q, $s) =>
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->orderByRaw("FIELD(status, 'open', 'customer_reply', 'answered', 'on_hold', 'closed')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Support/Index', [
            'tickets'     => $tickets,
            'departments' => Department::active()->get(['id', 'name']),
            'filters'     => $request->only('search', 'status', 'priority', 'department'),
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['user', 'assignedTo', 'department', 'replies.user']);

        return Inertia::render('Admin/Support/Show', [
            'ticket'         => $ticket,
            'staff'          => User::whereHas('roles', fn ($q) =>
                $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
            'cannedResponses'=> CannedResponse::with('department')
                ->orderBy('title')
                ->get(['id', 'title', 'body', 'department_id']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message'  => ['required', 'string'],
            'internal' => ['boolean'],
        ]);

        $isInternal = (bool) ($data['internal'] ?? false);

        $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $data['message'],
            'is_staff' => true,
            'internal' => $isInternal,
        ]);

        if (! $isInternal) {
            $ticket->update([
                'status'        => 'answered',
                'last_reply_at' => now(),
            ]);

            $ticket->load('user');
            Mail::to($ticket->user->email)->queue(new TemplateMailable('support.reply', [
                'name'           => $ticket->user->name,
                'app_name'       => config('app.name'),
                'ticket_id'      => $ticket->id,
                'ticket_subject' => $ticket->subject,
                'reply_body'     => $data['message'],
                'ticket_url'     => route('client.support.show', $ticket->id),
            ]));
        } else {
            $ticket->touch();
        }

        return back()->with('flash', [
            'success' => $isInternal ? 'Internal note added.' : 'Reply sent.',
        ]);
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $ticket->update(['assigned_to' => $request->assigned_to]);

        return back()->with('flash', ['success' => 'Ticket assigned.']);
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'closed']);

        return back()->with('flash', ['success' => 'Ticket closed.']);
    }

    public function reopen(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'open']);

        return back()->with('flash', ['success' => 'Ticket reopened.']);
    }
}
