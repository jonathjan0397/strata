<?php

namespace App\Console\Commands;

use App\Services\StrataLicense;
use Illuminate\Console\Command;

class StrataSync extends Command
{
    protected $signature   = 'strata:sync';
    protected $description = 'Sync platform telemetry and refresh license cache.';

    public function handle(): int
    {
        StrataLicense::refresh();
        return Command::SUCCESS;
    }
}
