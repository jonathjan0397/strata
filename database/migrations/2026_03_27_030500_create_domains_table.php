<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();          // full domain, e.g. "example.com"
            $table->string('registrar')->nullable();   // e.g. "enom", "opensrs", "namecheap"
            $table->enum('status', ['active', 'pending', 'expired', 'cancelled', 'transferred'])->default('pending');
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('locked')->default(true);
            $table->boolean('privacy')->default(false);
            $table->string('nameserver_1')->nullable();
            $table->string('nameserver_2')->nullable();
            $table->string('nameserver_3')->nullable();
            $table->string('nameserver_4')->nullable();
            $table->json('registrar_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
