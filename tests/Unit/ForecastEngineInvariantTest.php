<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineFlow;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineLine;
use App\Modules\Planning\Support\Forecast\EngineResult;
use App\Modules\Planning\Support\Forecast\FlowKind;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Property test: 300 random but seeded (so reproducible) inputs, each checked against invariants recounted here
 * from the input alone, never from the engine's own running totals.
 */
class ForecastEngineInvariantTest extends TestCase
{
    private const SEED = 20261110;

    private const CASES = 300;

    private const START = '2026-01-01';

    public function test_random_scenarios_keep_the_money_invariants(): void
    {
        mt_srand(self::SEED);

        for ($case = 0; $case < self::CASES; $case++) {
            [$input, $lines] = $this->randomCase();
            $result = (new ForecastEngine)->run($input, $lines);
            $label = "case {$case} (seed ".self::SEED.')';

            $this->assertMoneyIdentity($input, $result, $label);
            $this->assertCashNeverShortAfterAPurchase($result, $label);
            $this->assertPurchasesAreSound($input, $lines, $result, $label);
            $this->assertDeterministic($input, $lines, $result, $label);
            $this->assertExtraIncomeNeverDelaysTheFirstItem($input, $lines, $result, $label);
            $this->assertReusedWorkChangesNothing($input, $lines, $label);
        }
    }

    /**
     * (a) At every point cash is the start cash plus every flow so far minus every purchase so far, and ready to
     * assign plus the funded envelopes is that cash.
     */
    private function assertMoneyIdentity(EngineInput $input, EngineResult $result, string $label): void
    {
        foreach ($result->timeline as $point) {
            $key = $point->date->format('Y-m-d');
            $cash = $input->startCash;
            foreach ($input->flows as $flow) {
                if ($flow->date->format('Y-m-d') <= $key) {
                    $cash = $flow->kind === FlowKind::Expense ? $cash->subtract($flow->amount) : $cash->add($flow->amount);
                }
            }
            foreach ($result->lines as $line) {
                if ($line->purchaseDate !== null && $line->purchaseDate->format('Y-m-d') <= $key) {
                    $cash = $cash->subtract($line->price);
                }
            }

            $envelopes = Money::zero();
            foreach ($point->envelopes() as $envelope) {
                $envelopes = $envelopes->add($envelope);
            }

            $this->assertSame($cash->toDecimalString(), $point->cash->toDecimalString(), "{$label}: cash on {$key}");
            $this->assertSame($cash->toDecimalString(), $point->free->add($envelopes)->toDecimalString(), "{$label}: free + envelopes on {$key}");
        }
    }

    /**
     * (b) From the first purchase on, the accounts never go below zero.
     */
    private function assertCashNeverShortAfterAPurchase(EngineResult $result, string $label): void
    {
        $dates = array_filter(array_map(fn ($line) => $line->purchaseDate?->format('Y-m-d'), $result->lines));
        if ($dates === []) {
            return;
        }

        $first = min($dates);
        foreach ($result->timeline as $point) {
            if ($point->date->format('Y-m-d') >= $first) {
                $this->assertFalse($point->cash->isNegative(), "{$label}: cash below zero on {$point->date->toDateString()}");
            }
        }
    }

    /**
     * (c) a purchase is split into what its bucket pays and what ready to assign pays, and leaves neither negative that
     * day; (d) items are bought in order, and nothing after an unbought item is bought; (e) never before the start or
     * an item's not-before date.
     *
     * @param  list<EngineLine>  $lines
     */
    private function assertPurchasesAreSound(EngineInput $input, array $lines, EngineResult $result, string $label): void
    {
        $previous = null;
        $stopped = false;

        foreach ($result->lines as $index => $line) {
            $source = $lines[$index];
            if ($source->blocked !== null) {
                $this->assertNull($line->purchaseDate, "{$label}: blocked line {$index} bought");

                continue;
            }

            if ($line->purchaseDate === null) {
                $stopped = true;

                continue;
            }

            $this->assertFalse($stopped, "{$label}: line {$index} bought after an unbought one");
            $date = $line->purchaseDate->format('Y-m-d');
            $this->assertGreaterThanOrEqual($input->start->format('Y-m-d'), $date, "{$label}: line {$index} before the start");
            $this->assertLessThanOrEqual($input->end->format('Y-m-d'), $date, "{$label}: line {$index} after the end");
            if ($source->notBefore !== null) {
                $this->assertGreaterThanOrEqual($source->notBefore->format('Y-m-d'), $date, "{$label}: line {$index} before not-before");
            }
            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $date, "{$label}: line {$index} out of order");
            }
            $previous = $date;

