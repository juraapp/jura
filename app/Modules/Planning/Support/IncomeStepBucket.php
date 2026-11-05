<?php

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Enums\FillRule;
use App\Modules\Shared\Support\Money;

/**
 * A bucket as one income sees it, whoever builds it (the real allocator from the database, the forecast from its
 * simulated state): its rule and fill value on the income's date, its cap, what it holds and whether it may take
 * money from this income at all.
 */
final readonly class IncomeStepBucket
{
    /**
     * @param  string|null  $value  the fill value on the income's date (step-up already resolved)
     * @param  Money|null  $cap  the cap that applies on the income's date (per-cycle override already resolved)
     * @param  bool  $roomApplies  whether the cap limits income (savings buckets and fill-to-target rules); a spending limit does not
     * @param  Money|null  $receivedInCycle  income already received in the current cycle (null = no cycle)
     * @param  bool  $eligible  whether this income may fill it (allowed accounts, not completed)
     */
    public function __construct(
        public int $id,
        public ?FillRule $rule,
        public ?string $value,
        public ?Money $cap,
        public bool $roomApplies,
        public Money $available,
        public ?Money $receivedInCycle,
        public bool $eligible,
    ) {}

    /**
     * How much more it can take before reaching its cap (null = no limit).
     */
    public function room(): ?Money
    {
        if ($this->cap === null || ! $this->roomApplies) {
            return null;
        }

        $missing = $this->cap->subtract($this->available);

        return $missing->isPositive() ? $missing : Money::zero();
    }
}
