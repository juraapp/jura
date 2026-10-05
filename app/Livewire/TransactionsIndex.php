<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Component;

#[Layout('layouts.app')]
class TransactionsIndex extends Component
{
    use WithPagination;

    public string $typeFilter = 'all';

    #[Url(as: 'cuenta', except: '')]
    public string $accountFilter = '';

    #[Url(as: 'categoria', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'desde', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'hasta', except: '')]
    public string $dateTo = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?int $confirmingDeleteId = null;

    /**
     * @var string título de página + tipo por defecto de la ruta que montó
     *      este componente (todas / income / expense / transfer).
     */
    public function mount(string $typeFilter = 'all'): void
    {
        $this->typeFilter = $typeFilter;
    }

    public function updating($property): void
    {
        if (in_array($property, ['accountFilter', 'categoryFilter', 'dateFrom', 'dateTo', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['accountFilter', 'categoryFilter', 'dateFrom', 'dateTo', 'search']);
    }

    public function edit(int $transactionId): void
    {
        $this->dispatch('edit-transaction', transactionId: $transactionId);
    }

    public function confirmDelete(int $transactionId): void
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('delete', $transaction);
        $this->confirmingDeleteId = $transactionId;
    }

    public function delete(TransactionService $service): void
    {
        $transaction = Transaction::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $transaction);

        $service->delete($transaction);

        $this->confirmingDeleteId = null;
        $this->dispatch('toast', type: 'success', message: __('Transaction deleted.'));
    }

    #[On('finances-updated')]
    public function refresh(): void
    {
        // Livewire vuelve a ejecutar render() en el próximo ciclo automáticamente.
    }

    public function pageTitle(): string
    {
        return match ($this->typeFilter) {
            'income' => __('Income'),
            'expense' => __('Expenses'),
            'transfer' => __('Transfers'),
            default => __('Transactions'),
        };
    }

    public function render(): View
    {
        $query = Transaction::query()->with(['account', 'category'])->latest('date')->latest('id');

        if ($this->typeFilter === 'transfer') {
            $query->where('type', 'transfer_out');
        } elseif ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->accountFilter !== '') {
            $query->where('account_id', $this->accountFilter);
        }

        if ($this->categoryFilter !== '') {
            $query->where('category_id', $this->categoryFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        if ($this->search !== '') {
            $query->where('description', 'like', "%{$this->search}%");
        }

        $transactions = $query->paginate(15);

        $transferDestinations = collect();
        if ($this->typeFilter === 'transfer') {
            $transferDestinations = Transaction::whereIn('transfer_group_id', $transactions->pluck('transfer_group_id'))
                ->where('type', 'transfer_in')
                ->with('account')
                ->get()
                ->keyBy('transfer_group_id');
        }

        return view('livewire.transactions.index', [
            'transactions' => $transactions,
            'transferDestinations' => $transferDestinations,
            'accounts' => Account::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
