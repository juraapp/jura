<?php

namespace App\Livewire;

use App\Enums\CategoryType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Global "New transaction" modal — mounted once in the app layout so it can
 * be opened from the topbar, empty states, or a transaction row's edit
 * action anywhere in the app without re-navigating.
 */
class TransactionForm extends Component
{
    public bool $show = false;

    public string $activeTab = 'expense';

    public ?int $editingId = null;

    public ?int $account_id = null;

    public ?int $category_id = null;

    public ?string $amount = null;

    public string $date = '';

    public ?string $description = null;

    public ?string $notes = null;

    public ?string $payment_method = null;

    public ?int $from_account_id = null;

    public ?int $to_account_id = null;

    #[On('open-transaction-form')]
    public function openCreate(string $type = 'expense', ?int $accountId = null): void
    {
        $this->authorize('create', Transaction::class);

        $this->resetForm();
        $this->activeTab = $type;

        if ($accountId) {
            if ($type === 'transfer') {
                $this->from_account_id = $accountId;
            } else {
                $this->account_id = $accountId;
            }
        }

        $this->show = true;
    }

    #[On('edit-transaction')]
    public function openEdit(int $transactionId): void
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('update', $transaction);

        $this->resetForm();
        $this->editingId = $transaction->id;
        $this->date = $transaction->date->toDateString();
        $this->description = $transaction->description;
        $this->notes = $transaction->notes;

        if ($transaction->type->isTransfer()) {
            $this->activeTab = 'transfer';
            $sibling = Transaction::where('transfer_group_id', $transaction->transfer_group_id)
                ->whereKeyNot($transaction->id)
                ->first();

            $out = $transaction->type === TransactionType::TransferOut ? $transaction : $sibling;
            $in = $transaction->type === TransactionType::TransferIn ? $transaction : $sibling;

            $this->from_account_id = $out?->account_id;
            $this->to_account_id = $in?->account_id;
            $this->amount = (string) $transaction->amount;
        } else {
            $this->activeTab = $transaction->type->value;
            $this->account_id = $transaction->account_id;
            $this->category_id = $transaction->category_id;
            $this->amount = (string) $transaction->amount;
            $this->payment_method = $transaction->payment_method?->value;
        }

        $this->show = true;
    }

    public function updatedActiveTab(): void
    {
        $this->category_id = null;
    }

    public function save(TransactionService $service): void
    {
        if ($this->activeTab === 'transfer') {
            $this->saveTransfer($service);

            return;
        }

        $data = $this->validate($this->simpleRules());

        if ($this->editingId) {
            $transaction = Transaction::findOrFail($this->editingId);
            $this->authorize('update', $transaction);
            $service->updateSimple($transaction, $data);
        } else {
            $this->authorize('create', Transaction::class);
            $type = TransactionType::from($this->activeTab);
            $type === TransactionType::Income
                ? $service->createIncome(auth()->user(), $data)
                : $service->createExpense(auth()->user(), $data);
        }

        $this->finishSave();
    }

    private function saveTransfer(TransactionService $service): void
    {
        $data = $this->validate([
            'from_account_id' => ['required', 'integer', 'different:to_account_id', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->editingId) {
            $transaction = Transaction::findOrFail($this->editingId);
            $this->authorize('update', $transaction);
            $service->updateTransfer($transaction, $data);
        } else {
            $this->authorize('create', Transaction::class);
            $service->createTransfer(auth()->user(), $data);
        }

        $this->finishSave();
    }

    private function finishSave(): void
    {
        $this->show = false;
        $this->dispatch('finances-updated');
        $this->dispatch('toast', type: 'success', message: __('Transaction saved successfully.'));
        $this->resetForm();
    }

    private function simpleRules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'account_id', 'category_id', 'amount', 'description',
            'notes', 'payment_method', 'from_account_id', 'to_account_id',
        ]);
        $this->date = Carbon::today()->toDateString();
    }

    public function accounts()
    {
        return Account::where('is_active', true)->orderBy('name')->get();
    }

    public function categories()
    {
        $type = $this->activeTab === 'income' ? CategoryType::Income : CategoryType::Expense;

        return Category::where('type', $type)->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.transaction-form', [
            'accounts' => $this->accounts(),
            'categories' => $this->activeTab === 'transfer' ? collect() : $this->categories(),
        ]);
    }
}
