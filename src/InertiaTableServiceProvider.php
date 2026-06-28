<?php

namespace Digit7s\InertiaTable;

use Digit7s\InertiaTable\Commands\MakeTableCommand;
use Digit7s\InertiaTable\Http\Controllers\TableActionController;
use Digit7s\InertiaTable\Http\Controllers\TableExportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InertiaTableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeTableCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../resources/js/components' => resource_path('js/components/inertia-table'),
            ], 'inertia-table-components');
        }

        Route::post('/_inertia-table/bulk-action', [TableActionController::class, 'handleBulkAction'])
            ->middleware(['web'])
            ->name('inertia-table.bulk-action');

        Route::get('/_inertia-table/export', [TableExportController::class, 'handleExport'])
            ->middleware(['web'])
            ->name('inertia-table.export');
    }

    public function register(): void
    {
        //
    }
}
