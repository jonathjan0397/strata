<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientEmail;
use App\Models\ClientCredit;
use App\Models\ClientGroup;
use App\Models\ClientNote;
use App\Models\ClientTask;
use App\Models\Setting;
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
            ->when($request->search, fn ($q, $s) => $q->where(fn ($inner) => $inner->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('company', 'like', "%{$s}%")
            )
            )
            ->when($request->status, fn ($q, $s) => $q->where('client_status', $s))
            ->when($request->lead_source, fn ($q, $l) => $q->where('lead_source', $l))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only('search', 'status', 'lead_source'),
        ]);
    }

    public function show(User $client): Response
    {
        $client->load([
            'services.product',
            'invoices' => fn ($q) => $q->latest()->limit(15),
            'tickets' => fn ($q) => $q->latest()->limit(15),
            'domains',
            'notes.author:id,name',
            'tasks.assignee:id,name',
            'group',
        ]);

        $stats = [
            'active_services' => $client->services->where('status', 'active')->count(),
            'unpaid_total' => $client->invoices()->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due'),
            'total_paid' => $client->invoices()->where('status', 'paid')->sum('total'),
            'open_tickets' => $client->tickets()->whereNotIn('status', ['closed'])->count(),
        ];

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
            'stats' => $stats,
            'groups' => ClientGroup::orderBy('name')->get(['id', 'name']),
            'staff' => User::role(['super-admin', 'admin', 'staff'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Rules\Password::defaults()],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
            'company' => $request->company,
            'phone' => $request->phone,
            'client_status' => 'active',
        ]);
        $user->assignRole('client');

        AuditLogger::log('client.created', $user);

        return redirect()->route('admin.clients.show', $user)
            ->with('success', 'Client created.');
    }

    public function update(Request $request, User $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$client->id],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'client_status' => ['nullable', 'in:prospect,active,inactive,at_risk,churned'],
            'country' => ['nullable', 'string', 'max:2'],
            'state' => ['nullable', 'string', 'max:10'],
            'tax_exempt' => ['nullable', 'boolean'],
        ]);

        $client->update($data);

        AuditLogger::log('client.updated', $client);

        return back()->with('success', 'Client profile updated.');
    }

    public function suspend(User $client): RedirectResponse
    {
        $client->services()->where('status', 'active')->update(['status' => 'suspended']);

        return back()->with('success', 'Client services suspended.');
    }

    public function verifyEmail(User $client): RedirectResponse
    {
        if (! $client->email_verified_at) {
            $client->forceFill(['email_verified_at' => now()])->save();
            AuditLogger::log('client.email_verified', $client);
        }

        return back()->with('success', 'Email marked as verified.');
    }

    /** Add credit to a client's balance. */
    public function addCredit(Request $request, User $client): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $client) {
            ClientCredit::create([
                'user_id' => $client->id,
                'amount' => $request->amount,
                'description' => $request->description,
            ]);

            $client->increment('credit_balance', (float) $request->amount);
        });

        return back()->with('success', 'Credit of $'.number_format((float) $request->amount, 2).' added.');
    }

    public function storeNote(Request $request, User $client): RedirectResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:note,call,email,meeting'],
        ]);

        ClientNote::create([
            'user_id' => $client->id,
            'author_id' => $request->user()->id,
            'body' => $request->body,
            'type' => $request->type ?? 'note',
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

    // ── Tasks ─────────────────────────────────────────────────────────────────

    public function storeTask(Request $request, User $client): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,normal,high'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $data['user_id'] = $client->id;
        $data['priority'] = $data['priority'] ?? 'normal';

        ClientTask::create($data);

        return back()->with('success', 'Task added.');
    }

    public function completeTask(User $client, ClientTask $task): RedirectResponse
    {
        abort_if($task->user_id !== $client->id, 404);
        $task->update(['completed_at' => $task->completed_at ? null : now()]);

        return back()->with('success', $task->completed_at ? 'Task completed.' : 'Task reopened.');
    }

    public function destroyTask(User $client, ClientTask $task): RedirectResponse
    {
        abort_if($task->user_id !== $client->id, 404);
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    public function sendEmail(Request $request, User $client): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        set_time_limit(15);

        $mailer = Setting::get('mail_mailer', config('mail.default'));
        $from = Setting::get('mail_from_address', config('mail.from.address'));

        if ($mailer === 'sendmail') {
            // Bypass Laravel's transport (hangs); use proc_open directly like testMail
            $path = Setting::get('mail_sendmail_path', '/usr/sbin/sendmail -t -i');
            $bin = explode(' ', $path)[0];

            if (! file_exists($bin) || ! is_executable($bin)) {
                return back()->withErrors(['email' => "sendmail not found: {$bin}"]);
            }

            $appName = Setting::get('company_name', config('app.name'));
            $bodyHtml = nl2br(e($data['body']));
            $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;background:#f9fafb;padding:32px 0;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
<div style="background:#4f46e5;padding:24px 32px;">
  <span style="font-size:20px;font-weight:700;color:#fff;">{$appName}</span>
</div>
<div style="padding:32px;color:#374151;font-size:14px;line-height:1.6;">{$bodyHtml}</div>
<div style="padding:16px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">&copy; {$appName}. All rights reserved.</div>
</div></body></html>
HTML;

            $boundary = '=_'.bin2hex(random_bytes(8));
            $encoded = base64_encode($html);
            $raw = "To: {$client->name} <{$client->email}>\r\n";
            $raw .= "From: {$from}\r\n";
            $raw .= "Subject: {$data['subject']}\r\n";
            $raw .= "MIME-Version: 1.0\r\n";
            $raw .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            $raw .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
            $raw .= strip_tags($data['body'])."\r\n\r\n";
            $raw .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $raw .= chunk_split($encoded)."\r\n";
            $raw .= "--{$boundary}--\r\n";

            $proc = proc_open($path, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (! is_resource($proc)) {
                return back()->withErrors(['email' => 'Could not open sendmail process.']);
            }

            fwrite($pipes[0], $raw);
            fclose($pipes[0]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($proc);

            if ($exit !== 0) {
                return back()->withErrors(['email' => "sendmail failed (exit {$exit}): {$stderr}"]);
            }
        } else {
            // SMTP / log — use Laravel mail stack with try/catch
            try {
                Mail::to($client->email, $client->name)
                    ->send(new ClientEmail($data['subject'], $data['body']));
            } catch (\Throwable $e) {
                return back()->withErrors(['email' => 'Failed to send: '.$e->getMessage()]);
            }
        }

        AuditLogger::log('client.email_sent', $client, ['subject' => $data['subject']]);

        return back()->with('success', 'Email sent to '.$client->email);
    }
}
