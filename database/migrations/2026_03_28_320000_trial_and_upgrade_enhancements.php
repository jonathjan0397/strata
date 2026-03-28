<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trial days on products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'trial_days')) {
                $table->unsignedSmallInteger('trial_days')
                    ->nullable()
                    ->default(null)
                    ->after('autosetup')
                    ->comment('Free trial length in days; null = no trial');
            }
        });

        // Trial end date on services
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'trial_ends_at')) {
                $table->date('trial_ends_at')
                    ->nullable()
                    ->after('scheduled_cancel_at')
                    ->comment('Date the free trial expires; null = not a trial service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
