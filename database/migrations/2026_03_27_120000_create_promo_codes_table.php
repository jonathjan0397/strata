<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->enum('type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 10, 2);                      // % or $ amount
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // null = global
            $table->unsignedInteger('max_uses')->nullable();       // null = unlimited
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('applies_once')->default(false);       // one use per client
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
