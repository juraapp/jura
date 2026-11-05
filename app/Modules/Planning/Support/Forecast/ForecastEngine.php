<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Support\IncomeStep;
use App\Modules\Planning\Support\IncomeStepBucket;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use WeakMap;

/**
 * Deterministic simulation of on-budget money over time. Ready to assign (free money) is never stored: it is cash
 * minus what funded bucket envelopes hold. Planned incomes add cash and are split across buckets by the same
 * IncomeStep the real allocator uses (what no bucket claims stays free); planned expenses take cash and shrink every
 * funded spending bucket that tracks their category; planned transfers across the budget's edge move cash only.
 * Cycles close with their carry-over. The scenario's items are bought strictly in order at the earliest day they
 * can be paid: from their bucket first, the rest from free money, and only when the cash left after every later
 * planned flow (the lowest end-of-day balance from that day on) still covers them. A later dip of ready to assign
 * below zero only warns. Same-day order: flows (incomes, then transfers, then expenses), then cycle closes, then
 * purchases. Pure: no I/O.
 */
final class ForecastEngine
{
    /** @var WeakMap<EngineInput, array<string, mixed>> the days to visit and reusable work per input (see schedule()) */
    private WeakMap $schedules;

    public function __construct(private readonly IncomeStep $step = new IncomeStep)
    {
        $this->schedules = new WeakMap;
    }

    /**
     * @param  list<EngineLine>  $lines  the scenario, in purchase order
     */
    public function run(EngineInput $input, array $lines): EngineResult
    {
        $schedule = $this->schedules[$input] ??= $this->schedule($input);

        $dates = $schedule['dates'];
        $notBefore = [];
        foreach ($lines as $index => $line) {
            $notBefore[$index] = $line->notBefore?->format('Y-m-d');
            if ($line->notBefore !== null && $line->notBefore->gt($input->start) && $line->notBefore->lte($input->end)) {
                $dates[] = $notBefore[$index];
            }
        }
        if (count($dates) !== count($schedule['dates'])) {
            $dates = array_values(array_unique($dates));
            sort($dates);
        }

        $run = new EngineRun($input, $schedule, $dates, $lines, $notBefore, implode(',', $dates));

        for ($bought = count($lines); $bought >= 1; $bought--) {
            $snapshot = $schedule['memo']->snapshots[$run->daysKey][$run->prefixes[$bought]] ?? null;
            if ($snapshot !== null) {
                return $this->simulate($run, clone $snapshot[0], $snapshot[1]);
            }
        }

        $baseline = $schedule['memo']->baselines[$run->daysKey] ??= $this->baseline($run);
        $last = count($baseline) - 1;
        foreach ($baseline as $index => $preBuy) {
            $state = clone $preBuy;
            $this->buy($run, $state, $index);

            if ($state->bought !== [] || $index === $last) {
                return $this->simulate($run, $state, $index);
            }
        }

        return $this->finish($input, $lines, new EngineState($input, $schedule['windows'], $schedule['calendar']));
    }

    /**
     * Carries a run on from a day whose flows are already applied: buys what can be bought that day, closes it, then
     * simulates every later day.
     */
    private function simulate(EngineRun $run, EngineState $state, int $from): EngineResult
    {
        $count = count($run->dates);
        for ($index = $from; $index < $count; $index++) {
            $date = $run->moment($index);
            if ($index > $from) {
                $this->applyDay($run, $state, $index, $date);
            }

            $this->buy($run, $state, $index);
            $state->noteReachedIfFunded($date);
            $state->addPoint($date);
        }

        return $this->finish($run->input, $run->lines, $state);
    }

    /**
     * The state of every day after its flows and cycle closes, before any purchase, when nothing is ever bought.
     *
     * @return list<EngineState>
     */
    private function baseline(EngineRun $run): array
    {
        $state = new EngineState($run->input, $run->schedule['windows'], $run->schedule['calendar']);
        $state->noteReached($run->input->start);

        $days = [];
        foreach ($run->dates as $index => $key) {
            $date = $run->moment($index);
            $this->applyDay($run, $state, $index, $date);
            $days[] = clone $state;
            $state->addPoint($date);
        }

        return $days;
    }

