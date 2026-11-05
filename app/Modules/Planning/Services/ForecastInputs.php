<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Support\Forecast\CashTimeline;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Planning\Support\Forecast\ForecastCoverage;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Everything a forecast of one currency starts from: on-budget cash today, the buckets as they are, the planned
 * flows the chosen view counts, and display details.
 */
final readonly class ForecastInputs
{
    /**
     * @param  list<EngineBucket>  $buckets  in priority order
     * @param  list<EngineFlow>  $flows  by date, then seq
     * @param  array<int, string>  $bucketNames
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public Money $startCash,
        public array $buckets,
        public array $flows,
        public CashTimeline $timeline,
        public array $bucketNames,
        public ForecastCoverage $coverage,
    ) {}

    /**
     * Ready to assign today: on-budget cash minus every funded envelope.
     */
    public function startReadyToAssign(): Money
    {
        $free = $this->startCash;
        foreach ($this->buckets as $bucket) {
            if ($bucket->isFunded) {
                $free = $free->subtract($bucket->envelope);
            }
        }

        return $free;
    }
}
