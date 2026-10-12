<?php

namespace App\Modules\Planning\Console\Commands;

use App\Modules\Planning\Services\PlannedEventService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('planning:extend-series')]
#[Description('Create the missing planned events of repeating series up to 12 months ahead')]
class ExtendPlannedEventSeries extends Command
{
    public function handle(PlannedEventService $service): void
    {
        $created = $service->extendAllSeries();

        $this->info("Created {$created} planned events.");
    }
}
