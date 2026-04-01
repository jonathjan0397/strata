<?php

namespace App\Console\Commands;

use App\Models\MailboxPipe;
use App\Services\EmailPipeProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchMailboxes extends Command
{
    protected $signature = 'mail:fetch {--pipe= : Only fetch a specific pipe ID}';

    protected $description = 'Poll configured IMAP mailboxes and pipe new messages into the ticket system';

    public function handle(EmailPipeProcessor $processor): int
    {
        if (! function_exists('imap_open')) {
            $this->error('PHP IMAP extension is not installed or enabled.');
            return self::FAILURE;
        }

        $query = MailboxPipe::where('is_active', true)
            ->whereNotNull('imap_host')
            ->whereNotNull('imap_username')
            ->whereNotNull('imap_password');

        if ($id = $this->option('pipe')) {
            $query->where('id', $id);
        }

        $pipes = $query->get();

        if ($pipes->isEmpty()) {
            $this->line('No active IMAP-configured pipes found.');
            return self::SUCCESS;
        }

        foreach ($pipes as $pipe) {
            $this->fetchPipe($pipe, $processor);
        }

        return self::SUCCESS;
    }

    private function fetchPipe(MailboxPipe $pipe, EmailPipeProcessor $processor): void
    {
        $mailbox = $this->buildMailboxString($pipe);

        try {
            $imap = @imap_open($mailbox, $pipe->imap_username, $pipe->imap_password, 0, 1);
        } catch (\Throwable $e) {
            $this->warn("[Pipe:{$pipe->id}] IMAP open exception: {$e->getMessage()}");
            Log::warning("[MailPipe:{$pipe->id}] IMAP open exception: {$e->getMessage()}");
            return;
        }

        if (! $imap) {
            $error = imap_last_error();
            $this->warn("[Pipe:{$pipe->id}] Could not connect to IMAP: {$error}");
            Log::warning("[MailPipe:{$pipe->id}] IMAP connection failed: {$error}");
            return;
        }

        try {
            $uids = imap_search($imap, 'UNSEEN', SE_UID);

            if (! $uids) {
                $this->line("[Pipe:{$pipe->id}] No new messages in '{$pipe->imap_mailbox}'.");
                $pipe->update(['imap_last_checked_at' => now()]);
                return;
            }

            $count = 0;
            foreach ($uids as $uid) {
                try {
                    $header = imap_fetchheader($imap, $uid, FT_UID);
                    $body   = imap_body($imap, $uid, FT_UID);
                    $raw    = $header . "\r\n" . $body;

                    $processor->process($pipe, $raw);

                    imap_setflag_full($imap, (string) $uid, '\\Seen', ST_UID);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error("[MailPipe:{$pipe->id}] Failed to process UID {$uid}: {$e->getMessage()}");
                    $this->warn("[Pipe:{$pipe->id}] Failed UID {$uid}: {$e->getMessage()}");
                }
            }

            $this->line("[Pipe:{$pipe->id}] Processed {$count} message(s) from '{$pipe->name}'.");
            $pipe->update(['imap_last_checked_at' => now()]);
        } finally {
            try { imap_close($imap); } catch (\Throwable) {}
        }
    }

    private function buildMailboxString(MailboxPipe $pipe): string
    {
        $host       = $pipe->imap_host;
        $port       = $pipe->imap_port ?: 993;
        $mailbox    = $pipe->imap_mailbox ?: 'INBOX';
        $encryption = $pipe->imap_encryption ?? 'ssl';

        $flags = match ($encryption) {
            'ssl'  => '/imap/ssl/novalidate-cert',
            'tls'  => '/imap/tls/novalidate-cert',
            default => '/imap',
        };

        return "{{$host}:{$port}{$flags}}{$mailbox}";
    }
}
