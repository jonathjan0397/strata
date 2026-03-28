<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Promo code enhancements ───────────────────────────────────────────
        Schema::table('promo_codes', function (Blueprint $table) {
            // Widen the type enum to support free_setup
            $table->enum('type', ['percent', 'fixed', 'free_setup'])
                ->default('percent')
                ->change();

            // Valid-from date (null = valid immediately)
            $table->timestamp('starts_at')->nullable()->after('expires_at');

            // How many billing cycles the discount carries forward:
            //   null / 0 = first invoice only
            //   n > 0    = n invoices
            //   -1       = all invoices (forever)
            $table->smallInteger('recurring_cycles')->nullable()->after('starts_at');

            // Restrict to clients with no prior active/paid services
            $table->boolean('new_clients_only')->default(false)->after('recurring_cycles');
        });

        // ── Service cancellation type ─────────────────────────────────────────
        Schema::table('services', function (Blueprint $table) {
            // Set by client when requesting cancellation
            $table->enum('cancellation_type', ['immediate', 'end_of_period'])
                ->nullable()
                ->after('cancellation_requested_at');

            // Set by admin when approving an end_of_period cancellation
            // Service stays active until this date, then auto-cancelled by scheduler
            $table->date('scheduled_cancel_at')->nullable()->after('cancellation_type');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->enum('type', ['percent', 'fixed'])->default('percent')->change();
            $table->dropColumn(['starts_at', 'recurring_cycles', 'new_clients_only']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['cancellation_type', 'scheduled_cancel_at']);
        });
    }
};
