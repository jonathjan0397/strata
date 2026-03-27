<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('domain')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled', 'terminated'])->default('pending');
            $table->decimal('amount', 10, 2);                // billed amount (may differ from product price)
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annual', 'annual', 'biennial', 'triennial', 'one_time'])->default('monthly');
            $table->date('registration_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('username')->nullable();          // control-panel username
            $table->text('password_enc')->nullable();        // encrypted service password
            $table->string('server_hostname')->nullable();   // provisioned server
            $table->integer('server_port')->nullable();
            $table->json('module_data')->nullable();         // arbitrary provisioning metadata
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
