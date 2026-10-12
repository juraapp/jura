<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Support\Forecast\EngineInput;

/**
 * The data a planner run works on: the engine input plus display details.
 */
final readonly class PlannerContext
{
    /**
     * @param  array<int, string>  $bucketNames
     * @param  array<int, float>  $startBalances
     */
    public function __construct(
        public EngineInput $input,
        public array $bucketNames,
        public array $startBalances,
        public bool $hasFillRules,
    ) {}
}
