<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A bucket as the forecast engine sees it: its rule, funds and cycle behaviour.
 */
final readonly class EngineBucket
{
    /**
     * @param  list<int>|null  $allowedAccountIds  null or empty = income from any account may fill it
     */
    public function __construct(
        public int $id,
        public ?FillRule $rule,
        public ?string $value,
        public ?Money $target,
        public bool $isSavings,
        public Money $balance,
        public ?array $allowedAccountIds,
        public ?BudgetPeriodType $cycle,
        public CarryOverPolicy $carryOver,
        public ?int $overflowBucketId,
        public bool $isDefault,
        public int $cycleEvery = 1,
        public ?CarbonImmutable $cycleAnchor = null,
    ) {}

    public function acceptsAccount(?int $accountId): bool
    {
        return empty($this->allowedAccountIds) || ($accountId !== null && in_array($accountId, $this->allowedAccountIds, true));
    }

    /**
     * How much more it can take given its current balance (null = no limit). Mirrors the real income allocator.
     */
    public function room(Money $currentBalance): ?Money
    {
        if ($this->target === null) {
            return null;
        }

        if ($this->rule !== FillRule::FillToTarget && ! $this->isSavings) {
            return null;
        }

        $missing = $this->target->subtract($currentBalance);

        return $missing->isPositive() ? $missing : Money::zero();
    }
}
