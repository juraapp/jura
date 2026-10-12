<?php

namespace App\Modules\Planning\Console\Commands;

use App\Modules\Planning\Services\BucketCycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('planning:close-bucket-cycles')]
#[Description('Apply each bucket\'s carry-over policy for cycles that have ended')]
class CloseBucketCycles extends Command
{
    public function handle(BucketCycleService $cycles): void
    {
        $closed = $cycles->closeDue();

        $this->info("Closed {$closed} bucket cycles.");
    }
}
