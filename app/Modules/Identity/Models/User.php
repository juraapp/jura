<?php

namespace App\Modules\Identity\Models;

use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Planning\Models\SavingsGoal;
use App\Modules\Shared\Support\UserFormat;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'name', 'email', 'password', 'currency_default', 'locale', 'timezone',
    'date_format', 'time_format', 'decimal_separator', 'thousands_separator', 'fraction_digits', 'currency_display', 'currency_position',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'currency_default' => 'COP',
        'locale' => 'en',
        'timezone' => 'America/Bogota',
        'date_format' => 'auto',
        'time_format' => 'auto',
        'currency_display' => 'auto',
        'currency_position' => 'auto',
        'decimal_separator' => null,
        'thousands_separator' => null,
        'fraction_digits' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * How this user wants dates, times, numbers and money displayed.
     */
    public function formatter(): UserFormat
    {
        return new UserFormat($this->only([
            'date_format', 'time_format', 'decimal_separator', 'thousands_separator',
            'fraction_digits', 'currency_display', 'currency_position',
        ]), $this->locale ?? 'en');
    }

    /**
     * The user's timezone, or the app's when none (or an unknown one) is stored.
     */
    public function timezoneName(): string
    {
        $timezone = $this->timezone;

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone');
    }

    /**
     * The user's current calendar date, as midnight in the app timezone so it compares directly with stored
     * `date` columns. Use this, never today()/now(), whenever server code needs "today" for a user.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->timezoneName())->toDateString(), config('app.timezone'));
    }

    /**
     * Today for a user given by id, without a query when it is the authenticated user (services that only
     * hold a user_id, such as bucket maths, call this on every request).
     */
    public static function todayFor(self|int $user): CarbonImmutable
    {
        if ($user instanceof self) {
            return $user->today();
        }

        $current = Auth::user();
        if ($current instanceof self && $current->id === $user) {
            return $current->today();
        }

        return (static::find($user) ?? new static)->today();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function buckets(): HasMany
    {
        return $this->hasMany(Bucket::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }
}
