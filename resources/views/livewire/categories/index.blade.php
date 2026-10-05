<?php

use App\Enums\CategoryType;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'expense';

    public string $icon = 'tag';

    public string $color = '#6b7280';

    public ?int $confirmingDeleteId = null;

    private array $iconChoices = [
        'tag', 'banknotes', 'wallet', 'home', 'cog', 'flag', 'document-chart-bar',
        'arrow-path', 'arrows-right-left', 'adjustments', 'exclamation-triangle',
        'arrow-down-circle', 'arrow-up-circle', 'arrow-trending-up', 'calendar',
    ];

    #[On('open-create-category')]
    public function create(): void
    {
        $this->authorize('create', Category::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->type = $category->type->value;
        $this->icon = $category->icon;
        $this->color = $category->color;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'icon' => ['required', 'string', 'max:32'],
            'color' => ['required', 'string', 'max:7'],
        ]);

        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);
            $this->authorize('update', $category);
            $category->update($data);
        } else {
            $this->authorize('create', Category::class);
            auth()->user()->categories()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Category saved successfully.'));
    }

    public function confirmDelete(int $categoryId): void
    {
        $this->authorize('delete', Category::findOrFail($categoryId));
        $this->confirmingDeleteId = $categoryId;
    }

    public function delete(): void
    {
        $category = Category::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $category);

        if ($category->transactions()->exists()) {
            $this->dispatch('toast', type: 'error', message: __('You can\'t delete a category that has recorded transactions.'));
        } else {
            $category->delete();
            $this->dispatch('toast', type: 'success', message: __('Category deleted.'));
        }

        $this->confirmingDeleteId = null;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->type = 'expense';
        $this->icon = 'tag';
        $this->color = '#6b7280';
    }

    public function with(): array
    {
        return [
            'expenseCategories' => Category::where('type', CategoryType::Expense)->orderBy('name')->get(),
            'incomeCategories' => Category::where('type', CategoryType::Income)->orderBy('name')->get(),
            'iconChoices' => $this->iconChoices,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Categories') }}
            </h2>
            <button onclick="Livewire.dispatch('open-create-category')" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="plus" class="h-4 w-4" />
                {{ __('New category') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        @foreach ([['Gastos', $expenseCategories], ['Ingresos', $incomeCategories]] as [$label, $items])
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $category)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-center gap-3">
                                <x-category-icon :category="$category" size="h-9 w-9" />
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $category->name }}</p>
                                    @if ($category->is_system)
                                        <span class="text-xs text-gray-400">{{ __('System category') }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($category->isEditable() && $category->user_id === auth()->id())
                                <div class="flex items-center gap-1">
                                    <button wire:click="edit({{ $category->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                        <x-dynamic-icon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button wire:click="confirmDelete({{ $category->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                        <x-dynamic-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="show = false" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-16 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $editingId ? __('Edit category') : __('New category') }}
                </h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="save" class="space-y-4 px-6 py-5">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="type" :value="__('Type')" />
                    <select wire:model="type" id="type" @disabled($editingId) class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="expense">{{ __('Expense') }}</option>
                        <option value="income">{{ __('Income') }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="icon" :value="__('Icon')" />
                        <select wire:model="icon" id="icon" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach ($iconChoices as $choice)
                                <option value="{{ $choice }}">{{ $choice }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="color" :value="__('Color')" />
                        <input wire:model="color" id="color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full" style="background-color: {{ $color }}1a; color: {{ $color }};">
                        <x-dynamic-icon :name="$icon" class="h-4 w-4" />
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Preview') }}</span>
                </div>
            </form>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="save" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Confirmación de borrado --}}
    <div x-data="{ show: @entangle('confirmingDeleteId').live }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="$wire.confirmingDeleteId = null" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this category?') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('This action cannot be undone.') }}</p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="$wire.confirmingDeleteId = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="delete" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
