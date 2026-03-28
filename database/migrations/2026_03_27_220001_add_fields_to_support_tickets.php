<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->tinyInteger('rating')->unsigned()->nullable()->after('status');
            $table->string('rating_note', 500)->nullable()->after('rating');
            $table->timestamp('first_replied_at')->nullable()->after('last_reply_at');
            $table->timestamp('closed_at')->nullable()->after('first_replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_note', 'first_replied_at', 'closed_at']);
        });
    }
};