    /**
     * A day's flows, then its cycle closes (D19), then the windows that end on it.
     */
    private function applyDay(EngineRun $run, EngineState $state, int $index, CarbonImmutable $date): void
    {
        $key = $run->dates[$index];
        $day = $run->schedule['days'][$key] ?? null;
        $state->day = $key;

        foreach ($day['flows'] ?? [] as $flow) {
            $this->applyFlow($run->input, $state, $flow, $date);
        }

        foreach ($day['cycles'] ?? [] as $bucketId) {
            $this->moveLeftover($state, $state->buckets[$bucketId], $date);
        }

        foreach ($day['windows'] ?? [] as $bucketId) {
            $state->closeWindow($bucketId);
        }

        $state->noteReachedIfFunded($date);
    }

    /**
     * The days the simulation visits, independent of the lines: the start, every flow, every cycle end of a bucket
     * that does not carry its money over, the end of each bucket's current window and every month end.
     *
     * @return array{dates: list<string>, moments: array<string, CarbonImmutable>, days: array<string, array{flows?: list<EngineFlow>, cycles?: list<int>, windows?: list<int>}>, windows: array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>, calendar: EngineCalendar, memo: EngineMemo}
     */
    private function schedule(EngineInput $input): array
    {
        $start = $input->start->startOfDay();
        $end = $input->end->startOfDay();
        $days = [];
        $dates = [$start->format('Y-m-d')];

        $flows = $input->flows;
        $rank = [FlowKind::Income->value => 0, FlowKind::Boundary->value => 1, FlowKind::Expense->value => 2];
        usort($flows, fn (EngineFlow $a, EngineFlow $b) => [$a->date->format('Y-m-d'), $rank[$a->kind->value], $a->seq] <=> [$b->date->format('Y-m-d'), $rank[$b->kind->value], $b->seq]);
        foreach ($flows as $flow) {
            $date = $flow->date->lt($start) ? $start : $flow->date->startOfDay();
            if ($date->gt($end)) {
                continue;
            }
            $days[$date->format('Y-m-d')]['flows'][] = $flow;
        }

        $windows = [];
        foreach ($input->buckets as $bucket) {
            $period = $bucket->windowPeriod();
            $windowStart = CarbonImmutable::instance($period->startOf($start->toDateTimeImmutable()));
            $windowEnd = CarbonImmutable::instance($period->endOf($start->toDateTimeImmutable()));
            $windows[$bucket->id] = [$windowStart, $windowEnd];
            if ($windowEnd->lte($end)) {
                $days[$windowEnd->format('Y-m-d')]['windows'][] = $bucket->id;
            }

            $cycle = $bucket->cyclePeriod();
            if ($cycle === null || $bucket->carryOver === CarryOverPolicy::Carry) {
                continue;
            }

            $cursor = CarbonImmutable::instance($cycle->endOf($start->toDateTimeImmutable()));
            while ($cursor->lte($end)) {
                $days[$cursor->format('Y-m-d')]['cycles'][] = $bucket->id;
                $cursor = CarbonImmutable::instance($cycle->endOf($cursor->addDay()->toDateTimeImmutable()));
            }
        }

        for ($month = $start->endOfMonth()->startOfDay(); $month->lte($end); $month = $month->addDay()->endOfMonth()->startOfDay()) {
            $dates[] = $month->format('Y-m-d');
        }
        $dates[] = $end->format('Y-m-d');

        $dates = array_values(array_unique([...$dates, ...array_map('strval', array_keys($days))]));
        sort($dates);

        $moments = [];
        foreach ($dates as $key) {
            $moments[$key] = CarbonImmutable::parse($key);
        }

        return ['dates' => $dates, 'moments' => $moments, 'days' => $days, 'windows' => $windows, 'calendar' => new EngineCalendar, 'memo' => new EngineMemo];
    }

