<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\AnnualReportPdfController;
use App\Http\Controllers\Reports\CustomReportCsvController;
use App\Http\Controllers\Reports\MonthlyReportPdfController;
use App\Http\Controllers\TransactionImportTemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

// TODO(Phase 6): replaced by a real DashboardController.
Route::get('dashboard', fn () => Inertia::render('Dashboard'))
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
    Route::get('finances/transactions', fn () => 'TODO: Phase 6')->name('transactions.index');
    Route::get('finances/transactions/import', fn () => 'TODO: Phase 6')->name('transactions.import');
    Route::get('finances/transactions/import/template', TransactionImportTemplateController::class)->name('transactions.import.template');
    Route::get('finances/incomes', fn () => 'TODO: Phase 6')->name('incomes.index');
    Route::get('finances/expenses', fn () => 'TODO: Phase 6')->name('expenses.index');
    Route::get('finances/transfers', fn () => 'TODO: Phase 6')->name('transfers.index');

    Route::get('accounts', fn () => 'TODO: Phase 6')->name('accounts.index');

    Route::get('planning/budgets', fn () => 'TODO: Phase 6')->name('budgets.index');
    Route::get('planning/goals', fn () => 'TODO: Phase 6')->name('savings-goals.index');
    Route::get('planning/recurring', fn () => 'TODO: Phase 6')->name('recurring-transactions.index');

    Route::get('reports/monthly', fn () => 'TODO: Phase 6')->name('reports.monthly');
    Route::get('reports/monthly/pdf', MonthlyReportPdfController::class)->name('reports.monthly.pdf');

    Route::get('reports/annual', fn () => 'TODO: Phase 6')->name('reports.annual');
    Route::get('reports/annual/pdf', AnnualReportPdfController::class)->name('reports.annual.pdf');

    Route::get('reports/custom', fn () => 'TODO: Phase 6')->name('reports.custom');
    Route::get('reports/custom/csv', CustomReportCsvController::class)->name('reports.custom.csv');

    Route::get('settings/categories', fn () => 'TODO: Phase 6')->name('categories.index');
    Route::get('settings/preferences', fn () => 'TODO: Phase 6')->name('preferences.edit');
    Route::get('settings/export', fn () => Inertia::render('ComingSoon', ['title' => 'Export my data']))->name('data-export.index');
});

require __DIR__.'/auth.php';
