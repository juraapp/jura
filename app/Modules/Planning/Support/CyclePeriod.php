<?php

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Enums\BudgetPeriodType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The repeating window a bucket works in: every N days, weeks, months or years,
 * counted from an anchor date. Pure date arithmetic, no framework.
 *
 * Monthly and yearly windows keep the anchor's day of the month; a day past the
 * end of a short month falls on that month's last day (the 31st becomes the 28th).
 */
final readonly class CyclePeriod
{
    private const MAX_STEPS = 100000;

    public DateTimeImmutable $anchor;

    public function __construct(
        public BudgetPeriodType $unit,
        public int $every = 1,
        ?DateTimeImmutable $anchor = null,
    ) {
        if ($every < 1) {
            throw new InvalidArgumentException('A cycle repeats every 1 or more units.');
        }

        $this->anchor = ($anchor ?? $this->calendarAnchor())->setTime(0, 0);
    }

    /**
     * The start of the cycle that contains the date.
     */
    public function startOf(DateTimeImmutable $date): DateTimeImmutable
    {
        $date = $date->setTime(0, 0);

        if (in_array($this->unit, [BudgetPeriodType::Daily, BudgetPeriodType::Weekly], true)) {
            $length = $this->days();
            $elapsed = (int) $this->anchor->diff($date)->format('%r%a');

            return $this->anchor->modify(sprintf('%+d days', intdiv($elapsed, $length) * $length - ($elapsed < 0 && $elapsed % $length !== 0 ? $length : 0)));
        }

        $step = $this->monthStep();
        $months = ((int) $date->format('Y') - (int) $this->anchor->format('Y')) * 12 + ((int) $date->format('n') - (int) $this->anchor->format('n'));
        $index = intdiv($months, $step) - ($months < 0 && $months % $step !== 0 ? 1 : 0);

        for ($guard = 0; $guard < 4; $guard++) {
            $start = $this->startAt($index);
            if ($start > $date) {
                $index--;
            } elseif ($this->startAt($index + 1) <= $date) {
                $index++;
            } else {
                return $start;
            }
        }

        throw new InvalidArgumentException('Could not locate the cycle.');
    }

    /**
     * The last day of the cycle that contains the date.
     */
    public function endOf(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->nextStart($this->startOf($date))->modify('-1 day');
    }

    /**
     * The first day of the cycle after the one starting at $start.
     */
    public function nextStart(DateTimeImmutable $start): DateTimeImmutable
    {
        if (in_array($this->unit, [BudgetPeriodType::Daily, BudgetPeriodType::Weekly], true)) {
            return $start->modify('+'.$this->days().' days');
        }

        $months = ((int) $start->format('Y') - (int) $this->anchor->format('Y')) * 12 + ((int) $start->format('n') - (int) $this->anchor->format('n'));

        return $this->startAt(intdiv($months, $this->monthStep()) + 1);
    }

    /**
     * Every cycle that has completely ended before $today and began on or after $from.
     *
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}> [start, end] pairs, oldest first
     */
    public function completedBetween(DateTimeImmutable $from, DateTimeImmutable $today): array
    {
        $cycles = [];
        $start = $this->startOf($from);

        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            $next = $this->nextStart($start);
            if ($next > $today->setTime(0, 0)) {
                break;
            }

            $cycles[] = [$start, $next->modify('-1 day')];
            $start = $next;
        }

        return $cycles;
    }

    /**
     * Plain-language summary, e.g. "Every 2 weeks" or "Every month from the 15th".
     */
    public function describe(): string
    {
        $count = $this->every === 1 ? '' : $this->every.' ';
        $plural = $this->every === 1 ? '' : 's';

        return match ($this->unit) {
            BudgetPeriodType::Daily => $this->every === 1 ? 'Every day' : "Every {$this->every} days",
            BudgetPeriodType::Weekly => "Every {$count}week{$plural} from ".$this->anchor->format('l'),
            BudgetPeriodType::Monthly => "Every {$count}month{$plural} from the ".$this->ordinal((int) $this->anchor->format('j')),
            BudgetPeriodType::Yearly => "Every {$count}year{$plural} from ".$this->anchor->format('F j'),
        };
    }

    private function days(): int
    {
        return $this->unit === BudgetPeriodType::Weekly ? 7 * $this->every : $this->every;
    }

    private function monthStep(): int
    {
        return $this->unit === BudgetPeriodType::Yearly ? 12 * $this->every : $this->every;
    }

    /**
     * Start of the cycle $index steps after the anchor's cycle (months/years only).
     */
    private function startAt(int $index): DateTimeImmutable
    {
        $totalMonths = ((int) $this->anchor->format('n') - 1) + $index * $this->monthStep();
        $year = (int) $this->anchor->format('Y') + intdiv($totalMonths, 12) - ($totalMonths < 0 && $totalMonths % 12 !== 0 ? 1 : 0);
        $month = (($totalMonths % 12) + 12) % 12 + 1;
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $day = min((int) $this->anchor->format('j'), (int) $first->format('t'));

        return $first->setDate($year, $month, $day);
    }

    /**
     * Calendar-aligned defaults: the 1st of the month, January 1st, Mondays.
     */
    private function calendarAnchor(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->unit === BudgetPeriodType::Weekly ? '2000-01-03' : '2000-01-01');
    }

    private function ordinal(int $day): string
    {
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix;
    }
}
