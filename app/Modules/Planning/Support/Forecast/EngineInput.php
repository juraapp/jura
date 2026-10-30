<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Everything the engine needs apart from the scenario's purchase lines.
 */
final readonly class EngineInput
{
    /**
     * @param  list<EngineBucket>  $buckets  in filling priority order
     * @param  list<EngineIncome>  $incomes  already filtered to what the chosen view trusts (on-budget accounts only)
     * @param  Money|null  $readyToAssign  money in the accounts that no bucket holds at the start (null = none)
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $buckets,
        public array $incomes,
        public ?Money $readyToAssign = null,
    ) {}
}
