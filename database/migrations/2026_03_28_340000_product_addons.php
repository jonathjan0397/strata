<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Addon product catalog
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->string('billing_cycle', 20)->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Addon instances attached to services
        Schema::create('service_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'pending', 'suspended', 'cancelled'])->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('billing_cycle', 20)->default('monthly');
            $table->date('next_due_date')->nullable();
            $table->timestamps();
        });

        // Allow invoice items to reference a service addon (for addon renewal billing)
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'service_addon_id')) {
                $table->unsignedBigInteger('service_addon_id')->nullable()->after('service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('service_addon_id');
        });

        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('addons');
    }
};
