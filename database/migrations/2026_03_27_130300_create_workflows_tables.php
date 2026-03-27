<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main workflow definition
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger');          // invoice.created, invoice.paid, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Conditions that must ALL pass for the workflow to run
        Schema::create('workflow_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('field', 100);        // e.g. invoice.total, client.role
            $table->string('operator', 20);      // eq, neq, gt, lt, gte, lte, contains
            $table->string('value', 255);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        // Actions executed in order when workflow fires
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);         // send.email, create.ticket, suspend.service, add.credit, call.webhook
            $table->json('config');              // action-specific config
            $table->unsignedSmallInteger('delay_minutes')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        // Run history
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->enum('status', ['completed', 'failed', 'skipped'])->default('completed');
            $table->json('log')->nullable();
            $table->timestamp('ran_at')->useCurrent();

            $table->index(['workflow_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflows');
    }
};
