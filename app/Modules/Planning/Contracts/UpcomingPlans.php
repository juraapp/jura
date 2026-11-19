<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Identity\Models\User;

/**
 * Open planned money for other modules' pages: what is overdue and coming up, which transactions came from a
 * plan, and what needs reviewing. Plain arrays of primitives only (planned rows as PlannedEventPresenter
 * shapes them), never a model or a Money object.
 */
interface UpcomingPlans
{
    /** Rows per band; the band says how many there are in all. */
    public const BAND_LIMIT = 10;

    /**
     * Open plans before today (overdue, each with its suggested matching transaction) and from today up to
     * $days ahead.
     *
     * @param  array{account?: int|null, category?: int|null, type?: string|null}  $filters  account matches either side of a transfer
     * @return array{overdue: array{count: int, items: list<array<string, mixed>>}, upcoming: array{count: int, items: list<array<string, mixed>>}}
     */
    public function listBands(User $user, int $days, array $filters = []): array;

    /**
     * Which of these transactions realized a plan.
     *
     * @param  list<int>  $transactionIds
     * @return array<int, array{plannedEventId: int, seriesId: int|null}> keyed by transaction id
     */
    public function provenance(User $user, array $transactionIds): array;

    /**
     * Open plans that need the user: overdue ones, and ones that could not be recorded automatically.
     *
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function needsReview(User $user, int $limit): array;

    public function needsReviewCount(User $user): int;
}
