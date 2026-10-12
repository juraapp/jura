<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Http\Requests\Planner\SimulateScenarioRequest;
use App\Modules\Planning\Http\Requests\Planner\StoreScenarioRequest;
use App\Modules\Planning\Http\Requests\Planner\SuggestScenarioRequest;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Scenario;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\PlannerService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlannerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', WishlistItem::class);

        $items = WishlistItem::with('bucket')->where('status', WishlistStatus::Wanted->value)->orderBy('id')->get();

        return Inertia::render('Planner/Index', [
            'items' => $items->map(fn (WishlistItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price->toFloat(),
                'currency' => $item->currency,
                'importance' => $item->importance,
                'desiredBy' => $item->desired_by?->toDateString(),
                'bucket' => $item->bucket ? ['id' => $item->bucket->id, 'name' => $item->bucket->name] : null,
            ])->values(),
            'currencies' => $items->pluck('currency')->unique()->sort()->values(),
            'scenarios' => Scenario::with('lines')->orderByDesc('id')->get()->map(fn (Scenario $scenario) => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'currency' => $scenario->currency,
                'view' => $scenario->forecast_view->value,
                'horizonMonths' => $scenario->horizon_months,
                'lines' => $scenario->lines->map(fn ($line) => [
                    'itemId' => $line->wishlist_item_id,
                    'notBefore' => $line->not_before?->toDateString(),
                ])->values(),
            ])->values(),
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $view) => ['value' => $view->value, 'label' => $view->label()]),
            'horizons' => [12, 24, 36],
            'hasFillRules' => Bucket::whereNotNull('fill_rule')->exists(),
        ]);
    }

    public function suggest(SuggestScenarioRequest $request, PlannerService $planner): JsonResponse
    {
        $data = $request->validated();
        $context = $planner->context($request->user(), strtoupper($data['currency']), ForecastView::from($data['view']), (int) $data['horizon_months']);
        $lines = $planner->lines(
            $request->user(),
            strtoupper($data['currency']),
            $context,
            array_map(fn ($id) => ['item_id' => $id], $data['item_ids']),
        );

        return response()->json(['suggestions' => $planner->suggest($context, $lines)]);
    }

    public function simulate(SimulateScenarioRequest $request, PlannerService $planner): JsonResponse
    {
        $data = $request->validated();
        $currency = strtoupper($data['currency']);
        $context = $planner->context($request->user(), $currency, ForecastView::from($data['view']), (int) $data['horizon_months']);

        return response()->json($planner->simulate($context, $planner->lines($request->user(), $currency, $context, $data['lines'])));
    }

    public function store(StoreScenarioRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $scenario = new Scenario([
                'name' => $data['name'],
                'currency' => $data['currency'],
                'forecast_view' => $data['view'],
                'horizon_months' => $data['horizon_months'],
            ]);
            $scenario->user_id = $request->user()->id;
            $scenario->save();

            foreach (array_values($data['lines']) as $position => $line) {
                $scenario->lines()->create([
                    'wishlist_item_id' => $line['item_id'],
                    'position' => $position + 1,
                    'not_before' => $line['not_before'] ?? null,
                ]);
            }
        });

        return back()->with('success', __('planner.saved'));
    }

    public function destroy(Scenario $scenario): RedirectResponse
    {
        $this->authorize('delete', $scenario);

        $scenario->delete();

        return back()->with('success', __('planner.deleted'));
    }
}
