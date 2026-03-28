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

            if (!Schema::hasColumn('promo_codes', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('promo_codes', 'recurring_cycles')) {
                $table->smallInteger('recurring_cycles')->nullable()->after('starts_at');
            }
            if (!Schema::hasColumn('promo_codes', 'new_clients_only')) {
                $table->boolean('new_clients_only')->default(false)->after('recurring_cycles');
            }
        });

        // ── Service cancellation type ─────────────────────────────────────────
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'cancellation_type')) {
                $table->enum('cancellation_type', ['immediate', 'end_of_period'])
                    ->nullable()
                    ->after('cancellation_requested_at');
            }
            if (!Schema::hasColumn('services', 'scheduled_cancel_at')) {
                $table->date('scheduled_cancel_at')->nullable()->after('cancellation_type');
            }
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
