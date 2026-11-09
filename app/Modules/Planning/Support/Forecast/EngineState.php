<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The mutable state of one ForecastEngine run. Earmarked money is kept as a running total of the funded envelopes;
 * ready to assign is always cash minus it. Internal to the engine.
 *
 * @internal
 */
final class EngineState
{
    /** @var array<int, EngineBucket> */
    public array $buckets = [];

    /** @var array<int, Money> envelope per bucket */
    public array $env = [];

    /** @var array<int, bool> */
    public array $funded = [];

    /** @var array<int, CarbonImmutable|null> */
    public array $fundedSince = [];

    /** @var array<int, array<string, Money>> income received per bucket and cycle start */
    public array $received = [];

    /** @var array<int, CarbonImmutable> */
    public array $completed = [];

    /** @var array<int, CarbonImmutable> */
    public array $reached = [];

    public Money $earmarked;

    public Money $cash;

    /** Money spent on purchases so far (it lowers every later balance of the cash timeline). */
    public Money $committed;

    public int $next = 0;

    /** The day of the latest purchase (Y-m-d): later items wait until then. */
    public string $lastPurchaseDay;

    /** The day being simulated (Y-m-d). */
    public string $day;

    /** Whether some bucket received money since reached caps were last checked. */
    private bool $grew = false;

    /** @var array<int, array{0: CarbonImmutable, 1: Money, 2: Money}> line index => [date, from bucket, from free] */
    public array $bought = [];

    /** @var array<int, string> line index => why it waited the last time it was tried */
    public array $limitedBy = [];

    /** @var list<TimelinePoint> */
    public array $points = [];

    /** @var array<int, Money> */
    private array $provisional = [];

    /** @var array<int, Money> */
    private array $released = [];

    /** @var array<int, Money> */
    private array $incoming = [];

    /** @var array<int, CycleOutcome> */
    public array $outcomes = [];

    public int $incomeCount = 0;

    public Money $incomeAmount;

    public Money $incomeAllocated;

    public Money $incomeLeftFree;

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows  bucket id => current window
     */
    /** @var array<int, array{0: string, 1: string}> */
    private array $windowDays = [];

