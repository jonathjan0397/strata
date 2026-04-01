<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('local_hostname')->nullable()->after('hostname');
            $table->unsignedSmallInteger('local_port')->nullable()->after('local_hostname');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['local_hostname', 'local_port']);
        });
    }
};
