<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE services MODIFY COLUMN status ENUM('pending','active','suspended','cancelled','terminated','cancellation_requested') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Move any cancellation_requested rows back to active before narrowing the enum
        DB::statement("UPDATE services SET status='active' WHERE status='cancellation_requested'");
        DB::statement("ALTER TABLE services MODIFY COLUMN status ENUM('pending','active','suspended','cancelled','terminated') NOT NULL DEFAULT 'pending'");
    }
};
