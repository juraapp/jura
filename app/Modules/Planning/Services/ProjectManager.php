<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Saves projects and keeps their savings bucket consistent: a project may use one of the user's savings buckets
 * or get a new one made for it, and deleting a project can delete that bucket too (its money then goes back to
 * ready to assign).
 */
class ProjectManager
{
    public function __construct(private readonly BucketManager $buckets) {}

    /**
     * @param  array<string, mixed>  $data  validated project data (see ProjectRules)
     */
    public function save(User $user, array $data, ?Project $project = null): Project
    {
        return DB::transaction(function () use ($user, $data, $project) {
            $project ??= new Project(['user_id' => $user->id]);
            $project->user_id ??= $user->id;

            $mode = $data['bucket_mode'] ?? 'none';
            $currency = isset($data['currency']) ? strtoupper($data['currency']) : $project->currency;

            $project->fill([
                'name' => $data['name'],
                'kind' => $data['kind'],
                'currency' => $currency,
                'budget_amount' => isset($data['budget_amount']) ? Money::of($data['budget_amount']) : null,
                'target_date' => $data['target_date'] ?? null,
                'color' => $data['color'] ?? $project->color ?? '#2563eb',
                'icon' => $data['icon'] ?? $project->icon ?? 'flag',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($mode === 'existing') {
                $project->bucket_id = (int) $data['bucket_id'];
            } elseif ($mode === 'none') {
                $project->bucket_id = null;
            }

            $project->save();

            if ($mode === 'new') {
                $this->createBucket($project, $data['new_bucket']);
            }

            return $project;
        });
    }

    /**
     * Makes a savings bucket for the project and points the project at it. Its target is the project's budget, or
     * what its wanted items cost when it has no budget.
     *
     * @param  array{fill_rule: string, fill_value: string|int|float}  $fill
     */
    public function createBucket(Project $project, array $fill): void
    {
        $user = User::findOrFail($project->user_id);
        $rule = FillRule::from($fill['fill_rule']);

        $planned = WishlistItem::forUser($user)
            ->where('project_id', $project->id)
            ->where('status', WishlistStatus::Wanted->value)
            ->where('currency', $project->currency)
            ->get()
            ->reduce(fn (Money $total, WishlistItem $item) => $total->add($item->price), Money::zero());

        $target = $project->budget_amount ?? ($planned->isPositive() ? $planned : null);

        $bucket = $this->buckets->save($user, [
            'name' => $project->name,
            'kind' => BucketKind::Savings->value,
            'currency' => $project->currency,
            'cap_amount' => $target?->toDecimalString(),
            'target_date' => $target !== null ? $project->target_date?->toDateString() : null,
            'fill_rule' => $rule->value,
            'fill_value' => $fill['fill_value'],
            'cycle' => $rule === FillRule::Fixed ? 'monthly' : null,
        ]);

        $project->update(['bucket_id' => $bucket->id]);
    }

    /**
     * Deletes the project. Its items stay (without a project); its bucket stays too unless asked to go, in which
     * case whatever it held is ready to assign again.
     */
    public function delete(Project $project, bool $deleteBucket = false): void
    {
        DB::transaction(function () use ($project, $deleteBucket) {
            $bucket = $deleteBucket ? $project->bucket : null;

            $project->delete();

            if ($bucket !== null) {
                $this->buckets->delete($bucket);
            }
        });
    }
}
