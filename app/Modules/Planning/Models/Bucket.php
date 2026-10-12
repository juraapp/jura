<?php

namespace App\Modules\Planning\Models;

use App\Modules\Ledger\Models\Category;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\CyclePeriod;
use App\Modules\Shared\Casts\AsMoney;
use App\Modules\Shared\Concerns\BelongsToUser;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A virtual earmark of money. A spending bucket tracks a category against a
 * per-cycle limit; a savings bucket just holds funded money (optionally toward
 * a target). Funded money is the sum of its allocations.
 */
#[Fillable([
    'name', 'kind', 'currency', 'priority', 'category_id', 'cycle', 'cycle_every', 'cycle_anchor', 'cycle_goal', 'cap_amount', 'fill_rule', 'fill_value',
    'step_up_value', 'step_up_after_bucket_id', 'completed_on', 'completes_on_purchase', 'carry_over', 'overflow_bucket_id', 'allowed_account_ids', 'is_default', 'tracking_started_on', 'cycle_closed_through',
])]
class Bucket extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => BucketKind::class,
            'cycle' => BudgetPeriodType::class,
            'cycle_every' => 'integer',
            'cycle_anchor' => 'date',
            'cycle_goal' => AsMoney::class,
            'cap_amount' => AsMoney::class,
            'fill_rule' => FillRule::class,
            'carry_over' => CarryOverPolicy::class,
            'allowed_account_ids' => 'array',
            'is_default' => 'boolean',
            'tracking_started_on' => 'date',
            'cycle_closed_through' => 'date',
            'completed_on' => 'date',
            'completes_on_purchase' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected function currency(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    /**
     * The repeating window this bucket works in, or null for buckets without a cycle.
     */
    public function cyclePeriod(): ?CyclePeriod
    {
        if ($this->cycle === null) {
            return null;
        }

        return new CyclePeriod(
            $this->cycle,
            max(1, (int) ($this->cycle_every ?? 1)),
            $this->cycle_anchor !== null ? new DateTimeImmutable($this->cycle_anchor->toDateString()) : null,
        );
    }

    /**
     * The limit/target that applies on a date: one set for that cycle only wins over the bucket's own.
     */
    public function effectiveCap(CarbonInterface $date): ?Money
    {
        $period = $this->cyclePeriod();

        if ($period !== null) {
            $override = BucketCycle::forUser($this->user_id)
                ->where('bucket_id', $this->id)
                ->where('starts_on', $period->startOf($date->toDateTimeImmutable())->format('Y-m-d'))
                ->value('cap_override');

            if ($override !== null) {
                return Money::of($override);
            }
        }

        return $this->cap_amount;
    }

    /**
     * Whether the bucket has been closed (its goal reached and spent) and no longer receives income.
     */
    public function isCompletedOn(CarbonInterface $date): bool
    {
        return $this->completed_on !== null && $this->completed_on->lte($date->copy()->startOfDay());
    }

    public function stepUpAfter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'step_up_after_bucket_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(BucketCycle::class);
    }

    public function scopeInPriorityOrder(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function overflowBucket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'overflow_bucket_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * Whether this bucket measures category spending against a per-cycle limit.
     */
    public function tracksLimit(): bool
    {
        return $this->kind === BucketKind::Spending
            && $this->category_id !== null
            && $this->cap_amount !== null
            && $this->cycle !== null;
    }

    /**
     * Whether income landing in this account may fill the bucket (no restriction = any account).
     */
    public function acceptsAccount(?int $accountId): bool
    {
        $allowed = $this->allowed_account_ids;

        return empty($allowed) || ($accountId !== null && in_array($accountId, $allowed, true));
    }
}
