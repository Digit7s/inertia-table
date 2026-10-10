<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RelationshipTable extends InertiaTable
{
    protected array $columnDefinitions;

    public function __construct(?Request $request = null, array $columns = [])
    {
        parent::__construct($request);
        $this->columnDefinitions = $columns;
    }

    public function query(): Builder
    {
        return User::query();
    }

    public function columns(): array
    {
        return $this->columnDefinitions;
    }
}
