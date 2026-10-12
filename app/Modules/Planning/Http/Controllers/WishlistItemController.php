<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Http\Requests\Wishlist\PurchaseWishlistItemRequest;
use App\Modules\Planning\Http\Requests\Wishlist\StoreWishlistItemRequest;
use App\Modules\Planning\Http\Requests\Wishlist\UpdateWishlistItemRequest;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\WishlistService;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WishlistItemController extends Controller
{
    public function index(BucketLedger $funds, LedgerQuery $ledger): Response
    {
        $this->authorize('viewAny', WishlistItem::class);

        $items = WishlistItem::with(['bucket', 'project'])->orderBy('id')->get();
        $available = $items->pluck('bucket')->filter()->unique('id')
            ->mapWithKeys(fn (Bucket $bucket) => [$bucket->id => $funds->available($bucket)]);

        $currencies = Auth::user()->accounts()->pluck('currency')->unique()->sort()->values();

        return Inertia::render('Wishlist/Index', [
            'projects' => Project::orderBy('name')->get()->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'kind' => $project->kind->value,
                'kindLabel' => $project->kind->label(),
                'color' => $project->color,
                'icon' => $project->icon,
                'notes' => $project->notes,
            ])->values(),
            'items' => $items->map(fn (WishlistItem $item) => $this->present($item, $available))->values(),
            'buckets' => Bucket::inPriorityOrder()->where('kind', BucketKind::Savings->value)->get(['id', 'name', 'currency', 'allowed_account_ids']),
            'accounts' => $ledger->activeAccountOptions(),
            'categories' => $ledger->categoryOptions(CategoryType::Expense, ['id', 'name', 'icon', 'color']),
            'currencies' => $currencies->isEmpty() ? collect([Auth::user()->currency_default]) : $currencies,
            'projectKinds' => collect(ProjectKind::cases())->map(fn (ProjectKind $kind) => ['value' => $kind->value, 'label' => $kind->label()]),
        ]);
    }

    public function store(StoreWishlistItemRequest $request): RedirectResponse
    {
        $item = new WishlistItem($this->attributes($request->validated(), $request->user()->currency_default));
        $item->user_id = $request->user()->id;
        $item->save();

        return back()->with('success', __('wishlist.saved'));
    }

    public function update(UpdateWishlistItemRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $data = $request->validated();
        $attributes = $this->attributes($data, $wishlistItem->currency);

        if ($wishlistItem->status !== WishlistStatus::Purchased && isset($data['status'])) {
            $attributes['status'] = $data['status'];
        }

        $wishlistItem->update($attributes);

        return back()->with('success', __('wishlist.saved'));
    }

    public function destroy(WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('delete', $wishlistItem);

        $wishlistItem->delete();

        return back()->with('success', __('wishlist.deleted'));
    }

    public function purchase(PurchaseWishlistItemRequest $request, WishlistItem $wishlistItem, WishlistService $service): RedirectResponse
    {
        if ($wishlistItem->status === WishlistStatus::Purchased) {
            return back()->with('error', __('wishlist.already_purchased'));
        }

        $service->purchase($wishlistItem, $request->validated());

        return back()->with('success', __('wishlist.purchased'));
    }

    public function unpurchase(WishlistItem $wishlistItem, WishlistService $service): RedirectResponse
    {
        $this->authorize('update', $wishlistItem);

        if ($wishlistItem->status !== WishlistStatus::Purchased) {
            return back()->with('error', __('wishlist.not_purchased'));
        }

        $service->undoPurchase($wishlistItem);

        return back()->with('success', __('wishlist.unpurchased'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $fallbackCurrency): array
    {
        return [
            'project_id' => $data['project_id'] ?? null,
            'bucket_id' => $data['bucket_id'] ?? null,
            'name' => $data['name'],
            'price' => Money::of($data['price']),
            'currency' => $data['currency'] ?? $fallbackCurrency,
            'importance' => $data['importance'] ?? 3,
            'desired_by' => $data['desired_by'] ?? null,
            'allowed_account_ids' => ! empty($data['allowed_account_ids']) ? array_map('intval', $data['allowed_account_ids']) : null,
            'url' => $data['url'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, Money>  $available  funded money by bucket id
     * @return array<string, mixed>
     */
    private function present(WishlistItem $item, Collection $available): array
    {
        $funded = null;
        if ($item->bucket !== null && $item->price->isPositive()) {
            $funded = max(0, min(100, $available->get($item->bucket_id, Money::zero())->percentageOf($item->price)));
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price->toFloat(),
            'currency' => $item->currency,
            'importance' => $item->importance,
            'desiredBy' => $item->desired_by?->toDateString(),
            'status' => $item->status->value,
            'statusLabel' => $item->status->label(),
            'purchasedOn' => $item->purchased_on?->toDateString(),
            'projectId' => $item->project_id,
            'bucket' => $item->bucket ? ['id' => $item->bucket->id, 'name' => $item->bucket->name] : null,
            'allowedAccountIds' => $item->allowed_account_ids ?? [],
            'fundedPercent' => $funded,
            'url' => $item->url,
            'notes' => $item->notes,
        ];
    }
}
