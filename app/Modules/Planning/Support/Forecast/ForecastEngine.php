<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\CyclePeriod;
use App\Modules\Planning\Support\IncomeStep;
use App\Modules\Planning\Support\IncomeStepBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Deterministic simulation of bucket funds over time. Replays future incomes
 * through the priority waterfall (what no bucket claims stays ready to assign),
 * applies end-of-cycle carry-over, and buys the scenario's items strictly in
 * order at the earliest date they can be paid: an item without a bucket from
 * ready to assign, one with a bucket from the bucket first and the rest from
 * ready to assign. Pure: no I/O.
 */
final class ForecastEngine
{
    /** @var array<int, array<string, Money>> income received per bucket and cycle start */
    private array $received = [];

    /** @var array<int, CarbonImmutable> when each bucket was closed */
    private array $completed = [];

    /** @var array<int, CarbonImmutable> when each bucket first held its target (or was closed) */
    private array $reached = [];

    /** Money in the accounts no bucket holds, as the simulation goes. */
    private Money $ready;

    public function __construct(private readonly IncomeStep $step = new IncomeStep) {}

    /**
     * @param  list<EngineLine>  $lines  the scenario, in purchase order
     */
    public function run(EngineInput $input, array $lines): EngineResult
    {
        $buckets = [];
        $balances = [];
        $this->received = [];
        $this->completed = [];
        $this->reached = [];
        $this->ready = $input->readyToAssign ?? Money::zero();
        foreach ($input->buckets as $bucket) {
            $period = $bucket->cyclePeriod();
            if ($period !== null) {
                $this->received[$bucket->id] = [$period->startOf($input->start->toDateTimeImmutable())->format('Y-m-d') => $bucket->receivedThisCycle ?? Money::zero()];
            }

            $buckets[$bucket->id] = $bucket;
            $balances[$bucket->id] = $bucket->envelope;
            if ($bucket->completedOn !== null) {
                $this->completed[$bucket->id] = $bucket->completedOn;
            }
        }

        $state = new class
        {
            public int $next = 0;

            public CarbonImmutable $lastPurchase;

            /** @var array<int, LineResult> */
            public array $results = [];
        };
        $state->lastPurchase = $input->start;

        $this->noteReached($input, $balances, $input->start);
        $this->buy($input, $lines, $balances, $state, $input->start);

        foreach ($this->events($input, $lines) as $event) {
            if ($event['type'] === 'income') {
                $this->applyIncome($input, $buckets, $balances, $event['income']);
            } elseif ($event['type'] === 'cycle') {
                $this->closeCycle($input, $buckets[$event['bucketId']], $balances);
            }

            $this->noteReached($input, $balances, $event['date']);
            $this->buy($input, $lines, $balances, $state, $event['date']);
            $this->noteReached($input, $balances, $event['date']);
        }

        while ($state->next < count($lines)) {
            $this->recordUnbought($input, $lines[$state->next], $balances, $state);
            $state->next++;
        }

        return new EngineResult(array_values($state->results), $balances, $this->ready);
    }

    /**
     * @param  list<EngineLine>  $lines
     * @return list<array{date: CarbonImmutable, order: int, type: string, income?: EngineIncome, bucketId?: int}>
     */
    private function events(EngineInput $input, array $lines): array
    {
        $events = [];

        foreach ($input->incomes as $income) {
            $date = $income->date->lt($input->start) ? $input->start : $income->date;
            if ($date->lte($input->end)) {
                $events[] = ['date' => $date, 'order' => 0, 'type' => 'income', 'income' => $income];
            }
        }

        foreach ($input->buckets as $bucket) {
            if ($bucket->cycle === null || $bucket->carryOver === CarryOverPolicy::Carry) {
                continue;
            }

            $period = new CyclePeriod($bucket->cycle, $bucket->cycleEvery, $bucket->cycleAnchor?->toDateTimeImmutable());
            $cursor = CarbonImmutable::instance($period->endOf($input->start->toDateTimeImmutable()))->startOfDay();
            while ($cursor->lte($input->end)) {
                $events[] = ['date' => $cursor, 'order' => 1, 'type' => 'cycle', 'bucketId' => $bucket->id];
                $cursor = CarbonImmutable::instance($period->endOf($cursor->addDay()->toDateTimeImmutable()))->startOfDay();
            }
        }

        foreach ($lines as $line) {
            if ($line->notBefore !== null && $line->notBefore->gt($input->start) && $line->notBefore->lte($input->end)) {
                $events[] = ['date' => $line->notBefore, 'order' => 2, 'type' => 'check'];
            }
        }

        usort($events, fn (array $a, array $b) => [$a['date']->getTimestamp(), $a['order']] <=> [$b['date']->getTimestamp(), $b['order']]);

        return $events;
    }

