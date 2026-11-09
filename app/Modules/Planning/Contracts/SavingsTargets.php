<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Identity\Models\User;

/**
 * How a user's savings buckets that have a target date are doing: on pace or behind. Safe to call without a
 * logged-in user (it scopes everything by the user it is given), so alerts can use it from the console.
 */
interface SavingsTargets
{
    /**
     * Every open savings bucket of the user with a target amount and a target date.
     *
     * `basis` says how "behind" was decided: `plan` for a bucket the user's income plan fills (a fill rule or a split
     * on a planned income): the forecast must hold its target by the target date (`projectedOn` is the day it does,
     * null if not within the look-ahead); `pace` for any other bucket: the money put aside so far against an even
     * pace from the day the bucket was made.
     *
     * @return list<array{bucketId: int, name: string, currency: string, targetDate: string, overdue: bool, percent: float, behind: bool, projectedOn: string|null, neededPerMonth: float, basis: string}>
     */
    public function targets(User $user): array;
}
