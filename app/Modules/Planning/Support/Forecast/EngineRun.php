<?php

namespace App\Modules\Planning\Support\Forecast;

use Carbon\CarbonImmutable;

/**
 * One engine run's fixed inputs: the days it visits, its lines and the key of every beginning of its line order.
 * Internal to the engine.
 *
 * @internal
 */
final readonly class EngineRun
{
    /** @var list<string> prefixes[k] = what identifies the first k lines (only what changes the simulation) */
    public array $prefixes;

    /**
     * @param  array<string, mixed>  $schedule
     * @param  list<string>  $dates
     * @param  list<EngineLine>  $lines
     * @param  array<int, string|null>  $notBefore
     */
    public function __construct(
        public EngineInput $input,
        public array $schedule,
        public array $dates,
        public array $lines,
        public array $notBefore,
        public string $daysKey,
    ) {
        $prefixes = [''];
        $key = '';
        foreach ($lines as $index => $line) {
            $key .= implode(':', [$line->itemId, $line->price->toDecimalString(), $line->bucketId ?? '-', $notBefore[$index] ?? '-', $line->blocked ?? '-']).'|';
            $prefixes[] = $key;
        }
        $this->prefixes = $prefixes;
    }

    public function moment(int $index): CarbonImmutable
    {
        $key = $this->dates[$index];

        return $this->schedule['moments'][$key] ?? CarbonImmutable::parse($key);
    }

    /**
     * Keeps the state right after a purchase so later runs that start with the same lines can resume from it.
     */
    public function remember(EngineState $state, int $index): void
    {
        $memo = $this->schedule['memo'];
        $prefix = $this->prefixes[$state->next];
        if ($memo->count >= EngineMemo::MAX_SNAPSHOTS || isset($memo->snapshots[$this->daysKey][$prefix])) {
            return;
        }

        $memo->snapshots[$this->daysKey][$prefix] = [clone $state, $index];
        $memo->count++;
    }
}
