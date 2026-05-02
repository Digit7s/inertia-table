<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\Action;
use Digit7s\InertiaTable\BulkAction;
use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Columns\BooleanColumn;
use Digit7s\InertiaTable\Columns\DateColumn;
use Digit7s\InertiaTable\Columns\TextColumn;
use Digit7s\InertiaTable\Filter;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Database\Eloquent\Builder;

class UsersTable extends InertiaTable
{
    protected ?string $model = User::class;

    public function query(): Builder
    {
        return User::query();
    }

    public function columns(): array
    {
        return [
            Column::make('id')->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable(),
            BooleanColumn::make('is_admin'),
            DateColumn::make('created_at'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('is_admin', 'Admin Status')->type('boolean'),
        ];
    }

    public function bulkActions(): array
    {
        return [
            BulkAction::make('delete', 'Delete')
                ->action(fn ($ids) => User::whereIn('id', $ids)->delete()),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('edit', 'Edit')->url(fn ($user) => "/users/{$user->id}/edit"),
        ];
    }
}
