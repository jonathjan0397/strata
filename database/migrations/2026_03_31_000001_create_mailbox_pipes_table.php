<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_pipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // e.g. "Support", "Billing", "Sales"
            $table->string('email_address')->nullable();               // display-only; actual routing done by mail server
            $table->string('pipe_token')->unique();                    // used in POST /pipe/{token} and artisan mail:pipe {token}
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('auto_assign_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('default_priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->boolean('create_client_if_not_exists')->default(true);
            $table->boolean('strip_signature')->default(true);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->string('auto_reply_subject')->nullable();
            $table->text('auto_reply_body')->nullable();
            $table->boolean('reject_unknown_senders')->default(false); // if true + !create_client, drop the email
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_pipes');
    }
};