    private function applyFlow(EngineInput $input, EngineState $state, EngineFlow $flow, CarbonImmutable $date): void
    {
        if ($flow->kind === FlowKind::Boundary) {
            $state->cash = $state->cash->add($flow->amount);

            return;
        }

        if ($flow->kind === FlowKind::Expense) {
            $state->cash = $state->cash->subtract($flow->amount);
            if ($flow->categoryId !== null) {
                $state->spendCategory($flow->categoryId, $flow->amount, $date);
            }

            return;
        }

        $state->cash = $state->cash->add($flow->amount);

        $inputs = [];
        foreach ($input->buckets as $bucket) {
            $inputs[] = new IncomeStepBucket(
                $bucket->id,
                $bucket->rule,
                $state->valueOn($bucket, $date),
                $bucket->capOn($date),
                $bucket->roomApplies(),
                $state->env[$bucket->id],
                $state->receivedOn($bucket, $date),
                $bucket->acceptsAccount($flow->accountId) && ! $state->isCompleted($bucket->id, $date),
            );
        }

        $split = $this->step->split($flow->amount, $inputs, $flow->overrides);
        $allocated = Money::zero();
        foreach ($split['allocations'] as $bucketId => $amount) {
            $state->fund($bucketId, $amount, $date);
            $state->noteReceived($bucketId, $amount, $date);
            $allocated = $allocated->add($amount);
        }

        $state->incomeCount++;
        $state->incomeAmount = $state->incomeAmount->add($flow->amount);
        $state->incomeAllocated = $state->incomeAllocated->add($allocated);
        $state->incomeLeftFree = $state->incomeLeftFree->add($flow->amount->subtract($allocated));
    }

    /**
     * Gives away what a bucket holds when its cycle closes (or when it completes): to its overflow bucket, or back
     * to ready to assign. A bucket set to overflow into itself keeps it.
     */
    private function moveLeftover(EngineState $state, EngineBucket $bucket, CarbonImmutable $date): void
    {
        if (! $state->funded[$bucket->id]) {
            return;
        }

        $leftover = $state->env[$bucket->id];
        if (! $leftover->isPositive()) {
            return;
        }

        $target = $bucket->carryOver === CarryOverPolicy::Overflow ? $bucket->overflowBucketId : null;
        if ($target === $bucket->id) {
            return;
        }

        $state->withdraw($bucket->id, $leftover, $date);
        if ($target !== null && isset($state->buckets[$target])) {
            $state->fund($target, $leftover, $date);
            $state->noteIncoming($target, $leftover, $date);
        }
    }

    /**
     * Buys as many scenario items as can be paid on this day, in order, remembering the state after each purchase.
     */
    private function buy(EngineRun $run, EngineState $state, int $index): void
    {
        $lines = $run->lines;
        $key = $run->dates[$index];
        $date = $run->moment($index);
        $input = $run->input;
        $count = count($lines);
        while ($state->next < $count) {
            $line = $lines[$state->next];

            if ($line->blocked !== null) {
                $state->next++;

                continue;
            }

            if ($key < $state->lastPurchaseDay || ($run->notBefore[$state->next] !== null && $key < $run->notBefore[$state->next])) {
                return;
            }

            $bucketId = $this->payingBucket($state, $line, $date);
            $fromBucket = $bucketId === null ? Money::zero() : $this->smaller($state->env[$bucketId], $line->price);
            $fromFree = $line->price->subtract($fromBucket);

            if ($fromFree->greaterThan($state->free())) {
                $state->limitedBy[$state->next] = 'free';

                return;
            }

            if ($line->price->greaterThan($input->timeline->suffixMin($date)->subtract($state->committed))) {
                $state->limitedBy[$state->next] = 'cash';

                return;
            }

            if ($bucketId !== null && $fromBucket->isPositive()) {
                $state->withdraw($bucketId, $fromBucket, $date, released: false);
            }
            $state->cash = $state->cash->subtract($line->price);
            $state->committed = $state->committed->add($line->price);
            if ($bucketId !== null) {
                $this->completeIfDone($state, $bucketId, $date);
            }
            $state->lastPurchaseDay = $key;
            $state->bought[$state->next] = [$date, $fromBucket, $fromFree];
            $state->next++;
            $run->remember($state, $index);
        }
    }

