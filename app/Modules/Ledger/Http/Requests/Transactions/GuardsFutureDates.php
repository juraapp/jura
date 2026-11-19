<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use Closure;
use Illuminate\Support\Carbon;

/**
 * A real transaction dated after the user's today counts in the balance right away, which is rarely what
 * was meant: the form asks first, and the request passes only when it is sent again with confirm_future.
 * CSV imports and bulk edits are not asked.
 */
trait GuardsFutureDates
{
    /**
     * @param  string|null  $currentDate  the date the transaction already has (an edit that keeps it is not asked)
     */
    protected function futureDateGuard(?string $currentDate = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentDate) {
            if ($this->boolean('confirm_future') || ! is_string($value) || strtotime($value) === false) {
                return;
            }

            $date = Carbon::parse($value)->toDateString();
            if ($date !== $currentDate && $date > $this->user()->today()->toDateString()) {
                $fail(__('transactions.future_date_confirm'));
            }
        };
    }
}
