<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['shared', 'reseller', 'vps', 'dedicated', 'domain', 'ssl', 'other'])->default('shared');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annual', 'annual', 'biennial', 'triennial', 'one_time'])->default('monthly');
            $table->string('module')->nullable();        // e.g. "cpanel", "plesk", "directadmin"
            $table->json('module_config')->nullable();   // module-specific fields
            $table->integer('stock')->nullable();        // null = unlimited
            $table->boolean('hidden')->default(false);
            $table->boolean('taxable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
