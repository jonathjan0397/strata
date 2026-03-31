<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company', 255)->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('company');
            $table->string('website', 255)->nullable()->after('phone');
            $table->string('lead_source', 100)->nullable()->after('website');
            $table->enum('client_status', ['prospect', 'active', 'inactive', 'at_risk', 'churned'])
                ->default('active')->after('lead_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company', 'phone', 'website', 'lead_source', 'client_status']);
        });
    }
};
