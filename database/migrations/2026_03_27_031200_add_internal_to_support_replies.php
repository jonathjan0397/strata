<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_replies', function (Blueprint $table) {
            $table->boolean('internal')->default(false)->after('is_staff')
                ->comment('Internal staff notes — hidden from client');
        });
    }

    public function down(): void
    {
        Schema::table('support_replies', function (Blueprint $table) {
            $table->dropColumn('internal');
        });
    }
};
