<?php

namespace App\Modules\Planning\Support\Forecast;

/**
 * Work the engine can reuse between runs over one input (the suggester runs it hundreds of times on the same
 * input): the purchase-free days, simulated once, and the state right after each purchase, keyed by the lines bought
 * so far. Runs that share a beginning resume from there instead of simulating it again. Internal to the engine.
 *
 * @internal
 */
final class EngineMemo
{
    /** Snapshots kept per input, so a long suggestion never holds unbounded memory. */
    public const MAX_SNAPSHOTS = 4000;

    /** @var array<string, list<EngineState>> days key => the state of each day after its flows, before purchases */
    public array $baselines = [];

    /** @var array<string, array<string, array{0: EngineState, 1: int}>> days key => lines bought so far => [state, day index] */
    public array $snapshots = [];

    public int $count = 0;
}
