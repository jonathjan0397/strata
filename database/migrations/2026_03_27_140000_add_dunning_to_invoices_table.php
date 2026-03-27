<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedTinyInteger('dunning_attempts')->default(0)->after('payment_method');
            $table->timestamp('dunning_last_attempt_at')->nullable()->after('dunning_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['dunning_attempts', 'dunning_last_attempt_at']);
        });
    }
};
