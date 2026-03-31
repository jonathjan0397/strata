<?php

namespace App\Console\Commands;

use App\Models\MailboxPipe;
use App\Services\EmailPipeProcessor;
use Illuminate\Console\Command;

/**
 * Reads a raw email from STDIN and routes it to the correct pipe.
 *
 * Usage in .forward or procmail:
 *   | php /home/user/public_html/artisan mail:pipe {token}
 *
 * Or via cron + mail server pipe script:
 *   php artisan mail:pipe abc123def... < /path/to/message.eml
 */
class PipeEmail extends Command
{
    protected $signature = 'mail:pipe {token : The pipe token (from Admin → Settings → Mail Pipes)}';

    protected $description = 'Pipe an incoming email (read from STDIN) into the ticket system';

    public function handle(EmailPipeProcessor $processor): int
    {
        $token = $this->argument('token');

        $pipe = MailboxPipe::where('pipe_token', $token)->where('is_active', true)->first();

        if (! $pipe) {
            $this->error("No active mail pipe found for token: {$token}");

            return self::FAILURE;
        }

        // Read raw email from STDIN
        $raw = '';
        while (! feof(STDIN)) {
            $raw .= fread(STDIN, 8192);
        }

        if (empty(trim($raw))) {
            $this->warn('No email data received on STDIN.');

            return self::SUCCESS;
        }

        try {
            $processor->process($pipe, $raw);
            $this->line("Email processed via pipe '{$pipe->name}'.");
        } catch (\Throwable $e) {
            $this->error('Failed to process email: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
