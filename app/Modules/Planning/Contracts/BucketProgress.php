<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Support\Money;

interface BucketProgress
{
    /**
     * Limit progress of a spending bucket for its current cycle. Only valid when `$bucket->tracksLimit()`.
     *
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Bucket $bucket): array;
}
