<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->enum('type', ['note', 'call', 'email', 'meeting'])->default('note')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
