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

    /** Normalized header cell => canonical field. Accepts both English and Spanish headers; 'valor'/'amount'/'monto' are synonyms; 'moneda'/'currency' is tolerated but ignored. */
    private const COLUMN_ALIASES = [
        'fecha' => 'fecha',
        'date' => 'fecha',
        'descripcion' => 'descripcion',
        'description' => 'descripcion',
        'categoria' => 'categoria',
        'category' => 'categoria',
        'cuenta' => 'cuenta',
        'account' => 'cuenta',
        'tipo' => 'tipo',
        'type' => 'tipo',
        'notas' => 'notas',
        'notes' => 'notas',
        'monto' => 'monto',
        'amount' => 'monto',
        'valor' => 'monto',
        'value' => 'monto',
    ];

    private const REQUIRED_COLUMNS = ['fecha' => 'Date', 'cuenta' => 'Account', 'categoria' => 'Category', 'tipo' => 'Type', 'monto' => 'Amount'];

    /**
     * @return array<int, array{line: int, fecha: ?string, descripcion: ?string, categoria: ?string, cuenta: ?string, tipo: ?string, notas: ?string, monto: ?string}>
     */
    public function parse(string $realPath): array
    {
        $handle = fopen($realPath, 'r');

        if ($handle === false) {
            throw new TransactionImportException(__('The file could not be read.'));
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false || trim($firstLine) === '') {
                throw new TransactionImportException(__('The file is empty.'));
            }

            rewind($handle);
            $delimiter = $this->detectDelimiter($this->stripBom($firstLine));

            $header = fgetcsv($handle, 0, $delimiter, '"', '\\');

            if ($header === false) {
                throw new TransactionImportException(__('The file is empty.'));
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
                    throw new TransactionImportException(
                        __('The file has more than :max rows; split it into smaller files.', ['max' => self::MAX_ROWS])
                    );
                }

                $rows[] = [
                    'line' => $line,
                    'fecha' => $this->columnValue($data, $columnMap, 'fecha'),
                    'descripcion' => $this->columnValue($data, $columnMap, 'descripcion'),
                    'categoria' => $this->columnValue($data, $columnMap, 'categoria'),
                    'cuenta' => $this->columnValue($data, $columnMap, 'cuenta'),
                    'tipo' => $this->columnValue($data, $columnMap, 'tipo'),
                    'notas' => $this->columnValue($data, $columnMap, 'notas'),
                    'monto' => $this->columnValue($data, $columnMap, 'monto'),
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

        $type = $this->resolveType($row['tipo']);
        if ($type === null) {
            $errors[] = __("Invalid type: ':type' (use 'Income' or 'Expense').", ['type' => $row['tipo']]);
        }

        $accountId = $accounts[Str::lower(trim((string) $row['cuenta']))] ?? null;
        if ($accountId === null) {
            $errors[] = __("Account ':account' not found.", ['account' => $row['cuenta']]);
        }

        $categoryId = null;
        if ($type !== null) {
            $categoryKey = $type->value.'|'.Str::lower(trim((string) $row['categoria']));
            $categoryId = $categories[$categoryKey] ?? null;
            if ($categoryId === null) {
                $errors[] = __("Category ':category' doesn't exist for type ':type'.", ['category' => $row['categoria'], 'type' => $type->label()]);
            }
        }

        $amount = $this->parseAmount($row['monto']);
        if ($amount === null) {
            $errors[] = __("Invalid amount: ':amount'.", ['amount' => $row['monto']]);
        }

        $date = $this->parseDate($row['fecha']);
        if ($date === null) {
            $errors[] = __("Invalid date: ':date'.", ['date' => $row['fecha']]);
        }

        $description = $row['descripcion'];
        if ($description !== null && mb_strlen($description) > 255) {
            $errors[] = __('Description exceeds 255 characters.');
        }

        $notes = $row['notas'];
        if ($notes !== null && mb_strlen($notes) > 2000) {
            $errors[] = __('Notes exceed 2000 characters.');
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

    /** @return array<string, int> canonical field => column index */
    private function mapColumns(array $header): array
    {
        $map = [];

        foreach ($header as $index => $cell) {
            $normalized = Str::of((string) $cell)->trim()->lower()->ascii()->toString();

            if (isset(self::COLUMN_ALIASES[$normalized])) {
                $map[self::COLUMN_ALIASES[$normalized]] = $index;
            }
        }

        return $map;
    }

    private function assertRequiredColumns(array $columnMap): void
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $key => $label) {
            if (! isset($columnMap[$key])) {
                $missing[] = __($label);
            }
        }

        if (! empty($missing)) {
            throw new TransactionImportException(__('Required columns are missing from the file: :columns.', ['columns' => implode(', ', $missing)]));
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
