<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Exports\CsvExport;
use Digit7s\InertiaTable\Filter;
use Illuminate\Database\Eloquent\Builder;

class ExportBenchmarkTable extends RelationshipTable
{
    public function query(): Builder
    {
        return User::query()->whereBetween('id', [
            (int) $this->request->query('min_id', 1),
            (int) $this->request->query('max_id', PHP_INT_MAX),
        ]);
    }

    public function columns(): array
    {
        return match ($this->request->query('shape')) {
            'singular' => [Column::make('company.name')],
            'collection' => [Column::make('roles.name')],
            'mixed' => [
                Column::make('name')->searchable(),
                Column::make('company.name')->sortable(),
            ],
            default => [Column::make('name')],
        };
    }

    public function filters(): array
    {
        return [Filter::make('is_admin')->type('boolean')];
    }

    public function exports(): array
    {
        return [CsvExport::make('benchmark.csv')->name('csv')];
    }
}