    /**
     * @param  array<int, EngineBucket>  $buckets
     * @param  array<int, Money>  $balances
     */
    private function applyIncome(EngineInput $input, array $buckets, array &$balances, EngineIncome $income): void
    {
        $inputs = [];
        foreach ($input->buckets as $bucket) {
            $inputs[] = new IncomeStepBucket(
                $bucket->id,
                $bucket->rule,
                $this->valueOn($bucket, $income->date),
                $bucket->cap,
                $bucket->rule === FillRule::FillToTarget || $bucket->isSavings,
                $balances[$bucket->id],
                $this->receivedOn($bucket, $income->date),
                $bucket->acceptsAccount($income->accountId) && ! $this->isCompleted($bucket->id, $income->date),
            );
        }

        $result = $this->step->split($income->amount, $inputs);

        foreach ($result['allocations'] as $bucketId => $amount) {
            $balances[$bucketId] = $balances[$bucketId]->add($amount);

            if (isset($this->received[$bucketId])) {
                $key = $buckets[$bucketId]->cyclePeriod()->startOf($income->date->toDateTimeImmutable())->format('Y-m-d');
                $this->received[$bucketId][$key] = ($this->received[$bucketId][$key] ?? Money::zero())->add($amount);
            }
        }

        $this->ready = $this->ready->add($result['leftover']);
    }

    private function isCompleted(int $bucketId, CarbonImmutable $date): bool
    {
        return isset($this->completed[$bucketId]) && $this->completed[$bucketId]->lte($date);
    }

    /**
     * The fill value on a date: the step-up value once the bucket it waits for has held its target,
     * counted from the start of the current cycle.
     */
    private function valueOn(EngineBucket $bucket, CarbonImmutable $date): ?string
    {
        if ($bucket->stepUpValue === null || $bucket->stepUpAfterBucketId === null || ! isset($this->reached[$bucket->stepUpAfterBucketId])) {
            return $bucket->value;
        }

        $period = $bucket->cyclePeriod();
        $cutoff = $period !== null ? CarbonImmutable::instance($period->startOf($date->toDateTimeImmutable()))->subDay() : $date;

        return $this->reached[$bucket->stepUpAfterBucketId]->lte($cutoff) ? $bucket->stepUpValue : $bucket->value;
    }

    /**
     * @param  array<int, Money>  $balances
     */
    private function noteReached(EngineInput $input, array $balances, CarbonImmutable $date): void
    {
        foreach ($input->buckets as $bucket) {
            if (! isset($this->reached[$bucket->id]) && $bucket->cap !== null && ! $balances[$bucket->id]->lessThan($bucket->cap)) {
                $this->reached[$bucket->id] = $date;
            }
        }
    }

    /**
     * A bucket marked "completes on purchase" closes when something is bought from it: what is left moves
     * to its overflow bucket (or back to ready to assign) and it stops receiving income.
     *
     * @param  array<int, Money>  $balances
     */
    private function completeIfDone(EngineInput $input, int $bucketId, array &$balances, CarbonImmutable $date): void
    {
        foreach ($input->buckets as $bucket) {
            if ($bucket->id !== $bucketId || ! $bucket->completesOnPurchase || isset($this->completed[$bucketId])) {
                continue;
            }

            $this->completed[$bucketId] = $date;
            $this->reached[$bucketId] ??= $date;
            $this->closeCycle($input, $bucket, $balances);
        }
    }

