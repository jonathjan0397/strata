<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // the client
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // staff member
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tasks');
    }
};
