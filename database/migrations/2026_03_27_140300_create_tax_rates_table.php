<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "US Sales Tax", "EU VAT"
            $table->decimal('rate', 5, 2);                   // e.g. 20.00 = 20%
            $table->string('country', 2)->nullable();        // ISO 3166-1 alpha-2, null = global default
            $table->string('state', 10)->nullable();         // state/province code, null = all states
            $table->boolean('is_default')->default(false);   // fallback when no country matches
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['country', 'state']);
        });

        // Add country + tax_exempt to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('client_group_id');
            $table->string('state', 10)->nullable()->after('country');
            $table->boolean('tax_exempt')->default(false)->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'state', 'tax_exempt']);
        });
        Schema::dropIfExists('tax_rates');
    }
};
