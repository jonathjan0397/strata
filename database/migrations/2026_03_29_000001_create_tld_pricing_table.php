<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tld_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('tld', 32)->unique();          // e.g. ".com"
            $table->decimal('register_cost', 10, 4)->nullable();  // raw registrar cost
            $table->decimal('renew_cost', 10, 4)->nullable();
            $table->decimal('transfer_cost', 10, 4)->nullable();
            $table->enum('markup_type', ['fixed', 'percent'])->default('percent');
            $table->decimal('markup_value', 10, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tld_pricing');
    }
};
