<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\Reports\AnnualReportPdfController;
use App\Http\Controllers\Reports\CustomReportCsvController;
use App\Http\Controllers\Reports\MonthlyReportPdfController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\TransactionImportTemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// TODO(Phase 6): re-wired to real Inertia controllers.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('finances/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('finances/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('finances/transactions/form-options', [TransactionController::class, 'formOptions'])->name('transactions.form-options');
    Route::get('finances/transactions/{transaction}/edit-data', [TransactionController::class, 'editData'])->name('transactions.edit-data');
    Route::patch('finances/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('finances/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::get('finances/transactions/import', [TransactionImportController::class, 'create'])->name('transactions.import');
    Route::post('finances/transactions/import/parse', [TransactionImportController::class, 'parse'])->name('transactions.import.parse');
    Route::post('finances/transactions/import', [TransactionImportController::class, 'store'])->name('transactions.import.store');
    Route::get('finances/transactions/import/template', TransactionImportTemplateController::class)->name('transactions.import.template');
    Route::get('finances/incomes', [TransactionController::class, 'index'])->defaults('type', 'income')->name('incomes.index');
    Route::get('finances/expenses', [TransactionController::class, 'index'])->defaults('type', 'expense')->name('expenses.index');
    Route::get('finances/transfers', [TransactionController::class, 'index'])->defaults('type', 'transfer')->name('transfers.index');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('planning/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('planning/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::patch('planning/budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::delete('planning/budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
    Route::get('planning/goals', [SavingsGoalController::class, 'index'])->name('savings-goals.index');
    Route::post('planning/goals', [SavingsGoalController::class, 'store'])->name('savings-goals.store');
    Route::patch('planning/goals/{savingsGoal}', [SavingsGoalController::class, 'update'])->name('savings-goals.update');
    Route::delete('planning/goals/{savingsGoal}', [SavingsGoalController::class, 'destroy'])->name('savings-goals.destroy');
    Route::post('planning/goals/{savingsGoal}/contributions', [SavingsGoalController::class, 'contribute'])->name('savings-goals.contribute');
    Route::get('planning/recurring', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
    Route::post('planning/recurring', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
    Route::patch('planning/recurring/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
    Route::patch('planning/recurring/{recurringTransaction}/toggle', [RecurringTransactionController::class, 'toggleActive'])->name('recurring-transactions.toggle');
    Route::delete('planning/recurring/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');

    Route::get('reports/monthly', fn () => 'TODO: Phase 6')->name('reports.monthly');
    Route::get('reports/monthly/pdf', MonthlyReportPdfController::class)->name('reports.monthly.pdf');

    Route::get('reports/annual', fn () => 'TODO: Phase 6')->name('reports.annual');
    Route::get('reports/annual/pdf', AnnualReportPdfController::class)->name('reports.annual.pdf');

    Route::get('reports/custom', fn () => 'TODO: Phase 6')->name('reports.custom');
    Route::get('reports/custom/csv', CustomReportCsvController::class)->name('reports.custom.csv');

    Route::get('settings/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('settings/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('settings/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('settings/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('settings/preferences', fn () => 'TODO: Phase 6')->name('preferences.edit');
    Route::get('settings/export', fn () => Inertia::render('ComingSoon', ['title' => 'Export my data']))->name('data-export.index');
});

require __DIR__.'/auth.php';
