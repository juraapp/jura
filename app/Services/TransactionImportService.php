<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\TransactionImportException;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parses, validates and imports a CSV of income/expense rows. Never writes
 * a Transaction directly — actual persistence always goes through
 * TransactionService, so cache invalidation and notification side effects
 * stay identical to a manually-created transaction.
 */
class TransactionImportService
{
    public const MAX_ROWS = 1000;

    /** Extra header synonyms not already covered by the en/es messages.csv labels. */
    private const EXTRA_COLUMN_ALIASES = [
        'valor' => 'amount',
        'value' => 'amount',
    ];

    private const REQUIRED_COLUMNS = ['date', 'account', 'category', 'type', 'amount'];

    /**
     * @return array<int, array{line: int, date: ?string, description: ?string, category: ?string, account: ?string, type: ?string, notes: ?string, amount: ?string}>
     */
    public function parse(string $realPath): array
    {
        $handle = fopen($realPath, 'r');

        if ($handle === false) {
            throw new TransactionImportException(__('messages.import.could_not_read_file'));
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false || trim($firstLine) === '') {
                throw new TransactionImportException(__('messages.import.empty_file'));
            }

            rewind($handle);
            $delimiter = $this->detectDelimiter($this->stripBom($firstLine));

            $header = fgetcsv($handle, 0, $delimiter, '"', '\\');

            if ($header === false) {
                throw new TransactionImportException(__('messages.import.empty_file'));
            }

            $header[0] = $this->stripBom((string) $header[0]);
            $columnMap = $this->mapColumns($header);
            $this->assertRequiredColumns($columnMap);

            $rows = [];
            $line = 1;

            while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $line++;

                if (count($data) === 1 && trim((string) $data[0]) === '') {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw new TransactionImportException(__('messages.import.too_many_rows', ['max' => self::MAX_ROWS]));
                }

                $rows[] = [
                    'line' => $line,
                    'date' => $this->columnValue($data, $columnMap, 'date'),
                    'description' => $this->columnValue($data, $columnMap, 'description'),
                    'category' => $this->columnValue($data, $columnMap, 'category'),
                    'account' => $this->columnValue($data, $columnMap, 'account'),
                    'type' => $this->columnValue($data, $columnMap, 'type'),
                    'notes' => $this->columnValue($data, $columnMap, 'notes'),
                    'amount' => $this->columnValue($data, $columnMap, 'amount'),
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, array>  $rows
     * @return array<int, array{line: int, status: string, errors: string[], raw: array, resolved: ?array}>
     */
    public function validateRows(User $user, array $rows): array
    {
        $accounts = $this->loadAccountLookup($user);
        $categories = $this->loadCategoryLookup();

        return array_map(fn (array $row) => $this->validateRow($row, $accounts, $categories), $rows);
    }

    /**
     * Persists only the given rows (already-validated `resolved` payloads),
     * one call per row through TransactionService — wrapped in a single
     * transaction so the batch is all-or-nothing.
     *
     * @param  array<int, array>  $rowsToImport
     * @return array{imported: int}
     */
    public function import(User $user, array $rowsToImport, TransactionService $service): array
    {
        $imported = 0;

        DB::transaction(function () use ($user, $rowsToImport, $service, &$imported) {
            foreach ($rowsToImport as $row) {
                $data = $row['resolved'];

                $data['type'] === TransactionType::Income
                    ? $service->createIncome($user, $data)
                    : $service->createExpense($user, $data);

                $imported++;
            }
        });

        return ['imported' => $imported];
    }

    private function validateRow(array $row, array $accounts, array $categories): array
    {
        $errors = [];

        $type = $this->resolveType($row['type']);
        if ($type === null) {
            $errors[] = __('messages.import.invalid_type', ['value' => (string) $row['type']]);
        }

        $accountId = $accounts[Str::lower(trim((string) $row['account']))] ?? null;
        if ($accountId === null) {
            $errors[] = __('messages.import.account_not_found', ['account' => (string) $row['account']]);
        }

        $categoryId = null;
        if ($type !== null) {
            $categoryKey = $type->value.'|'.Str::lower(trim((string) $row['category']));
            $categoryId = $categories[$categoryKey] ?? null;
            if ($categoryId === null) {
                $errors[] = __('messages.import.category_not_found', ['category' => (string) $row['category'], 'type' => $type->label()]);
            }
        }

        $amount = $this->parseAmount($row['amount']);
        if ($amount === null) {
            $errors[] = __('messages.import.invalid_amount', ['value' => (string) $row['amount']]);
        }

        $date = $this->parseDate($row['date']);
        if ($date === null) {
            $errors[] = __('messages.import.invalid_date', ['value' => (string) $row['date']]);
        }

        $description = $row['description'];
        if ($description !== null && mb_strlen($description) > 255) {
            $errors[] = __('messages.import.description_too_long');
        }

        $notes = $row['notes'];
        if ($notes !== null && mb_strlen($notes) > 2000) {
            $errors[] = __('messages.import.notes_too_long');
        }

        if (! empty($errors)) {
            return ['line' => $row['line'], 'status' => 'error', 'errors' => $errors, 'raw' => $row, 'resolved' => null];
        }

        $resolved = [
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $type,
            'amount' => $amount,
            'date' => $date->toDateString(),
            'description' => $description,
            'notes' => $notes,
        ];

        $duplicateQuery = Transaction::where('account_id', $accountId)
            ->where('date', $resolved['date'])
            ->where('amount', $amount);

        $description === null ? $duplicateQuery->whereNull('description') : $duplicateQuery->where('description', $description);

        return [
            'line' => $row['line'],
            'status' => $duplicateQuery->exists() ? 'duplicate' : 'valid',
            'errors' => [],
            'raw' => $row,
            'resolved' => $resolved,
        ];
    }

    private function resolveType(?string $raw): ?TransactionType
    {
        return match (Str::lower(trim((string) $raw))) {
            'ingreso', 'income' => TransactionType::Income,
            'gasto', 'expense' => TransactionType::Expense,
            default => null,
        };
    }

    /**
     * Accepts "1234.56", "1234", "1.234,56" (es) and "1,234.56" (en); returns
     * a normalized DECIMAL(15,2)-ready string, or null if unparseable/not positive.
     */
    private function parseAmount(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $raw) || (str_contains($raw, ',') && ! str_contains($raw, '.'))) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $raw)) {
            $raw = str_replace(',', '', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $amount = Money::of($raw);

        return $amount->isPositive() ? $amount->toDecimalString() : null;
    }

    private function parseDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $raw);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** @return array<string, int> lowercased account name => id */
    private function loadAccountLookup(User $user): array
    {
        return $user->accounts()->get(['id', 'name'])
            ->mapWithKeys(fn (Account $account) => [Str::lower(trim($account->name)) => $account->id])
            ->all();
    }

    /** @return array<string, int> "type|lowercased category name" => id, scoped to categories visible to the current user */
    private function loadCategoryLookup(): array
    {
        return Category::query()->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Category $category) => [$category->type->value.'|'.Str::lower(trim($category->name)) => $category->id])
            ->all();
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function detectDelimiter(string $sampleLine): string
    {
        return substr_count($sampleLine, ';') > substr_count($sampleLine, ',') ? ';' : ',';
    }

    /**
     * Builds the accepted-header-cell => canonical-field map from both
     * locales' messages.csv labels (so an English or Spanish template CSV
     * both import correctly), plus a couple of extra known synonyms.
     *
     * @return array<string, string>
     */
    private function columnAliases(): array
    {
        $aliases = self::EXTRA_COLUMN_ALIASES;

        foreach (['en', 'es'] as $locale) {
            foreach (trans('messages.csv', [], $locale) as $field => $label) {
                $normalized = Str::of($label)->trim()->lower()->ascii()->toString();
                $aliases[$normalized] = $field;
            }
        }

        return $aliases;
    }

    /** @return array<string, int> canonical field => column index */
    private function mapColumns(array $header): array
    {
        $aliases = $this->columnAliases();
        $map = [];

        foreach ($header as $index => $cell) {
            $normalized = Str::of((string) $cell)->trim()->lower()->ascii()->toString();

            if (isset($aliases[$normalized])) {
                $map[$aliases[$normalized]] = $index;
            }
        }

        return $map;
    }

    private function assertRequiredColumns(array $columnMap): void
    {
        $missing = array_filter(
            self::REQUIRED_COLUMNS,
            fn (string $field) => ! isset($columnMap[$field])
        );

        if (! empty($missing)) {
            $labels = array_map(fn (string $field) => __("messages.csv.{$field}"), $missing);

            throw new TransactionImportException(__('messages.import.missing_columns', ['columns' => implode(', ', $labels)]));
        }
    }

    private function columnValue(array $data, array $columnMap, string $field): ?string
    {
        if (! isset($columnMap[$field]) || ! isset($data[$columnMap[$field]])) {
            return null;
        }

        $value = trim((string) $data[$columnMap[$field]]);

        return $value === '' ? null : $value;
    }
}
