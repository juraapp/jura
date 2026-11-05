<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Everything the engine needs apart from the scenario's purchase lines: on-budget cash at the start, the buckets
 * and the planned flows (already filtered to what the chosen view trusts). Ready to assign is never an input: it is
 * always cash minus the funded envelopes.
 */
final readonly class EngineInput
{
    public CashTimeline $timeline;

    /**
     * @param  list<EngineBucket>  $buckets  in filling priority order
     * @param  list<EngineFlow>  $flows
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public Money $startCash,
        public array $buckets,
        public array $flows = [],
        ?CashTimeline $timeline = null,
    ) {
        $this->timeline = $timeline ?? CashTimeline::build($start, $startCash, $flows);
    }

    /**
     * Ready to assign at the start: cash minus every funded envelope.
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
