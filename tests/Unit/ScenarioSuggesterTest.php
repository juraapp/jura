<?php

namespace Tests\Unit;

use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Support\Forecast\EngineBucket;
use App\Modules\Planning\Support\Forecast\EngineIncome;
use App\Modules\Planning\Support\Forecast\EngineInput;
use App\Modules\Planning\Support\Forecast\EngineLine;
use App\Modules\Planning\Support\Forecast\ForecastEngine;
use App\Modules\Planning\Support\Forecast\ScenarioSuggester;
use App\Modules\Planning\Support\Waterfall;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ScenarioSuggesterTest extends TestCase
{
    private function suggester(): ScenarioSuggester
    {
        return new ScenarioSuggester(new ForecastEngine(new Waterfall));
    }

    private function input(int $balance, ?string $fixed, array $incomes): EngineInput
    {
        $bucket = new EngineBucket(1, $fixed !== null ? FillRule::Fixed : null, $fixed, null, true, Money::of($balance), null, null, CarryOverPolicy::Carry, null, true);

        return new EngineInput(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2027-03-31'),
            [$bucket],
            array_map(fn (array $row) => new EngineIncome(CarbonImmutable::parse($row[0]), Money::of($row[1]), null), $incomes),
        );
    }

    private function item(int $id, int $price, int $importance, ?string $desiredBy = null): EngineLine
    {
        return new EngineLine($id, "Item {$id}", Money::of($price), 1, null, $desiredBy ? CarbonImmutable::parse($desiredBy) : null, $importance);
    }

    public function test_the_best_ordering_minimises_importance_weighted_waiting(): void
    {
        $input = $this->input(0, '500', [['2026-10-11', 500], ['2026-11-10', 500]]);

        $suggestions = $this->suggester()->suggest($input, [$this->item(1, 500, 1), $this->item(2, 500, 5)]);

        $this->assertSame('best', $suggestions[0]->strategy);
        $this->assertSame([2, 1], $suggestions[0]->order, 'the item you want more comes first');
    }

    public function test_a_cheap_item_that_is_affordable_now_can_beat_the_more_important_one(): void
    {
        $input = $this->input(100, '900', [['2026-10-31', 900]]);
        $cheap = $this->item(1, 100, 3);
        $dear = $this->item(2, 900, 4);

        $suggestions = $this->suggester()->suggest($input, [$dear, $cheap]);

        $this->assertSame([1, 2], $suggestions[0]->order);
        $importanceFirst = collect($suggestions)->firstWhere('strategy', 'importance');
        $this->assertNotNull($importanceFirst);
        $this->assertSame([2, 1], $importanceFirst->order);
        $this->assertGreaterThan($suggestions[0]->score, $importanceFirst->score);
    }

    public function test_an_item_that_can_never_be_afforded_is_ordered_last_so_it_does_not_block_the_rest(): void
    {
        $input = $this->input(1000, null, []);

        $suggestions = $this->suggester()->suggest($input, [$this->item(1, 99999, 5), $this->item(2, 100, 1)]);

        $this->assertSame([2, 1], $suggestions[0]->order);
        $this->assertNotNull($suggestions[0]->result->lines[0]->purchaseDate);
    }

    public function test_suggestions_are_distinct_orderings_and_respect_the_limit(): void
    {
        $input = $this->input(0, '300', [['2026-10-20', 1000], ['2026-12-20', 1000], ['2027-02-20', 1000]]);
        $items = [$this->item(1, 700, 2, '2026-12-31'), $this->item(2, 300, 5), $this->item(3, 500, 3, '2026-11-15')];

        $suggestions = $this->suggester()->suggest($input, $items, 3);

        $orders = array_map(fn ($suggestion) => implode(',', $suggestion->order), $suggestions);
        $this->assertLessThanOrEqual(3, count($suggestions));
        $this->assertSame($orders, array_values(array_unique($orders)));
        $scores = array_map(fn ($suggestion) => $suggestion->score, $suggestions);
        $this->assertSame($scores[0], min($scores), 'the first suggestion is the best-scoring one');
    }

    public function test_many_items_still_return_a_best_effort_suggestion(): void
    {
        $input = $this->input(0, '200', [['2026-10-20', 1000], ['2026-12-20', 1000]]);
        $items = array_map(fn (int $id) => $this->item($id, 100 * $id, ($id % 5) + 1), range(1, 8));

        $suggestions = $this->suggester()->suggest($input, $items);

        $this->assertNotEmpty($suggestions);
        $this->assertCount(8, $suggestions[0]->order);
        $this->assertEqualsCanonicalizing(range(1, 8), $suggestions[0]->order);
    }

    public function test_scoring_penalises_unbought_items_more_than_any_delay(): void
    {
        $input = $this->input(0, '500', [['2026-10-11', 1000]]);
        $suggester = $this->suggester();

        $engine = new ForecastEngine(new Waterfall);
        $bought = $suggester->score($input, $engine->run($input, [$this->item(1, 500, 3)]));
        $never = $suggester->score($input, $engine->run($input, [$this->item(1, 5000, 3)]));

        $this->assertGreaterThan($bought, $never);
    }

    public function test_an_empty_selection_has_no_suggestions(): void
    {
        $this->assertSame([], $this->suggester()->suggest($this->input(0, null, []), []));
    }
}
