<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()->tickets()->with('department');

        if ($request->search) {
            $query->where('subject', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Client/Support/Index', [
            'tickets' => $query->latest('last_reply_at')->paginate(20)->withQueryString(),
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Client/Support/Create', [
            'departments' => Department::active()->get(['id', 'name']),
        ]);
    }

    public function kbSuggest(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 3) {
            return response()->json([]);
        }

        $articles = KbArticle::where('published', true)
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'slug']);

        return response()->json($articles);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'subject'         => ['required', 'string', 'max:255'],
            'department_id'   => ['nullable', 'exists:departments,id'],
            'priority'        => ['required', 'in:low,medium,high,urgent'],
            'message'         => ['required', 'string'],
            'attachments'     => ['nullable', 'array', 'max:5'],
            'attachments.*'   => ['file', 'max:10240'],
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

        $reply = $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $request->message,
            'is_staff' => false,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("ticket-attachments/{$ticket->id}", 'public');
                $reply->attachments()->create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => $request->user()->id,
                    'filename'  => $file->getClientOriginalName(),
                    'path'      => $path,
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        WorkflowEngine::fire('ticket.opened', $ticket);

        // Notify admin/support team of new ticket
        $notifyEmail = Setting::get('ticket_notify_email',
            Setting::get('company_email', config('mail.from.address')));

        if ($notifyEmail) {
            try {
                Mail::to($notifyEmail)->send(new TemplateMailable('support.opened', [
                    'name'           => $request->user()->name,
                    'app_name'       => config('app.name'),
                    'ticket_id'      => $ticket->id,
                    'ticket_subject' => $ticket->subject,
                    'priority'       => ucfirst($ticket->priority),
                    'department'     => $dept?->name ?? 'General',
                    'ticket_url'     => route('admin.support.show', $ticket->id),
                ]));
            } catch (\Throwable) {}
        }

        return redirect()->route('client.support.show', $ticket)
            ->with('success', 'Ticket submitted.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $ticket->load(['department', 'replies' => function ($q) {
            $q->where('internal', false)->with(['user', 'attachments']);
        }]);

        return Inertia::render('Client/Support/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_if($ticket->status === 'closed', 422, 'Ticket is closed.');

        $request->validate([
            'message'         => ['required', 'string'],
            'attachments'     => ['nullable', 'array', 'max:5'],
            'attachments.*'   => ['file', 'max:10240'],
        ]);

        $reply = $ticket->replies()->create([
            'user_id'  => $request->user()->id,
            'message'  => $request->message,
            'is_staff' => false,
            'internal' => false,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("ticket-attachments/{$ticket->id}", 'public');
                $reply->attachments()->create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => $request->user()->id,
                    'filename'  => $file->getClientOriginalName(),
                    'path'      => $path,
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        $ticket->update([
            'status'        => 'customer_reply',
            'last_reply_at' => now(),
        ]);

        return back()->with('success', 'Reply sent.');
    }

    public function rate(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_unless($ticket->status === 'closed', 422, 'Can only rate closed tickets.');
        abort_if($ticket->rating !== null, 422, 'Ticket already rated.');

        $request->validate([
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'rating_note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->update([
            'rating'      => $request->rating,
            'rating_note' => $request->rating_note,
        ]);

        return back()->with('success', 'Thank you for your feedback!');
    }
}
