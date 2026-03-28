<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add autosetup trigger to products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'autosetup')) {
                $table->enum('autosetup', ['on_order', 'on_payment', 'manual', 'never'])
                    ->default('manual')
                    ->after('module');
            }
        });

        // Add order number and client notes to orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number', 30)->nullable()->unique()->after('user_id');
            }
            if (!Schema::hasColumn('orders', 'client_notes')) {
                $table->text('client_notes')->nullable()->after('notes');
            }
        });

        // Update service.active email template to include credential variables
        DB::table('email_templates')
            ->where('slug', 'service.active')
            ->update([
                'body_html' => '<p>Hi {{name}},</p><p>Great news — your service has been activated and is ready to use!</p><table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><td style="padding:8px 0;color:#6b7280">Service</td><td style="padding:8px 0;font-weight:600">{{service_name}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Domain</td><td style="padding:8px 0">{{domain}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Username</td><td style="padding:8px 0"><code>{{username}}</code></td></tr><tr><td style="padding:8px 0;color:#6b7280">Password</td><td style="padding:8px 0"><code>{{password}}</code></td></tr><tr><td style="padding:8px 0;color:#6b7280">Server</td><td style="padding:8px 0">{{server}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Nameserver 1</td><td style="padding:8px 0">{{nameserver1}}</td></tr><tr><td style="padding:8px 0;color:#6b7280">Nameserver 2</td><td style="padding:8px 0">{{nameserver2}}</td></tr></table><p><a href="{{portal_url}}" class="btn">View in Client Portal</a></p><p>If you have any questions, please open a support ticket and we\'ll be happy to help.</p><p>Thanks,<br>The {{app_name}} Team</p>',
                'body_plain' => "Hi {{name}},\n\nYour service {{service_name}} ({{domain}}) is now active.\n\nUsername: {{username}}\nPassword: {{password}}\nServer: {{server}}\nNameserver 1: {{nameserver1}}\nNameserver 2: {{nameserver2}}\n\nView it here: {{portal_url}}\n\nThanks,\nThe {{app_name}} Team",
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('autosetup');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'client_notes']);
        });
    }
};
