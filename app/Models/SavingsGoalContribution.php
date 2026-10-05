<?php

namespace App\Models;

use App\Casts\AsMoney;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amount', 'date', 'linked_transaction_id', 'notes'])]
class SavingsGoalContribution extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => AsMoney::class,
            'date' => 'date',
        ];
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }
}
