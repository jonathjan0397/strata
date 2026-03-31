<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\CannedResponse;
use App\Models\Department;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\TicketAttachment;
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
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->assigned_to, function ($q, $a) use ($request) {
                $a === 'me'
                    ? $q->where('assigned_to', $request->user()->id)
                    : $q->where('assigned_to', $a);
            })
            ->when($request->search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('subject', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('replies', fn ($r) => $r->where('message', 'like', "%{$s}%"));
            })
            )
            ->orderByRaw("FIELD(status, 'open', 'customer_reply', 'answered', 'on_hold', 'closed')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'departments' => Department::active()->get(['id', 'name']),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'priority', 'department', 'assigned_to'),
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['user', 'assignedTo', 'department', 'replies.user', 'replies.attachments']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
            'departments' => Department::active()->get(['id', 'name']),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
            'cannedResponses' => CannedResponse::with('department')
                ->orderBy('title')
                ->get(['id', 'title', 'body', 'department_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Support/Create', [
            'departments' => Department::active()->get(['id', 'name']),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
            'clients' => User::role('client')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'internal' => ['boolean'],
        ]);

        $dept = $data['department_id']
            ? Department::find($data['department_id'])
            : null;

        $ticket = SupportTicket::create([
            'user_id' => $data['user_id'],
            'subject' => $data['subject'],
            'department_id' => $data['department_id'] ?? null,
            'department' => $dept?->name ?? 'General',
            'priority' => $data['priority'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_staff' => true,
            'internal' => $data['internal'] ?? false,
        ]);

        return redirect()->route('admin.support.show', $ticket)
            ->with('success', 'Ticket created.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'internal' => ['boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $isInternal = (bool) ($data['internal'] ?? false);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_staff' => true,
            'internal' => $isInternal,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("ticket-attachments/{$ticket->id}", 'public');
                $reply->attachments()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        if (! $isInternal) {
            $updates = [
                'status' => 'answered',
                'last_reply_at' => now(),
            ];

            if (! $ticket->first_replied_at) {
                $updates['first_replied_at'] = now();
            }

            $ticket->update($updates);

            $ticket->load('user');
            try {
                Mail::to($ticket->user->email)->send(new TemplateMailable('support.reply', [
                    'name' => $ticket->user->name,
                    'app_name' => config('app.name'),
                    'ticket_id' => $ticket->id,
                    'ticket_subject' => $ticket->subject,
                    'reply_body' => $data['message'],
                    'ticket_url' => route('client.support.show', $ticket->id),
                ]));
            } catch (\Throwable) {
                // mail failure must not block support reply
            }
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

        $oldAssigned = $ticket->assigned_to;
        $ticket->update(['assigned_to' => $request->assigned_to]);

        if ($request->assigned_to && $request->assigned_to != $oldAssigned) {
            $assignee = User::find($request->assigned_to);
            if ($assignee) {
                try {
                    Mail::to($assignee->email)->send(new TemplateMailable('support.assigned', [
                        'name' => $assignee->name,
                        'app_name' => config('app.name'),
                        'ticket_id' => $ticket->id,
                        'ticket_subject' => $ticket->subject,
                        'ticket_url' => route('admin.support.show', $ticket->id),
                    ]));
                } catch (\Throwable) {
                }
            }
        }

        return back()->with('flash', ['success' => 'Ticket assigned.']);
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('flash', ['success' => 'Ticket closed.']);
    }

    public function reopen(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'open', 'closed_at' => null]);

        return back()->with('flash', ['success' => 'Ticket reopened.']);
    }

    public function setPriority(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['priority' => ['required', 'in:low,medium,high,urgent']]);
        $ticket->update(['priority' => $request->priority]);

        return back()->with('flash', ['success' => 'Priority updated.']);
    }

    public function transferDepartment(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['department_id' => ['required', 'exists:departments,id']]);

        $dept = Department::find($request->department_id);
        $ticket->update([
            'department_id' => $dept->id,
            'department' => $dept->name,
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => "Ticket transferred to department: {$dept->name}",
            'is_staff' => true,
            'internal' => true,
        ]);

        return back()->with('flash', ['success' => "Transferred to {$dept->name}."]);
    }

    public function merge(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate([
            'merge_ticket_id' => ['required', 'integer', 'exists:support_tickets,id'],
        ]);

        $mergeId = (int) $request->merge_ticket_id;

        if ($mergeId === $ticket->id) {
            return back()->withErrors(['merge_ticket_id' => 'Cannot merge a ticket with itself.']);
        }

        $source = SupportTicket::find($mergeId);

        // Move replies and attachments from source to this ticket
        $source->replies()->update(['ticket_id' => $ticket->id]);
        TicketAttachment::where('ticket_id', $source->id)->update(['ticket_id' => $ticket->id]);

        // Internal note on target
        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => "Ticket #{$source->id} merged into this ticket. (Original subject: {$source->subject})",
            'is_staff' => true,
            'internal' => true,
        ]);

        // Close source with note
        $source->replies()->create([
            'user_id' => $request->user()->id,
            'message' => "This ticket was merged into Ticket #{$ticket->id}.",
            'is_staff' => true,
            'internal' => true,
        ]);
        $source->update(['status' => 'closed', 'closed_at' => now()]);
        $ticket->touch();

        return back()->with('flash', ['success' => "Ticket #{$source->id} merged successfully."]);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:close,reopen,assign,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:support_tickets,id'],
            'value' => ['nullable', 'string'],
        ]);

        $count = count($data['ids']);

        switch ($data['action']) {
            case 'close':
                SupportTicket::whereIn('id', $data['ids'])
                    ->whereNot('status', 'closed')
                    ->update(['status' => 'closed', 'closed_at' => now()]);
                $msg = "{$count} ticket(s) closed.";
                break;

            case 'reopen':
                SupportTicket::whereIn('id', $data['ids'])
                    ->update(['status' => 'open', 'closed_at' => null]);
                $msg = "{$count} ticket(s) reopened.";
                break;

            case 'assign':
                $assignTo = $data['value'] ? (int) $data['value'] : null;
                SupportTicket::whereIn('id', $data['ids'])
                    ->update(['assigned_to' => $assignTo]);
                $msg = "{$count} ticket(s) assigned.";
                break;

            case 'delete':
                SupportTicket::whereIn('id', $data['ids'])->delete();
                $msg = "{$count} ticket(s) deleted.";
                break;

            default:
                $msg = 'Action completed.';
        }

        return redirect()->route('admin.support.index')
            ->with('flash', ['success' => $msg]);
    }
}
