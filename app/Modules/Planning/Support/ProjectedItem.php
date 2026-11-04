<?php

namespace App\Modules\Planning\Support;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * One open planned movement of money: a stored plan, or a virtual occurrence of a repeating plan beyond the
 * dates stored so far. Projected data only; it never touches a balance.
 */
final readonly class ProjectedItem
{
    /**
     * @param  list<array{bucket_id: int, amount: string}>|null  $allocationOverrides
     */
    public function __construct(
        public ?int $eventId,
        public ?int $seriesId,
        public TransactionType $type,
        public CarbonImmutable $date,
        public Money $amount,
        public string $currency,
        public ?int $accountId,
        public ?int $counterAccountId,
        public ?int $categoryId,
        public Certainty $certainty,
        public ?array $allocationOverrides,
        public bool $virtual,
    ) {}

    /**
     * The date the money is expected to move: an overdue plan that is still open counts as happening today.
     */
    public function effectiveDate(CarbonImmutable $today): CarbonImmutable
    {
        $today = $today->startOfDay();

        return $this->date->lt($today) ? $today : $this->date;
    }

    public function isIncome(): bool
    {
        return $this->type === TransactionType::Income;
    }

    public function isExpense(): bool
    {
        return $this->type === TransactionType::Expense;
    }

    /**
     * A planned transfer moves money between two accounts of one currency: net zero for the currency.
     */
    public function isTransfer(): bool
    {
        return $this->type === TransactionType::TransferOut;
    }
}
