<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

final readonly class EngineResult
{
    /**
     * @param  list<LineResult>  $lines  in scenario order
     * @param  array<int, Money>  $endBalances  bucket id => envelope at the end of the horizon
     * @param  Money  $endReadyToAssign  cash minus funded envelopes at the end of the horizon
     * @param  list<TimelinePoint>  $timeline  one point per simulated day, ascending
     * @param  array<int, CycleOutcome>  $cycles  bucket id => its current window
     * @param  array<int, CarbonImmutable>  $reached  bucket id => the day it first held its cap (or closed)
     */
    public function __construct(
        public array $lines,
        public array $endBalances,
        public Money $endCash,
        public Money $endReadyToAssign,
        public array $timeline,
        public array $cycles,
        public array $reached,
        public IncomeSummary $incomes,
    ) {}

    /**
     * The state at the end of a day: the last simulated day on or before it (null before the start).
     */
    public function pointAt(CarbonImmutable $date): ?TimelinePoint
    {
        $key = $date->format('Y-m-d');
        $low = 0;
        $high = count($this->timeline) - 1;
        $found = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->timeline[$mid]->date->format('Y-m-d') <= $key) {
                $found = $this->timeline[$mid];
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $found;
    }
}