    private function receivedOn(EngineBucket $bucket, CarbonImmutable $date): ?Money
    {
        if (! isset($this->received[$bucket->id])) {
            return null;
        }

        $key = $bucket->cyclePeriod()->startOf($date->toDateTimeImmutable())->format('Y-m-d');

        return $this->received[$bucket->id][$key] ?? Money::zero();
    }

    /**
     * @param  array<int, Money>  $balances
     */
    private function closeCycle(EngineInput $input, EngineBucket $bucket, array &$balances): void
    {
        $leftover = $balances[$bucket->id];
        if (! $leftover->isPositive()) {
            return;
        }

        $target = $bucket->carryOver === CarryOverPolicy::Overflow ? $bucket->overflowBucketId : null;

        if ($target === $bucket->id || ($target !== null && ! isset($balances[$target]))) {
            return;
        }

        $balances[$bucket->id] = $balances[$bucket->id]->subtract($leftover);

        if ($target === null) {
            $this->ready = $this->ready->add($leftover);
        } else {
            $balances[$target] = $balances[$target]->add($leftover);
        }
    }

    /**
     * Buys as many scenario items as are due on this date, in order.
     *
     * @param  list<EngineLine>  $lines
     * @param  array<int, Money>  $balances
     */
    private function buy(EngineInput $input, array $lines, array &$balances, object $state, CarbonImmutable $date): void
    {
        while ($state->next < count($lines)) {
            $line = $lines[$state->next];

            if ($line->blocked !== null) {
                $this->recordUnbought($input, $line, $balances, $state);
                $state->next++;

                continue;
            }

            $earliest = $state->lastPurchase;
            if ($line->notBefore !== null && $line->notBefore->gt($earliest)) {
                $earliest = $line->notBefore;
            }

            if ($date->lt($earliest)) {
                return;
            }

            $bucketId = $line->bucketId !== null && isset($balances[$line->bucketId]) ? $line->bucketId : null;
            $fromBucket = $this->fromBucket($line, $bucketId, $balances);
            $fromReady = $line->price->subtract($fromBucket);

            if ($fromReady->isPositive() && $this->spendableReady()->lessThan($fromReady)) {
                return;
            }

            if ($bucketId !== null) {
                $balances[$bucketId] = $balances[$bucketId]->subtract($fromBucket);
            }
            $this->ready = $this->ready->subtract($fromReady);
            if ($bucketId !== null) {
                $this->completeIfDone($input, $bucketId, $balances, $date);
            }
            $state->lastPurchase = $date;
            $state->results[$state->next] = new LineResult(
                $line->itemId, $line->name, $line->price, $date, (int) round($input->start->diffInDays($date)), Money::zero(), null,
                $line->desiredBy !== null && $date->gt($line->desiredBy), $line->importance,
            );
            $state->next++;
        }
    }

    /**
     * @param  array<int, Money>  $balances
     */
    private function recordUnbought(EngineInput $input, EngineLine $line, array $balances, object $state): void
    {
        $bucketId = $line->bucketId !== null && isset($balances[$line->bucketId]) ? $line->bucketId : null;
        $missing = $line->price->subtract($this->fromBucket($line, $bucketId, $balances))->subtract($this->spendableReady());

        $state->results[$state->next] = new LineResult(
            $line->itemId, $line->name, $line->price, null, null,
            $line->blocked !== null || $missing->isPositive() ? ($line->blocked !== null ? $line->price : $missing) : Money::zero(),
            $line->blocked, false, $line->importance,
        );
    }

    /**
     * What the bucket pays toward a line: its positive funds, up to the price.
     *
     * @param  array<int, Money>  $balances
     */
    private function fromBucket(EngineLine $line, ?int $bucketId, array $balances): Money
    {
        if ($bucketId === null || ! $balances[$bucketId]->isPositive()) {
            return Money::zero();
        }

        return $balances[$bucketId]->lessThan($line->price) ? $balances[$bucketId] : $line->price;
    }

    private function spendableReady(): Money
    {
        return $this->ready->isPositive() ? $this->ready : Money::zero();
    }
}
