<?php

namespace App\Modules\Planning\Services;

use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use Carbon\CarbonInterface;

/**
 * Shapes plans and repeating plans as plain arrays for pages and JSON (never a model or a Money object).
 */
class PlannedEventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function event(PlannedEvent $event, ?\stdClass $match = null): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'description' => $event->description,
            'amount' => $event->amount->toFloat(),
            'currency' => $event->currency,
            'date' => $event->date->toDateString(),
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'certainty' => $event->certainty->value,
            'certaintyLabel' => $event->certainty->label(),
            'seriesId' => $event->series_id,
            'notes' => $event->notes,
            'allocationOverrides' => $event->allocation_overrides ?? [],
            'autoPost' => (bool) $event->auto_post,
            'postError' => $event->post_error,
            'account' => $event->account ? ['id' => $event->account->id, 'name' => $event->account->name] : null,
            'counterAccount' => $event->counterAccount ? ['id' => $event->counterAccount->id, 'name' => $event->counterAccount->name] : null,
            'category' => $event->category ? [
                'id' => $event->category->id,
                'name' => $event->category->name,
                'icon' => $event->category->icon,
                'color' => $event->category->color,
            ] : null,
            'suggestedMatch' => $match,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function series(PlannedEventSeries $series, ?CarbonInterface $nextDate): array
    {
        return [
            'id' => $series->id,
            'type' => $series->type->value,
            'description' => $series->description,
            'amount' => $series->amount->toFloat(),
            'currency' => $series->currency,
            'frequency' => $series->frequency->value,
            'frequencyLabel' => $series->frequency->label(),
            'certainty' => $series->certainty->value,
            'certaintyLabel' => $series->certainty->label(),
            'nextDate' => $nextDate?->toDateString(),
            'endsOn' => $series->ends_on?->toDateString(),
            'autoPost' => (bool) $series->auto_post,
            'isActive' => $series->is_active !== false,
            'notes' => $series->notes,
            'allocationOverrides' => $series->allocation_overrides ?? [],
            'account' => $series->account ? ['id' => $series->account->id, 'name' => $series->account->name] : null,
            'counterAccount' => $series->counterAccount ? ['id' => $series->counterAccount->id, 'name' => $series->counterAccount->name] : null,
            'category' => $series->category ? [
                'id' => $series->category->id,
                'name' => $series->category->name,
                'icon' => $series->category->icon,
                'color' => $series->category->color,
            ] : null,
        ];
    }
}