            $this->assertSame($line->price->toDecimalString(), $line->fromBucket->add($line->fromFree)->toDecimalString(), "{$label}: line {$index} split");
            $this->assertFalse($line->fromBucket->isNegative() || $line->fromFree->isNegative(), "{$label}: line {$index} negative part");
            if ($source->bucketId === null) {
                $this->assertTrue($line->fromBucket->isZero(), "{$label}: line {$index} paid from a bucket it does not have");
            }

            $point = $result->pointAt($line->purchaseDate);
            $this->assertFalse($point->free->isNegative(), "{$label}: ready to assign below zero on purchase day {$date}");
        }
    }

    /**
     * (f) the same input gives the same result twice.
     *
     * @param  list<EngineLine>  $lines
     */
    private function assertDeterministic(EngineInput $input, array $lines, EngineResult $result, string $label): void
    {
        $fresh = (new ForecastEngine)->run($input, $lines);

        $this->assertSame($this->fingerprint($result), $this->fingerprint($fresh), "{$label}: not deterministic");
    }

    /**
     * (g) one more income can only bring the first item forward (or keep its date).
     *
     * @param  list<EngineLine>  $lines
     */
    private function assertExtraIncomeNeverDelaysTheFirstItem(EngineInput $input, array $lines, EngineResult $result, string $label): void
    {
        $first = null;
        foreach ($lines as $index => $line) {
            if ($line->blocked === null) {
                $first = $index;
                break;
            }
        }
        if ($first === null || $result->lines[$first]->purchaseDate === null) {
            return;
        }

        $extra = new EngineFlow($this->randomDate($input), 999, FlowKind::Income, Money::of(mt_rand(1, 3000)), mt_rand(0, 1) ? null : mt_rand(1, 2));
        $richer = new EngineInput($input->start, $input->end, $input->startCash, $input->buckets, [...$input->flows, $extra]);
        $date = (new ForecastEngine)->run($richer, $lines)->lines[$first]->purchaseDate;

        $this->assertNotNull($date, "{$label}: extra income lost the first item");
        $this->assertLessThanOrEqual($result->lines[$first]->purchaseDate->format('Y-m-d'), $date->format('Y-m-d'), "{$label}: extra income delayed the first item");
    }

    /**
     * (h) an engine that already ran other orders of the same input (and reuses that work) gives exactly what a fresh
     * engine gives, for every rotation and the reverse of the order.
     *
     * @param  list<EngineLine>  $lines
     */
    private function assertReusedWorkChangesNothing(EngineInput $input, array $lines, string $label): void
    {
        $orders = [array_reverse($lines)];
        for ($shift = 0; $shift < count($lines); $shift++) {
            $orders[] = [...array_slice($lines, $shift), ...array_slice($lines, 0, $shift)];
        }

        $shared = new ForecastEngine;
        foreach ([...$orders, ...$orders] as $order) {
            $this->assertSame(
                $this->fingerprint((new ForecastEngine)->run($input, $order)),
                $this->fingerprint($shared->run($input, $order)),
                "{$label}: reused work changed a result",
            );
        }
    }

    private function fingerprint(EngineResult $result): string
    {
        return json_encode([
            array_map(fn ($line) => [$line->purchaseDate?->format('Y-m-d'), (string) $line->fromBucket, (string) $line->fromFree, (string) $line->shortfall, $line->limitedBy, $line->freeDipsOn?->format('Y-m-d')], $result->lines),
            array_map(fn ($point) => [$point->date->format('Y-m-d'), (string) $point->cash, (string) $point->free], $result->timeline),
            array_map('strval', $result->endBalances),
        ]);
    }

    /**
     * @return array{0: EngineInput, 1: list<EngineLine>}
     */
    private function randomCase(): array
    {
        $start = CarbonImmutable::parse(self::START);
        $end = $start->addMonths(mt_rand(2, 12));
        $input = new EngineInput($start, $end, Money::of(0), []);

        $buckets = [];
        $count = mt_rand(0, 4);
        for ($id = 1; $id <= $count; $id++) {
            $savings = mt_rand(0, 1) === 1;
            $funded = mt_rand(0, 3) > 0;
            $categoryId = ! $savings && mt_rand(0, 1) ? mt_rand(1, 2) : null;
            $cycle = mt_rand(0, 1) ? BudgetPeriodType::Monthly : null;
            $rule = [null, FillRule::Fixed, FillRule::Percent, FillRule::PercentOfRemaining, FillRule::FillToTarget][mt_rand(0, 4)];
            $carry = $cycle === null ? CarryOverPolicy::Carry : [CarryOverPolicy::Carry, CarryOverPolicy::Release, CarryOverPolicy::Overflow][mt_rand(0, 2)];

            $buckets[] = new EngineBucket(
                id: $id,
                rule: $rule,
                value: match ($rule) {
                    FillRule::Fixed => (string) mt_rand(50, 800),
                    FillRule::Percent, FillRule::PercentOfRemaining => (string) mt_rand(5, 100),
                    default => null,
                },
                cap: mt_rand(0, 1) ? Money::of(mt_rand(200, 3000)) : null,
                isSavings: $savings,
                envelope: Money::of($funded ? mt_rand(-200, 1500) : -mt_rand(0, 300)),
                allowedAccountIds: mt_rand(0, 3) === 0 ? [mt_rand(1, 2)] : null,
                cycle: $cycle,
                carryOver: $carry,
                overflowBucketId: $carry === CarryOverPolicy::Overflow ? mt_rand(1, $count) : null,
                receivedThisCycle: $cycle !== null ? Money::of(mt_rand(0, 300)) : null,
                completesOnPurchase: mt_rand(0, 3) === 0,
                isFunded: $funded,
                fundedSince: $funded ? $start->subDays(mt_rand(0, 40)) : null,
                categoryId: $categoryId,
                trackingStartedOn: $start->subMonth(),
            );
        }

        $flows = [];
        $flowCount = mt_rand(0, 15);
        for ($seq = 0; $seq < $flowCount; $seq++) {
            $date = $this->randomDate($input);
            $kind = [FlowKind::Income, FlowKind::Income, FlowKind::Expense, FlowKind::Expense, FlowKind::Boundary][mt_rand(0, 4)];
            $overrides = [];
            if ($kind === FlowKind::Income && $count > 0 && mt_rand(0, 4) === 0) {
                $overrides[mt_rand(1, $count)] = Money::of(mt_rand(10, 500));
            }

            $flows[] = new EngineFlow(
                $date,
                $seq,
                $kind,
                $kind === FlowKind::Boundary ? Money::of(mt_rand(-800, 800)) : Money::of(mt_rand(10, 2500)),
                mt_rand(0, 1) ? null : mt_rand(1, 2),
                $kind === FlowKind::Expense && mt_rand(0, 1) ? mt_rand(1, 2) : null,
                $overrides,
            );
        }

        $lines = [];
        $lineCount = mt_rand(0, 4);
        for ($id = 1; $id <= $lineCount; $id++) {
            $lines[] = new EngineLine(
                $id,
                "Item {$id}",
                Money::of(mt_rand(50, 3000)),
                $count > 0 && mt_rand(0, 1) ? mt_rand(1, $count) : null,
                mt_rand(0, 3) === 0 ? $this->randomDate($input) : null,
                null,
                mt_rand(1, 5),
                mt_rand(0, 9) === 0 ? 'accounts' : null,
            );
        }

        return [new EngineInput($start, $end, Money::of(mt_rand(0, 4000)), $buckets, $flows), $lines];
    }

    private function randomDate(EngineInput $input): CarbonImmutable
    {
        return $input->start->addDays(mt_rand(0, (int) $input->start->diffInDays($input->end)));
    }
}