    public function __construct(EngineInput $input, private readonly array $windows, private readonly EngineCalendar $calendar)
    {
        foreach ($windows as $id => [$start, $end]) {
            $this->windowDays[$id] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        $this->cash = $input->startCash;
        $this->earmarked = Money::zero();
        $this->committed = Money::zero();
        $this->incomeAmount = Money::zero();
        $this->incomeAllocated = Money::zero();
        $this->incomeLeftFree = Money::zero();
        $this->lastPurchaseDay = $input->start->format('Y-m-d');
        $this->day = $this->lastPurchaseDay;

        foreach ($input->buckets as $bucket) {
            $this->buckets[$bucket->id] = $bucket;
            $this->env[$bucket->id] = $bucket->envelope;
            $this->funded[$bucket->id] = $bucket->isFunded;
            $this->fundedSince[$bucket->id] = $bucket->fundedSince;
            $this->provisional[$bucket->id] = Money::zero();
            $this->released[$bucket->id] = Money::zero();
            $this->incoming[$bucket->id] = Money::zero();

            if ($bucket->isFunded) {
                $this->earmarked = $this->earmarked->add($bucket->envelope);
            }

            $period = $bucket->cyclePeriod();
            if ($period !== null) {
                $this->received[$bucket->id] = [$period->startOf($input->start->toDateTimeImmutable())->format('Y-m-d') => $bucket->receivedThisCycle ?? Money::zero()];
            }

            if ($bucket->completedOn !== null) {
                $this->completed[$bucket->id] = $bucket->completedOn;
                $this->reached[$bucket->id] = $bucket->completedOn;
            }
        }
    }

    public function free(): Money
    {
        return $this->cash->subtract($this->earmarked);
    }

    public function isCompleted(int $bucketId, CarbonImmutable $date): bool
    {
        return isset($this->completed[$bucketId]) && $this->completed[$bucketId]->lte($date);
    }

    /**
     * Puts money in a bucket. A bucket that was never funded starts its envelope from zero that day: spending in
     * its category before then is not its concern.
     */
    public function fund(int $bucketId, Money $amount, CarbonImmutable $date): void
    {
        if (! $this->funded[$bucketId]) {
            $this->env[$bucketId] = Money::zero();
            $this->funded[$bucketId] = true;
            $tracking = $this->buckets[$bucketId]->trackingStartedOn;
            $this->fundedSince[$bucketId] = $tracking !== null && $tracking->gt($date) ? $tracking : $date;
        }

        $this->env[$bucketId] = $this->env[$bucketId]->add($amount);
        $this->earmarked = $this->earmarked->add($amount);
        $this->grew = true;
    }

    /**
     * Takes money out of a funded bucket: given away when its cycle closes (released), or spent on a purchase.
     */
    public function withdraw(int $bucketId, Money $amount, CarbonImmutable $date, bool $released = true): void
    {
        $this->env[$bucketId] = $this->env[$bucketId]->subtract($amount);
        $this->earmarked = $this->earmarked->subtract($amount);

        if ($released && $this->inWindow($bucketId, $date)) {
            $this->released[$bucketId] = $this->released[$bucketId]->add($amount);
        }
    }

    /**
     * A planned expense in a category: it comes out of every funded spending bucket that tracks the category (from
     * the day it was funded), like real spending does. A never-funded one only follows it as a limit.
     */
    public function spendCategory(int $categoryId, Money $amount, CarbonImmutable $date): void
    {
        foreach ($this->buckets as $id => $bucket) {
            if (! $bucket->tracksCategory($categoryId)) {
                continue;
            }

            if ($this->funded[$id]) {
                $since = $this->fundedSince[$id];
                if ($since === null || $since->lte($date)) {
                    $this->env[$id] = $this->env[$id]->subtract($amount);
                    $this->earmarked = $this->earmarked->subtract($amount);
                }
            } elseif ($bucket->trackingStartedOn === null || $bucket->trackingStartedOn->lte($date)) {
                $this->env[$id] = $this->env[$id]->subtract($amount);
            }
        }
    }

    public function noteReceived(int $bucketId, Money $amount, CarbonImmutable $date): void
    {
        if ($this->inWindow($bucketId, $date)) {
            $this->provisional[$bucketId] = $this->provisional[$bucketId]->add($amount);
        }

        $key = $this->calendar->cycleStart($this->buckets[$bucketId], $date);
        if ($key !== null) {
            $this->received[$bucketId][$key] = ($this->received[$bucketId][$key] ?? Money::zero())->add($amount);
        }
    }

    public function noteIncoming(int $bucketId, Money $amount, CarbonImmutable $date): void
    {
        if ($this->inWindow($bucketId, $date)) {
            $this->incoming[$bucketId] = $this->incoming[$bucketId]->add($amount);
        }
    }

    public function receivedOn(EngineBucket $bucket, CarbonImmutable $date): ?Money
    {
        $key = $this->calendar->cycleStart($bucket, $date);
        if ($key === null) {
            return null;
        }

        return $this->received[$bucket->id][$key] ?? Money::zero();
    }

    /**
     * The fill value on a date: the step-up value once the bucket it waits for has held its cap (or closed),
     * counted from the start of the current cycle; one that already applies when the forecast starts keeps applying.
     */
    public function valueOn(EngineBucket $bucket, CarbonImmutable $date): ?string
    {
        if ($bucket->stepUpValue !== null && $bucket->stepUpActive) {
            return $bucket->stepUpValue;
        }

        if ($bucket->stepUpValue === null || $bucket->stepUpAfterBucketId === null || ! isset($this->reached[$bucket->stepUpAfterBucketId])) {
            return $bucket->value;
        }

        $cycleStart = $this->calendar->cycleStart($bucket, $date);
        $cutoff = $cycleStart !== null ? CarbonImmutable::parse($cycleStart)->subDay() : $date;

        return $this->reached[$bucket->stepUpAfterBucketId]->lte($cutoff) ? $bucket->stepUpValue : $bucket->value;
    }

    /**
     * noteReached(), only when a bucket received money since the last check (nothing else can fill one).
     */
    public function noteReachedIfFunded(CarbonImmutable $date): void
    {
        if ($this->grew) {
            $this->noteReached($date);
        }
    }

    public function noteReached(CarbonImmutable $date): void
    {
        $this->grew = false;
        foreach ($this->buckets as $id => $bucket) {
            if (isset($this->reached[$id])) {
                continue;
            }

            $cap = $bucket->capOn($date);
            if ($cap !== null && $cap->isPositive() && ! $this->env[$id]->lessThan($cap)) {
                $this->reached[$id] = $date;
            }
        }
    }

    /**
     * Records how the bucket's current window ends (called the day after its last day, once its carry-over moved).
     */
    public function closeWindow(int $bucketId): void
    {
        [$start, $end] = $this->windows[$bucketId];
        $close = $this->env[$bucketId];

        $this->outcomes[$bucketId] = new CycleOutcome(
            $start,
            $end,
            $this->provisional[$bucketId],
            $close->add($this->released[$bucketId])->subtract($this->incoming[$bucketId]),
            $this->released[$bucketId],
            $this->incoming[$bucketId],
            $close,
        );
    }

    public function addPoint(CarbonImmutable $date): void
    {
        $this->points[] = new TimelinePoint($date, $this->cash, $this->earmarked, $this->free(), $this->env, $this->funded);
    }

    private function inWindow(int $bucketId, CarbonImmutable $date): bool
    {
        [$start, $end] = $this->windowDays[$bucketId];
        $day = $date->format('Y-m-d');

        return $day >= $start && $day <= $end;
    }
}
