<?php

use App\Exceptions\TransactionImportException;
use App\Models\Transaction;
use App\Services\TransactionImportService;
use App\Services\TransactionService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'idle';

    public array $parsedRows = [];

    public ?string $parseError = null;

    public bool $includeDuplicates = false;

    public int $importedCount = 0;

    public int $skippedCount = 0;

    public int $duplicateSkippedCount = 0;

    public function mount(): void
    {
        $this->authorize('create', Transaction::class);
    }

    public function updatedFile(TransactionImportService $importer): void
    {
        $this->authorize('create', Transaction::class);
        $this->parseError = null;

        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        try {
            $rawRows = $importer->parse($this->file->getRealPath());

            if (empty($rawRows)) {
                $this->parseError = __('The file has no data rows.');
                $this->reset('file');

                return;
            }

            $this->parsedRows = $importer->validateRows(auth()->user(), $rawRows);
            $this->step = 'preview';
        } catch (TransactionImportException $e) {
            $this->parseError = $e->getMessage();
            $this->reset('file');
        }
    }

    public function confirmImport(TransactionImportService $importer, TransactionService $service): void
    {
        $this->authorize('create', Transaction::class);

        $rowsToImport = array_filter(
            $this->parsedRows,
            fn (array $row) => $row['status'] === 'valid' || ($row['status'] === 'duplicate' && $this->includeDuplicates)
        );

        $result = $importer->import(auth()->user(), $rowsToImport, $service);

        $this->importedCount = $result['imported'];
        $this->duplicateSkippedCount = $this->includeDuplicates ? 0 : $this->countByStatus('duplicate');
        $this->skippedCount = $this->countByStatus('error');
        $this->step = 'done';

        $this->dispatch('finances-updated');
        $this->dispatch('toast', type: 'success', message: __(':count transactions imported.', ['count' => $result['imported']]));
    }

    public function startOver(): void
    {
        $this->reset(['file', 'step', 'parsedRows', 'parseError', 'includeDuplicates', 'importedCount', 'skippedCount', 'duplicateSkippedCount']);
    }

    public function countByStatus(string $status): int
    {
        return count(array_filter($this->parsedRows, fn ($row) => $row['status'] === $status));
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Import transactions') }}
            </h2>
            <a href="{{ route('transactions.index') }}" wire:navigate class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                {{ __('Back to transactions') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if ($step === 'idle')
            <div
                x-data="{ dragging: false }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="
                    dragging = false;
                    $refs.fileInput.files = $event.dataTransfer.files;
                    $refs.fileInput.dispatchEvent(new Event('change'));
                "
                :class="dragging ? 'border-primary-400 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-300 dark:border-gray-700'"
                class="rounded-xl border-2 border-dashed px-6 py-16 text-center transition"
            >
                <input type="file" x-ref="fileInput" wire:model="file" accept=".csv,text/csv" class="hidden">

                <x-dynamic-icon name="arrow-up-tray" class="mx-auto h-10 w-10 text-gray-400" />

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Drag your CSV file here or') }}
                    <button type="button" x-on:click="$refs.fileInput.click()" class="font-medium text-primary-600 hover:underline">
                        {{ __('select a file') }}
                    </button>
                </p>

                <div wire:loading wire:target="file" class="mt-3 text-sm text-gray-500">{{ __('Processing file…') }}</div>

                <x-input-error class="mt-3" :messages="$errors->get('file')" />

                @if ($parseError)
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $parseError }}</p>
                @endif

                <div class="mt-6 space-y-2">
                    <a href="{{ route('transactions.import.template') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:underline">
                        <x-dynamic-icon name="arrow-down-tray" class="h-4 w-4" />
                        {{ __('Download CSV template') }}
                    </a>
                    <p class="text-xs text-gray-400">
                        {{ __('Replace the example rows with your real data, using the exact names of your existing accounts and categories.') }}
                    </p>
                </div>
            </div>
        @elseif ($step === 'preview')
            @php
                $validCount = $this->countByStatus('valid');
                $duplicateCount = $this->countByStatus('duplicate');
                $errorCount = $this->countByStatus('error');
            @endphp

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold text-green-600 dark:text-green-400">{{ $validCount }} {{ __('new') }}</span>
                        &middot;
                        <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $duplicateCount }} {{ __('possible duplicates') }}</span>
                        &middot;
                        <span class="font-semibold text-red-600 dark:text-red-400">{{ $errorCount }} {{ __('with error') }}</span>
                    </p>

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <input wire:model="includeDuplicates" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                            {{ __('Import duplicates too') }}
                        </label>
                        <button wire:click="startOver" type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            {{ __('Cancel') }}
                        </button>
                        <button
                            wire:click="confirmImport"
                            type="button"
                            @disabled($validCount === 0 && ! $this->includeDuplicates)
                            class="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ __('Import') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Line') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Category') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Account') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Amount') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($parsedRows as $row)
                                <tr>
                                    <td class="px-4 py-2.5 text-sm text-gray-400">{{ $row['line'] }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $row['raw']['fecha'] }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $row['raw']['tipo'] }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $row['raw']['categoria'] }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $row['raw']['cuenta'] }}</td>
                                    <td class="px-4 py-2.5 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ $row['raw']['monto'] }}</td>
                                    <td class="px-4 py-2.5 text-sm">
                                        @if ($row['status'] === 'valid')
                                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                <x-dynamic-icon name="check-circle" class="h-4 w-4" />
                                                {{ __('New') }}
                                            </span>
                                        @elseif ($row['status'] === 'duplicate')
                                            <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400" title="{{ __('A transaction with the same account, date, amount, and description already exists.') }}">
                                                <x-dynamic-icon name="exclamation-triangle" class="h-4 w-4" />
                                                {{ __('Possible duplicate') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-start gap-1 text-red-600 dark:text-red-400">
                                                <x-dynamic-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                                                <span>{{ implode(' ', $row['errors']) }}</span>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <x-empty-state
                icon="check-circle"
                :title="__(':count transactions imported.', ['count' => $importedCount])"
                :description="trim(
                    ($skippedCount > 0 ? __(':count rows were skipped due to errors. ', ['count' => $skippedCount]) : '')
                    . ($duplicateSkippedCount > 0 ? __(':count possible duplicates were not imported.', ['count' => $duplicateSkippedCount]) : '')
                ) ?: null"
            >
                <x-slot name="action">
                    <div class="flex items-center gap-3">
                        <button wire:click="startOver" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                            <x-dynamic-icon name="arrow-up-tray" class="h-4 w-4" />
                            {{ __('Import another file') }}
                        </button>
                        <a href="{{ route('transactions.index') }}" wire:navigate class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            {{ __('View transactions') }}
                        </a>
                    </div>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</div>
