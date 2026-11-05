<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * On-budget cash over time from planned flows alone (no purchases): the end-of-day balance on every date a flow
 * moves money. Answers "how much is there on a day" and "the lowest it gets from a day on", which is what a
 * purchase must leave room for so no later planned expense finds the accounts short. Pure: no I/O.
 */
final readonly class CashTimeline
{
    /**
     * @param  list<string>  $dates  Y-m-d, ascending, unique
     * @param  list<Money>  $balances  end-of-day balance on each date
     * @param  list<Money>  $suffixMins  the lowest balance on that date or any later one
     */
    private function __construct(
        public CarbonImmutable $start,
        public Money $startCash,
        private array $dates,
        private array $balances,
        private array $suffixMins,
    ) {}

    /**
     * @param  iterable<EngineFlow>  $flows  flows before the start count on the start date
     */
    public static function build(CarbonImmutable $start, Money $startCash, iterable $flows): self
    {
        $start = $start->startOfDay();
        $byDate = [];
        foreach ($flows as $flow) {
            $key = $flow->date->lt($start) ? $start->format('Y-m-d') : $flow->date->format('Y-m-d');
            $byDate[$key] = ($byDate[$key] ?? Money::zero())->add($flow->cashEffect());
        }
        ksort($byDate);

        $dates = [];
        $balances = [];
        $running = $startCash;
        foreach ($byDate as $date => $change) {
            $running = $running->add($change);
            $dates[] = (string) $date;
            $balances[] = $running;
        }

        $suffixMins = [];
        $lowest = null;
        for ($i = count($balances) - 1; $i >= 0; $i--) {
            $lowest = $lowest === null || $balances[$i]->lessThan($lowest) ? $balances[$i] : $lowest;
            $suffixMins[$i] = $lowest;
        }
        ksort($suffixMins);

        return new self($start, $startCash, $dates, $balances, array_values($suffixMins));
    }

    /**
     * End-of-day cash on a date.
     */
    public function at(CarbonImmutable $date): Money
    {
        $index = $this->lastIndexOnOrBefore($date->format('Y-m-d'));

        return $index === null ? $this->startCash : $this->balances[$index];
    }

    /**
     * The lowest end-of-day cash on the date or any later date.
     */
    public function suffixMin(CarbonImmutable $date): Money
    {
        $key = $date->format('Y-m-d');
        $current = $this->at($date);
        $index = $this->lastIndexOnOrBefore($key);
        $next = $index === null ? 0 : $index + 1;

        if ($next >= count($this->dates)) {
            return $current;
        }

        return $this->suffixMins[$next]->lessThan($current) ? $this->suffixMins[$next] : $current;
    }

    /**
     * @return list<array{date: string, cash: Money}>
     */
    public function points(): array
    {
        return array_map(fn (string $date, Money $cash) => ['date' => $date, 'cash' => $cash], $this->dates, $this->balances);
    }

    private function lastIndexOnOrBefore(string $key): ?int
    {
        $low = 0;
        $high = count($this->dates) - 1;
        $found = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->dates[$mid] <= $key) {
                $found = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $found;
    }
}
