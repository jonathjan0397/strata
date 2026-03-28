<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('name');
            $table->string('short_description', 255)->nullable()->after('category');
            $table->json('configurable_options')->nullable()->after('module_config');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'short_description', 'configurable_options']);
        });
    }
};
