<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\CyclePeriod;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A bucket as the forecast engine sees it: its rule, its envelope (what it holds now, signed) and whether that
 * envelope is earmarked money (funded) or only a spending limit, and how its cycles behave.
 */
final readonly class EngineBucket
{
    /**
     * @param  Money|null  $cap  the bucket's cap (a savings target or a spending limit)
     * @param  Money  $envelope  what it holds now (for a never-funded category bucket, what available() says)
     * @param  bool  $isFunded  whether its envelope counts as earmarked money
     * @param  CarbonImmutable|null  $fundedSince  from when its category's spending comes out of its envelope
     * @param  list<int>|null  $allowedAccountIds  null or empty = income from any account may fill it
     * @param  array<string, Money>  $capOverrides  cycle start (Y-m-d) => that cycle's cap
     */
    public function __construct(
        public int $id,
        public ?FillRule $rule,
        public ?string $value,
        public ?Money $cap,
        public bool $isSavings,
        public Money $envelope,
        public ?array $allowedAccountIds = null,
        public ?BudgetPeriodType $cycle = null,
        public CarryOverPolicy $carryOver = CarryOverPolicy::Carry,
        public ?int $overflowBucketId = null,
        public int $cycleEvery = 1,
        public ?CarbonImmutable $cycleAnchor = null,
        public ?Money $receivedThisCycle = null,
        public ?string $stepUpValue = null,
        public ?int $stepUpAfterBucketId = null,
        public bool $completesOnPurchase = false,
        public ?CarbonImmutable $completedOn = null,
        public bool $isFunded = true,
        public ?CarbonImmutable $fundedSince = null,
        public ?int $categoryId = null,
        public array $capOverrides = [],
        public ?CarbonImmutable $targetDate = null,
        public ?CarbonImmutable $trackingStartedOn = null,
    ) {}

    public function cyclePeriod(): ?CyclePeriod
    {
        return $this->cycle === null
            ? null
            : new CyclePeriod($this->cycle, $this->cycleEvery, $this->cycleAnchor?->toDateTimeImmutable());
    }

    /**
     * The window projections report on: its cycle, or the calendar month for a bucket without one.
     */
    public function windowPeriod(): CyclePeriod
    {
        return $this->cyclePeriod() ?? new CyclePeriod(BudgetPeriodType::Monthly);
    }

    public function acceptsAccount(?int $accountId): bool
    {
        return empty($this->allowedAccountIds) || ($accountId !== null && in_array($accountId, $this->allowedAccountIds, true));
    }

    /**
     * Whether the cap limits income (savings buckets and fill-to-target rules); a spending limit does not.
     */
    public function roomApplies(): bool
    {
        return $this->rule === FillRule::FillToTarget || $this->isSavings;
    }

    /**
     * Whether planned spending in its category comes out of it.
     */
    public function tracksCategory(?int $categoryId): bool
    {
        return ! $this->isSavings && $this->categoryId !== null && $this->categoryId === $categoryId;
    }

    /**
     * The cap on a date: that cycle's override, or the bucket's cap.
     */
    public function capOn(CarbonImmutable $date): ?Money
    {
        $period = $this->cyclePeriod();
        if ($period !== null && $this->capOverrides !== []) {
            $key = $period->startOf($date->toDateTimeImmutable())->format('Y-m-d');
            if (isset($this->capOverrides[$key])) {
                return $this->capOverrides[$key];
            }
        }

        return $this->cap;
    }
}
