<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN gateway ENUM('stripe','paypal','authorizenet','bank_transfer','credit','manual') DEFAULT 'stripe'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN gateway ENUM('stripe','paypal','bank_transfer','credit','manual') DEFAULT 'stripe'");
    }
};
