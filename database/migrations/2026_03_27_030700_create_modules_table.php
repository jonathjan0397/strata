<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cpanel', 'plesk', 'directadmin', 'vestacp', 'cyberpanel', 'generic'])->default('generic');
            $table->string('hostname');
            $table->integer('port')->default(2087);
            $table->string('username');
            $table->text('api_token_enc')->nullable();    // encrypted API token
            $table->text('password_enc')->nullable();    // encrypted password fallback
            $table->boolean('ssl')->default(true);
            $table->boolean('active')->default(true);
            $table->integer('max_accounts')->nullable(); // null = unlimited
            $table->integer('current_accounts')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
