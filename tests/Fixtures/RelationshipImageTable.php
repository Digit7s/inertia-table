<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Database\Eloquent\Builder;

class RelationshipImageTable extends InertiaTable
{
    public function query(): Builder
    {
        return Image::query();
    }

    public function columns(): array
    {
        return [Column::make('imageable.name')];
    }
}
