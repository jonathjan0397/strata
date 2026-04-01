<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_pipes', function (Blueprint $table) {
            $table->string('imap_host')->nullable()->after('email_address');
            $table->unsignedSmallInteger('imap_port')->default(993)->after('imap_host');
            $table->string('imap_username')->nullable()->after('imap_port');
            $table->text('imap_password')->nullable()->after('imap_username');  // stored encrypted
            $table->enum('imap_encryption', ['ssl', 'tls', 'none'])->default('ssl')->after('imap_password');
            $table->string('imap_mailbox', 100)->default('INBOX')->after('imap_encryption');
            $table->timestamp('imap_last_checked_at')->nullable()->after('imap_mailbox');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_pipes', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host', 'imap_port', 'imap_username', 'imap_password',
                'imap_encryption', 'imap_mailbox', 'imap_last_checked_at',
            ]);
        });
    }
};
