<?php

namespace App\Modules\Planning\Jobs;

use App\Modules\Planning\Services\SeriesPoster;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly. Unique, so a queue backlog never runs two batches side by side; each plan is also locked and
 * re-checked while it is recorded (PlannedEventService::realize).
 */
class PostDuePlannedEvents implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Seconds the uniqueness lock lasts if a run dies without releasing it. */
    public int $uniqueFor = 3300;

    public function handle(SeriesPoster $poster): void
    {
        $poster->postDue();
    }
}
