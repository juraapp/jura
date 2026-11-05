<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Support\CyclePeriod;
use Carbon\CarbonImmutable;

/**
 * Remembers each bucket's cycle and which cycle a day falls in, for every run over the same input (the suggester
 * runs the engine hundreds of times on one input). Internal to the engine.
 *
 * @internal
 */
final class EngineCalendar
{
    /** @var array<int, CyclePeriod|null> */
    private array $periods = [];

    /** @var array<int, array<string, string|null>> bucket id => day => start of its cycle */
    private array $starts = [];

    public function period(EngineBucket $bucket): ?CyclePeriod
    {
        if (! array_key_exists($bucket->id, $this->periods)) {
            $this->periods[$bucket->id] = $bucket->cyclePeriod();
        }

        return $this->periods[$bucket->id];
    }

    /**
     * The start (Y-m-d) of the bucket's cycle that contains the day, or null when it has no cycle.
     */
    public function cycleStart(EngineBucket $bucket, CarbonImmutable $date): ?string
    {
        $day = $date->format('Y-m-d');
        if (! isset($this->starts[$bucket->id]) || ! array_key_exists($day, $this->starts[$bucket->id])) {
            $this->starts[$bucket->id][$day] = $this->period($bucket)?->startOf($date->toDateTimeImmutable())->format('Y-m-d');
        }

        return $this->starts[$bucket->id][$day];
    }
}