    /**
     * The bucket that pays first: the line's, when it is funded, holds money and is not closed.
     */
    private function payingBucket(EngineState $state, EngineLine $line, CarbonImmutable $date): ?int
    {
        $id = $line->bucketId;

        if ($id === null || ! isset($state->buckets[$id]) || ! $state->funded[$id] || $state->isCompleted($id, $date) || ! $state->env[$id]->isPositive()) {
            return null;
        }

        return $id;
    }

    /**
     * A bucket marked "completes on purchase" closes when something is bought from it: what is left moves to its
     * overflow bucket (or back to ready to assign) and it stops receiving income.
     */
    private function completeIfDone(EngineState $state, int $bucketId, CarbonImmutable $date): void
    {
        $bucket = $state->buckets[$bucketId];
        if (! $bucket->completesOnPurchase || isset($state->completed[$bucketId])) {
            return;
        }

        $state->completed[$bucketId] = $date;
        $state->reached[$bucketId] ??= $date;
        $this->moveLeftover($state, $bucket, $date);
    }

    /**
     * @param  list<EngineLine>  $lines
     */
    private function finish(EngineInput $input, array $lines, EngineState $state): EngineResult
    {
        $dips = [];
        foreach ($state->points as $point) {
            if ($point->free->isNegative()) {
                $dips[] = $point->date;
            }
        }

        $free = $state->free();
        $cashRoom = $input->timeline->suffixMin($input->end)->subtract($state->committed);
        $spendable = $this->smaller($free, $cashRoom);

        $results = [];
        foreach ($lines as $index => $line) {
            if (isset($state->bought[$index])) {
                [$date, $fromBucket, $fromFree] = $state->bought[$index];
                $results[] = new LineResult(
                    $line->itemId, $line->name, $line->price, $date, (int) round($input->start->diffInDays($date)), Money::zero(), null,
                    $line->desiredBy !== null && $date->gt($line->desiredBy), $line->importance,
                    $fromBucket, $fromFree, null, false, $this->firstOnOrAfter($dips, $date), $line->warnings,
                );

                continue;
            }

            if ($line->blocked !== null) {
                $results[] = new LineResult(
                    $line->itemId, $line->name, $line->price, null, null, $line->price, $line->blocked, false, $line->importance,
                    warnings: $line->warnings,
                );

                continue;
            }

            $bucketId = $this->payingBucket($state, $line, $input->end);
            $inBucket = $bucketId === null ? Money::zero() : $this->smaller($state->env[$bucketId], $line->price);
            $missing = $line->price->subtract($inBucket)->subtract($spendable->isPositive() ? $spendable : Money::zero());
            $limitedBy = $state->limitedBy[$index] ?? match (true) {
                $line->price->subtract($inBucket)->greaterThan($free) => 'free',
                $line->price->greaterThan($cashRoom) => 'cash',
                default => null,
            };

            $results[] = new LineResult(
                $line->itemId, $line->name, $line->price, null, null, $missing->isPositive() ? $missing : Money::zero(), null, false, $line->importance,
                limitedBy: $limitedBy, warnings: $line->warnings,
            );
        }

        return new EngineResult(
            $results,
            $state->env,
            $state->cash,
            $free,
            $state->points,
            $state->outcomes,
            $state->reached,
            new IncomeSummary($state->incomeCount, $state->incomeAmount, $state->incomeAllocated, $state->incomeLeftFree),
        );
    }

    /**
     * The first day on or after a purchase when ready to assign is below zero.
     *
     * @param  list<CarbonImmutable>  $dips  days ready to assign is below zero, ascending
     */
    private function firstOnOrAfter(array $dips, CarbonImmutable $date): ?CarbonImmutable
    {
        foreach ($dips as $dip) {
            if ($dip->gte($date)) {
                return $dip;
            }
        }

        return null;
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
