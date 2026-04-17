<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE modules MODIFY COLUMN type ENUM('cpanel','plesk','directadmin','hestia','cwp','strata_panel','vestacp','cyberpanel','generic') NOT NULL DEFAULT 'generic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE modules MODIFY COLUMN type ENUM('cpanel','plesk','directadmin','hestia','cwp','vestacp','cyberpanel','generic') NOT NULL DEFAULT 'generic'");
    }
};
