<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        $toAddresses = array_keys($message->getTo() ?? []);
        $to          = implode(', ', $toAddresses);
        $subject     = $message->getSubject() ?? '(no subject)';
        $body        = $message->getHtmlBody() ?? $message->getTextBody();

        // Try to resolve a user_id from the recipient email
        $userId = null;
        if (count($toAddresses) === 1) {
            $userId = User::where('email', $toAddresses[0])->value('id');
        }

        EmailLog::create([
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'mailer'  => config('mail.default', 'smtp'),
            'user_id' => $userId,
            'sent_at' => now(),
        ]);
    }
}
