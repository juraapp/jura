<?php

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
});

// TODO(Phase 5/6): URIs translated to English and re-wired to Inertia controllers.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('finanzas/transacciones', fn () => 'TODO: Phase 6')->name('transactions.index');
    Route::get('finanzas/transacciones/importar', fn () => 'TODO: Phase 6')->name('transactions.import');
    Route::get('finanzas/transacciones/importar/plantilla', TransactionImportTemplateController::class)->name('transactions.import.template');
    Route::get('finanzas/ingresos', fn () => 'TODO: Phase 6')->name('incomes.index');
    Route::get('finanzas/gastos', fn () => 'TODO: Phase 6')->name('expenses.index');
    Route::get('finanzas/transferencias', fn () => 'TODO: Phase 6')->name('transfers.index');

    Route::get('cuentas', fn () => 'TODO: Phase 6')->name('accounts.index');

    Route::get('planificacion/presupuestos', fn () => 'TODO: Phase 6')->name('budgets.index');
    Route::get('planificacion/metas', fn () => 'TODO: Phase 6')->name('savings-goals.index');
    Route::get('planificacion/recurrentes', fn () => 'TODO: Phase 6')->name('recurring-transactions.index');

    Route::get('reportes/mensual', fn () => 'TODO: Phase 6')->name('reports.monthly');
    Route::get('reportes/mensual/pdf', MonthlyReportPdfController::class)->name('reports.monthly.pdf');

    Route::get('reportes/anual', fn () => 'TODO: Phase 6')->name('reports.annual');
    Route::get('reportes/anual/pdf', AnnualReportPdfController::class)->name('reports.annual.pdf');

    Route::get('reportes/personalizado', fn () => 'TODO: Phase 6')->name('reports.custom');
    Route::get('reportes/personalizado/csv', CustomReportCsvController::class)->name('reports.custom.csv');

    Route::get('configuracion/categorias', fn () => 'TODO: Phase 6')->name('categories.index');
    Route::get('configuracion/preferencias', fn () => 'TODO: Phase 6')->name('preferences.edit');
    Route::view('configuracion/exportar', 'coming-soon', ['title' => 'Exportar mis datos'])->name('data-export.index');
});

require __DIR__.'/auth.php';
