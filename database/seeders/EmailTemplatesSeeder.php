<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'auth.welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {{app_name}}!',
                'body_html' => '<p>Hi {{name}},</p><p>Welcome to {{app_name}}! Your account has been created successfully.</p><p><a href="{{login_url}}" class="btn">Log In to Your Account</a></p><p>If you have any questions, please open a support ticket and we\'ll be happy to help.</p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nWelcome to {{app_name}}! Your account has been created successfully.\n\nLog in: {{login_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'invoice.created',
                'name' => 'Invoice Created',
                'subject' => 'Invoice #{{invoice_id}} — ${{amount}} Due {{due_date}}',
                'body_html' => '<p>Hi {{name}},</p><p>A new invoice has been generated on your account.</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Invoice #</td><td style="padding:8px 0;font-weight:600">{{invoice_id}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Amount Due</td><td style="padding:8px 0;font-weight:600">${{amount}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Due Date</td><td style="padding:8px 0">{{due_date}}</td></tr></table><p><a href="{{invoice_url}}" class="btn">View & Pay Invoice</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nInvoice #{{invoice_id}} for \${{amount}} is due on {{due_date}}.\n\nPay here: {{invoice_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'invoice.paid',
                'name' => 'Invoice Paid Confirmation',
                'subject' => 'Payment Received — Invoice #{{invoice_id}}',
                'body_html' => '<p>Hi {{name}},</p><p>We\'ve received your payment of <strong>${{amount}}</strong> for Invoice #{{invoice_id}}. Thank you!</p><p><a href="{{invoice_url}}" class="btn">Download Invoice</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nPayment of \${{amount}} received for Invoice #{{invoice_id}}. Thank you!\n\nDownload: {{invoice_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'invoice.overdue',
                'name' => 'Invoice Overdue Reminder',
                'subject' => 'Overdue: Invoice #{{invoice_id}} — Action Required',
                'body_html' => '<p>Hi {{name}},</p><p>Invoice #{{invoice_id}} for <strong>${{amount}}</strong> was due on {{due_date}} and remains unpaid. Please pay as soon as possible to avoid service interruption.</p><p><a href="{{invoice_url}}" class="btn">Pay Now</a></p><p>If you believe this is an error, please contact support.</p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nInvoice #{{invoice_id}} for \${{amount}} was due on {{due_date}} and is overdue.\n\nPay now: {{invoice_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'service.activated',
                'name' => 'Service Activated',
                'subject' => 'Your Service is Active — {{service_name}}',
                'body_html' => '<p>Hi {{name}},</p><p>Great news! Your service has been activated.</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Service</td><td style="padding:8px 0;font-weight:600">{{service_name}}</td></tr>{{#domain}}<tr><td style="padding:8px 0;color:#6b7280">Domain</td><td style="padding:8px 0">{{domain}}</td></tr>{{/domain}}{{#username}}<tr><td style="padding:8px 0;color:#6b7280">Username</td><td style="padding:8px 0"><code>{{username}}</code></td></tr>{{/username}}</table><p><a href="{{portal_url}}" class="btn">View in Client Portal</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour service {{service_name}} is now active.\n\nView it here: {{portal_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'service.suspended',
                'name' => 'Service Suspended',
                'subject' => 'Service Suspended — {{service_name}}',
                'body_html' => '<p>Hi {{name}},</p><p>Your service <strong>{{service_name}}</strong> has been suspended due to an outstanding unpaid invoice.</p><p>To reactivate your service, please pay your outstanding invoice:</p><p><a href="{{invoices_url}}" class="btn">View Invoices</a></p><p>Once payment is received, your service will be reactivated promptly. If you believe this is an error, please contact support.</p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour service {{service_name}} has been suspended. Please pay your outstanding invoice to reactivate.\n\n{{invoices_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'service.active',
                'name' => 'Service Welcome Email',
                'subject' => 'Your Service is Ready — {{service_name}}',
                'body_html' => '<p>Hi {{name}},</p><p>Great news — your service has been activated and is ready to use!</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Service</td><td style="padding:8px 0;font-weight:600">{{service_name}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Domain</td><td style="padding:8px 0">{{domain}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Username</td><td style="padding:8px 0"><code>{{username}}</code></td></tr><tr><td style="padding:8px 0;color:#6b7280">Password</td><td style="padding:8px 0"><code>{{password}}</code></td></tr><tr><td style="padding:8px 0;color:#6b7280">Server</td><td style="padding:8px 0">{{server}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Nameserver 1</td><td style="padding:8px 0">{{nameserver1}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Nameserver 2</td><td style="padding:8px 0">{{nameserver2}}</td></tr></table><p><a href="{{portal_url}}" class="btn">View in Client Portal</a></p><p>If you have any questions, please open a support ticket and we\'ll be happy to help.</p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour service {{service_name}} ({{domain}}) is now active.\n\nUsername: {{username}}\nPassword: {{password}}\nServer: {{server}}\nNameserver 1: {{nameserver1}}\nNameserver 2: {{nameserver2}}\n\nView it here: {{portal_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'support.reply',
                'name' => 'Support Ticket Reply',
                'subject' => 'Reply to Ticket #{{ticket_id}}: {{ticket_subject}}',
                'body_html' => '<p>Hi {{name}},</p><p>A reply has been added to your support ticket <strong>#{{ticket_id}}: {{ticket_subject}}</strong>.</p><blockquote style="border-left:3px solid #e5e7eb;margin:16px 0;padding:12px 16px;color:#6b7280">{{reply_body}}</blockquote><p><a href="{{ticket_url}}" class="btn">View Ticket</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nNew reply on Ticket #{{ticket_id}}: {{ticket_subject}}\n\n{{reply_body}}\n\nView: {{ticket_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'support.opened',
                'name' => 'New Support Ticket (Admin Notification)',
                'subject' => '[{{priority}}] New Ticket #{{ticket_id}}: {{ticket_subject}}',
                'body_html' => '<p>A new support ticket has been submitted.</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Ticket #</td><td style="padding:8px 0;font-weight:600">{{ticket_id}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">From</td><td style="padding:8px 0">{{name}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Subject</td><td style="padding:8px 0">{{ticket_subject}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Priority</td><td style="padding:8px 0">{{priority}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Department</td><td style="padding:8px 0">{{department}}</td></tr></table><p><a href="{{ticket_url}}" class="btn">View Ticket</a></p>',
                'body_plain' => "New ticket submitted.\n\nTicket: #{{ticket_id}}\nFrom: {{name}}\nSubject: {{ticket_subject}}\nPriority: {{priority}}\nDepartment: {{department}}\n\nView: {{ticket_url}}",
            ],
            [
                'slug' => 'support.closed',
                'name' => 'Support Ticket Auto-Closed',
                'subject' => 'Your Ticket #{{ticket_id}} Has Been Closed',
                'body_html' => '<p>Hi {{name}},</p><p>Your support ticket <strong>#{{ticket_id}}: {{subject}}</strong> has been automatically closed due to inactivity.</p><p>If you still need help, please open a new ticket or reply to this email to reopen it.</p><p><a href="{{ticket_url}}" class="btn">View Ticket</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour support ticket #{{ticket_id}}: {{subject}} has been automatically closed due to inactivity.\n\nIf you still need help, please open a new ticket.\n\nView: {{ticket_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
            [
                'slug' => 'support.assigned',
                'name' => 'Support Ticket Assigned to You',
                'subject' => 'Ticket #{{ticket_id}} Assigned to You: {{ticket_subject}}',
                'body_html' => '<p>Hi {{name}},</p><p>Support ticket <strong>#{{ticket_id}}: {{ticket_subject}}</strong> has been assigned to you.</p><p><a href="{{ticket_url}}" class="btn">View Ticket</a></p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nTicket #{{ticket_id}}: {{ticket_subject}} has been assigned to you.\n\nView: {{ticket_url}}\n\nThanks,\nThe {{app_name}} Team",
            ],
        ];

        foreach ($templates as $data) {
            EmailTemplate::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
