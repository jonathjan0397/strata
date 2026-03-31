<?php

namespace App\Services;

use App\Mail\TemplateMailable;
use App\Models\Department;
use App\Models\MailboxPipe;
use App\Models\Setting;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailPipeProcessor
{
    /**
     * Process a raw RFC 2822 email message for the given pipe.
     *
     * @throws \RuntimeException if the message cannot be processed
     */
    public function process(MailboxPipe $pipe, string $rawEmail): void
    {
        if (! $pipe->is_active) {
            return;
        }

        $parsed = $this->parseEmail($rawEmail);

        $fromEmail = $parsed['from_email'] ?? null;
        $fromName = $parsed['from_name'] ?? null;
        $subject = $parsed['subject'] ?? '(No Subject)';
        $body = $parsed['body'] ?? '';
        $messageId = $parsed['message_id'] ?? null;
        $inReplyTo = $parsed['in_reply_to'] ?? null;
        $references = $parsed['references'] ?? null;

        if (! $fromEmail) {
            Log::warning("[MailPipe:{$pipe->id}] Could not determine sender — dropping message.");

            return;
        }

        // ── Resolve sender ────────────────────────────────────────────────
        $user = User::where('email', $fromEmail)->first();

        if (! $user) {
            if ($pipe->reject_unknown_senders || ! $pipe->create_client_if_not_exists) {
                Log::info("[MailPipe:{$pipe->id}] Unknown sender {$fromEmail} — dropping (reject_unknown or no auto-create).");

                return;
            }

            $user = $this->createClientFromEmail($fromEmail, $fromName);
        }

        // ── Strip signature ───────────────────────────────────────────────
        if ($pipe->strip_signature) {
            $body = $this->stripSignature($body);
        }

        // ── Find existing ticket (reply?) ─────────────────────────────────
        $ticket = $this->resolveExistingTicket($subject, $inReplyTo, $references, $user);

        if ($ticket) {
            $this->appendReply($ticket, $user, $body);
        } else {
            $ticket = $this->createTicket($pipe, $user, $subject, $body);
        }

        // ── Auto-reply ────────────────────────────────────────────────────
        if ($pipe->auto_reply_enabled && ! $ticket->wasRecentlyCreated === false) {
            // Only auto-reply on new tickets
        }

        if ($pipe->auto_reply_enabled && isset($createdNew) && $createdNew) {
            $this->sendAutoReply($pipe, $user, $ticket);
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function createTicket(MailboxPipe $pipe, User $user, string $subject, string $body): SupportTicket
    {
        $dept = $pipe->department_id ? Department::find($pipe->department_id) : null;

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => mb_substr($subject, 0, 255),
            'department_id' => $pipe->department_id,
            'department' => $dept?->name ?? 'General',
            'priority' => $pipe->default_priority,
            'assigned_to' => $pipe->auto_assign_to,
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $body ?: '(empty message)',
            'is_staff' => false,
            'internal' => false,
        ]);

        WorkflowEngine::fire('ticket.opened', $ticket);

        if ($pipe->auto_reply_enabled) {
            $this->sendAutoReply($pipe, $user, $ticket);
        }

        return $ticket;
    }

    private function appendReply(SupportTicket $ticket, User $user, string $body): void
    {
        SupportReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $body ?: '(empty message)',
            'is_staff' => $user->hasAnyRole(['super-admin', 'admin', 'staff']),
            'internal' => false,
        ]);

        $ticket->update([
            'status' => 'customer_reply',
            'last_reply_at' => now(),
        ]);
    }

    private function createClientFromEmail(string $email, ?string $name): User
    {
        return User::create([
            'name' => $name ?: explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ])->assignRole('client');
    }

    /**
     * Look for an existing open/answered ticket to reply to.
     * Checks:
     *   1. In-Reply-To / References header matching ticket IDs via [Ticket #N] pattern
     *   2. Subject line containing [Ticket #N]
     */
    private function resolveExistingTicket(
        string $subject,
        ?string $inReplyTo,
        ?string $references,
        User $user
    ): ?SupportTicket {
        // Check subject for [Ticket #123] marker
        if (preg_match('/\[Ticket\s*#?(\d+)\]/i', $subject, $m)) {
            $ticket = SupportTicket::where('id', (int) $m[1])
                ->whereNotIn('status', ['closed'])
                ->first();

            if ($ticket) {
                return $ticket;
            }
        }

        // Check In-Reply-To / References for same pattern
        $headers = trim(($inReplyTo ?? '').' '.($references ?? ''));
        if (preg_match('/ticket[_\-]?(\d+)/i', $headers, $m)) {
            $ticket = SupportTicket::where('id', (int) $m[1])
                ->whereNotIn('status', ['closed'])
                ->first();

            if ($ticket) {
                return $ticket;
            }
        }

        return null;
    }

    private function sendAutoReply(MailboxPipe $pipe, User $user, SupportTicket $ticket): void
    {
        $subject = $pipe->auto_reply_subject
            ?: "Re: {$ticket->subject} [Ticket #{$ticket->id}]";

        $body = $pipe->auto_reply_body
            ?: "Thank you for contacting us. Your ticket #{$ticket->id} has been received and we will respond shortly.";

        try {
            Mail::to($user->email)->send(new TemplateMailable('ticket.auto_reply', [
                'name' => $user->name,
                'app_name' => Setting::get('site_title', config('app.name')),
                'ticket_id' => $ticket->id,
                'ticket_subject' => $ticket->subject,
                'reply_body' => $body,
                'ticket_url' => route('client.support.show', $ticket->id),
            ]));
        } catch (\Throwable $e) {
            Log::warning("[MailPipe:{$pipe->id}] Auto-reply failed for ticket #{$ticket->id}: ".$e->getMessage());
        }
    }

    // ─── MIME parser ─────────────────────────────────────────────────────────

    /**
     * Parse a raw RFC 2822 email into useful fields.
     *
     * @return array{from_email: string|null, from_name: string|null, subject: string, body: string, message_id: string|null, in_reply_to: string|null, references: string|null}
     */
    public function parseEmail(string $raw): array
    {
        // Normalise line endings
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        // Split headers and body on first blank line
        $headerEnd = strpos($raw, "\n\n");
        if ($headerEnd === false) {
            return ['from_email' => null, 'from_name' => null, 'subject' => '(No Subject)', 'body' => $raw, 'message_id' => null, 'in_reply_to' => null, 'references' => null];
        }

        $headerBlock = substr($raw, 0, $headerEnd);
        $bodyBlock = substr($raw, $headerEnd + 2);

        $headers = $this->parseHeaders($headerBlock);

        $from = $headers['from'] ?? '';
        $subject = $this->decodeHeader($headers['subject'] ?? '(No Subject)');
        $messageId = $headers['message-id'] ?? null;
        $inReplyTo = $headers['in-reply-to'] ?? null;
        $references = $headers['references'] ?? null;
        $contentType = $headers['content-type'] ?? 'text/plain';
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');

        [$fromEmail, $fromName] = $this->parseFromHeader($from);

        // Decode body based on top-level content-type
        $body = $this->extractPlainBody($bodyBlock, $contentType, $encoding);

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'body' => $body,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
        ];
    }

    /**
     * Parse unfolded header block into key => value array (lowercase keys).
     */
    private function parseHeaders(string $block): array
    {
        // Unfold multi-line headers (continuation lines start with whitespace)
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $block);
        $headers = [];

        foreach (explode("\n", $unfolded) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));
            $headers[$key] = trim($value);
        }

        return $headers;
    }

    /**
     * Decode RFC 2047 encoded-word sequences (=?charset?encoding?text?=).
     */
    private function decodeHeader(string $value): string
    {
        return mb_decode_mimeheader($value);
    }

    /**
     * Parse "Display Name <email@example.com>" or bare "email@example.com".
     *
     * @return array{string|null, string|null} [email, name]
     */
    private function parseFromHeader(string $from): array
    {
        $from = trim($from);

        if (preg_match('/^(.*?)<([^>]+)>/', $from, $m)) {
            $name = trim(trim($m[1]), '"\'');
            $email = strtolower(trim($m[2]));

            return [$email ?: null, $name ?: null];
        }

        // Bare address
        $email = strtolower(filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : '');

        return [$email ?: null, null];
    }

    /**
     * Extract a text/plain body from the raw body block, handling:
     *  - quoted-printable
     *  - base64
     *  - multipart/alternative and multipart/mixed (recurse to find text/plain part)
     */
    private function extractPlainBody(string $body, string $contentType, string $encoding): string
    {
        $ctLower = strtolower($contentType);

        if (str_starts_with($ctLower, 'multipart/')) {
            // Extract boundary
            if (! preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $bm)) {
                return strip_tags($body);
            }

            $boundary = $bm[1];
            $parts = preg_split('/--'.preg_quote($boundary, '/').'(?:--)?/m', $body);

            // Remove preamble (first element) and epilogue (last element)
            array_shift($parts);
            array_pop($parts);

            $plainText = null;
            $htmlText = null;

            foreach ($parts as $part) {
                $part = ltrim($part, "\r\n");
                $partHeaderEnd = strpos($part, "\n\n");
                if ($partHeaderEnd === false) {
                    continue;
                }

                $partHeaders = $this->parseHeaders(substr($part, 0, $partHeaderEnd));
                $partBody = substr($part, $partHeaderEnd + 2);
                $partCt = $partHeaders['content-type'] ?? 'text/plain';
                $partEncoding = strtolower($partHeaders['content-transfer-encoding'] ?? '');

                $decoded = $this->decodeBody($partBody, $partEncoding);

                if (str_starts_with(strtolower($partCt), 'text/plain')) {
                    $plainText = $decoded;
                } elseif (str_starts_with(strtolower($partCt), 'text/html') && $plainText === null) {
                    $htmlText = $decoded;
                } elseif (str_starts_with(strtolower($partCt), 'multipart/')) {
                    // Nested multipart — recurse
                    $nested = $this->extractPlainBody($partBody, $partCt, $partEncoding);
                    if ($nested !== '') {
                        $plainText = $nested;
                    }
                }
            }

            if ($plainText !== null) {
                return trim($plainText);
            }

            if ($htmlText !== null) {
                return trim(strip_tags(html_entity_decode($htmlText, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            }

            return '';
        }

        // Single-part body
        $decoded = $this->decodeBody($body, $encoding);

        if (str_contains(strtolower($ctLower), 'text/html')) {
            return trim(strip_tags(html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return trim($decoded);
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => base64_decode(preg_replace('/\s+/', '', $body)) ?: $body,
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Strip common email signature delimiters.
     * Removes everything after "-- \n" (RFC-compliant sig delimiter) or
     * common patterns like "Best regards," followed by short lines.
     */
    private function stripSignature(string $body): string
    {
        // RFC 3676 sig delimiter: "-- " on its own line
        if (preg_match('/^-- ?$/m', $body, $m, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $m[0][1]);
        }

        // Common signature openers at end of email
        $patterns = [
            '/\n\s*(Best regards?|Kind regards?|Regards?|Sincerely|Thanks?|Cheers?|With regards?)[,.]?\s*\n.*/is',
            '/\n\s*(Sent from (my|iPhone|iPad|Android|Samsung|Galaxy|Outlook|Mail)[^$]*).*/is',
        ];

        foreach ($patterns as $pattern) {
            $body = preg_replace($pattern, '', $body) ?? $body;
        }

        return trim($body);
    }
}
