<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fraud scoring on orders
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('fraud_score', 5, 2)->nullable()->after('client_notes');
            $table->json('fraud_flags')->nullable()->after('fraud_score');
        });

        // Quotes table
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('quote_number', 30)->nullable()->unique();
            $table->enum('status', ['draft', 'sent', 'accepted', 'declined', 'expired'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('client_message')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Quote line items
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        // Seed quote.sent email template
        DB::table('email_templates')->insertOrIgnore([[
            'slug'       => 'quote.sent',
            'name'       => 'Quote Sent',
            'subject'    => 'Your Quote from {{app_name}} — {{quote_number}}',
            'body_html'  => implode("\n", [
                '<p>Hi {{name}},</p>',
                '<p>We\'ve prepared a quote for you. Please review it at your convenience.</p>',
                '<p><strong>Quote:</strong> {{quote_number}}<br>',
                '<strong>Total:</strong> ${{total}}<br>',
                '<strong>Valid Until:</strong> {{valid_until}}</p>',
                '{{#message}}<p>{{message}}</p>{{/message}}',
                '<p><a href="{{quote_url}}">View &amp; Accept Quote</a></p>',
                '<p>Thank you,<br>{{app_name}}</p>',
            ]),
            'body_plain' => implode("\n", [
                'Hi {{name}},',
                '',
                'We\'ve prepared a quote for you.',
                '',
                'Quote: {{quote_number}}',
                'Total: ${{total}}',
                'Valid Until: {{valid_until}}',
                '',
                '{{message}}',
                '',
                'View & Accept: {{quote_url}}',
                '',
                'Thank you,',
                '{{app_name}}',
            ]),
            'variables'  => 'name,app_name,quote_number,total,valid_until,quote_url,message',
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fraud_score', 'fraud_flags']);
        });

        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');

        DB::table('email_templates')->where('slug', 'quote.sent')->delete();
    }
};
