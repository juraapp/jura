<?php

namespace App\Modules\Planning\Support\Forecast;

use Carbon\CarbonImmutable;

/**
 * Everything the engine needs apart from the scenario's purchase lines.
 */
final readonly class EngineInput
{
    /**
     * @param  list<EngineBucket>  $buckets  in filling priority order
     * @param  list<EngineIncome>  $incomes  already filtered to what the chosen view trusts
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $buckets,
        public array $incomes,
    ) {}

    public function defaultBucket(): ?EngineBucket
    {
        foreach ($this->buckets as $bucket) {
            if ($bucket->isDefault) {
                return $bucket;
            }
        }

        return null;
    }
}
