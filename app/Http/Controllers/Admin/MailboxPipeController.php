<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\MailboxPipe;
use App\Models\User;
use App\Services\EmailPipeProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MailboxPipeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/MailPipes', [
            'pipes' => MailboxPipe::with(['department', 'assignee'])->orderBy('name')->get(),
            'departments' => Department::active()->get(['id', 'name']),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin', 'staff'])
            )->get(['id', 'name']),
            'appUrl' => rtrim(config('app.url'), '/'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:100'],
            'email_address'               => ['nullable', 'email', 'max:255'],
            'department_id'               => ['nullable', 'exists:departments,id'],
            'auto_assign_to'              => ['nullable', 'exists:users,id'],
            'default_priority'            => ['required', 'in:low,medium,high,urgent'],
            'create_client_if_not_exists' => ['boolean'],
            'strip_signature'             => ['boolean'],
            'auto_reply_enabled'          => ['boolean'],
            'auto_reply_subject'          => ['nullable', 'string', 'max:255'],
            'auto_reply_body'             => ['nullable', 'string', 'max:5000'],
            'reject_unknown_senders'      => ['boolean'],
            'is_active'                   => ['boolean'],
            'imap_host'                   => ['nullable', 'string', 'max:255'],
            'imap_port'                   => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username'               => ['nullable', 'string', 'max:255'],
            'imap_password'               => ['nullable', 'string', 'max:1000'],
            'imap_encryption'             => ['nullable', 'in:ssl,tls,none'],
            'imap_mailbox'                => ['nullable', 'string', 'max:100'],
        ]);

        $data['pipe_token'] = MailboxPipe::generateToken();

        MailboxPipe::create($data);

        return back()->with('flash', ['success' => "Mail pipe '{$data['name']}' created."]);
    }

    public function update(Request $request, MailboxPipe $mailboxPipe): RedirectResponse
    {
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:100'],
            'email_address'               => ['nullable', 'email', 'max:255'],
            'department_id'               => ['nullable', 'exists:departments,id'],
            'auto_assign_to'              => ['nullable', 'exists:users,id'],
            'default_priority'            => ['required', 'in:low,medium,high,urgent'],
            'create_client_if_not_exists' => ['boolean'],
            'strip_signature'             => ['boolean'],
            'auto_reply_enabled'          => ['boolean'],
            'auto_reply_subject'          => ['nullable', 'string', 'max:255'],
            'auto_reply_body'             => ['nullable', 'string', 'max:5000'],
            'reject_unknown_senders'      => ['boolean'],
            'is_active'                   => ['boolean'],
            'imap_host'                   => ['nullable', 'string', 'max:255'],
            'imap_port'                   => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username'               => ['nullable', 'string', 'max:255'],
            'imap_password'               => ['nullable', 'string', 'max:1000'],
            'imap_encryption'             => ['nullable', 'in:ssl,tls,none'],
            'imap_mailbox'                => ['nullable', 'string', 'max:100'],
        ]);

        $mailboxPipe->update($data);

        return back()->with('flash', ['success' => "Pipe '{$mailboxPipe->name}' updated."]);
    }

    public function regenerateToken(MailboxPipe $mailboxPipe): RedirectResponse
    {
        $mailboxPipe->update(['pipe_token' => MailboxPipe::generateToken()]);

        return back()->with('flash', ['success' => 'Pipe token regenerated.']);
    }

    public function destroy(MailboxPipe $mailboxPipe): RedirectResponse
    {
        $name = $mailboxPipe->name;
        $mailboxPipe->delete();

        return back()->with('flash', ['success' => "Mail pipe '{$name}' deleted."]);
    }

    /**
     * HTTP pipe endpoint — accepts a raw email POSTed by the mail server.
     * Route: POST /pipe/{token}  (no auth — token is the secret)
     */
    public function receive(Request $request, string $token, EmailPipeProcessor $processor)
    {
        $pipe = MailboxPipe::where('pipe_token', $token)->where('is_active', true)->first();

        if (! $pipe) {
            abort(404);
        }

        // Accept raw body or a 'message' field (some forwarders POST form data)
        $raw = $request->getContent() ?: $request->input('message', '');

        if (empty(trim($raw))) {
            return response()->json(['error' => 'No message content.'], 422);
        }

        try {
            $processor->process($pipe, $raw);
        } catch (\Throwable $e) {
            Log::error("[MailPipe:{$pipe->id}] HTTP pipe error: ".$e->getMessage());

            return response()->json(['error' => 'Processing failed.'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
