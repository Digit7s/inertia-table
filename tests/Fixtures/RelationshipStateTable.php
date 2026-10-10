<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\BulkAction;
use Digit7s\InertiaTable\Columns\TextColumn;
use Digit7s\InertiaTable\Exports\CsvExport;
use Digit7s\InertiaTable\Filter;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Database\Eloquent\Builder;

class RelationshipStateTable extends InertiaTable
{
    public function query(): Builder
    {
        return User::query()->where('is_admin', false);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('company.name'),
        ];
    }

    public function filters(): array
    {
        return [Filter::make('company.name')];
    }

    public function bulkActions(): array
    {
        return [BulkAction::make('delete', 'Delete')->action(fn (array $ids) => User::whereIn('id', $ids)->delete())];
    }

    public function exports(): array
    {
        return [CsvExport::make('relationship-state.csv')->name('csv')];
    }
}
