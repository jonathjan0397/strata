<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'slug' => 'support.opened',
                'name' => 'New Support Ticket (Admin Notification)',
                'subject' => '[{{priority}}] New Ticket #{{ticket_id}}: {{ticket_subject}}',
                'body_html' => '<p>A new support ticket has been submitted.</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Ticket #</td><td style="padding:8px 0;font-weight:600">{{ticket_id}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">From</td><td style="padding:8px 0">{{name}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Subject</td><td style="padding:8px 0">{{ticket_subject}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Priority</td><td style="padding:8px 0">{{priority}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Department</td><td style="padding:8px 0">{{department}}</td></tr></table><p><a href="{{ticket_url}}" style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600">View Ticket</a></p>',
                'body_plain' => "New ticket submitted.\n\nTicket: #{{ticket_id}}\nFrom: {{name}}\nSubject: {{ticket_subject}}\nPriority: {{priority}}\nDepartment: {{department}}\n\nView: {{ticket_url}}",
            ],
            [
                'slug' => 'support.closed',
                'name' => 'Support Ticket Auto-Closed',
                'subject' => 'Your Ticket #{{ticket_id}} Has Been Closed',
                'body_html' => '<p>Hi {{name}},</p><p>Your support ticket <strong>#{{ticket_id}}: {{subject}}</strong> has been automatically closed due to inactivity.</p><p>If you still need help, please open a new ticket.</p><p><a href="{{ticket_url}}" style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600">View Ticket</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour support ticket #{{ticket_id}}: {{subject}} has been automatically closed due to inactivity.\n\nIf you still need help, please open a new ticket.\n\nView: {{ticket_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'support.assigned',
                'name' => 'Support Ticket Assigned to You',
                'subject' => 'Ticket #{{ticket_id}} Assigned to You: {{ticket_subject}}',
                'body_html' => '<p>Hi {{name}},</p><p>Support ticket <strong>#{{ticket_id}}: {{ticket_subject}}</strong> has been assigned to you.</p><p><a href="{{ticket_url}}" style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600">View Ticket</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nTicket #{{ticket_id}}: {{ticket_subject}} has been assigned to you.\n\nView: {{ticket_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
        ];

        foreach ($templates as $data) {
            EmailTemplate::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }

    public function down(): void
    {
        EmailTemplate::whereIn('slug', ['support.opened', 'support.closed', 'support.assigned'])->delete();
    }
};
