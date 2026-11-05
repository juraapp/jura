<?php

namespace App\Modules\Planning\Support\Forecast;

/**
 * What a planned flow does to on-budget cash: income adds (and may fill buckets), an expense takes (and may shrink
 * category envelopes), a boundary flow is money crossing the budget's edge (a planned transfer to or from an
 * off-budget account, signed).
 */
enum FlowKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Boundary = 'boundary';
}
