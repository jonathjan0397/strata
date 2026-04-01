<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE modules MODIFY COLUMN type ENUM(
            'cpanel','plesk','directadmin','vestacp','cyberpanel','generic','hestia','cwp'
        ) NOT NULL DEFAULT 'generic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE modules MODIFY COLUMN type ENUM(
            'cpanel','plesk','directadmin','vestacp','cyberpanel','generic'
        ) NOT NULL DEFAULT 'generic'");
    }
};
