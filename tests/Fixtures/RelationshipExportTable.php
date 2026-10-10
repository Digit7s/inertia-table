<?php

namespace Tests\Fixtures;

use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Exports\CsvExport;
use Illuminate\Http\Request;

class RelationshipExportTable extends RelationshipTable
{
    public function __construct(?Request $request = null, array $columns = [])
    {
        parent::__construct($request, [
            Column::make('company.name'),
            Column::make('roles.name'),
        ]);
    }

    public function exports(): array
    {
        return [CsvExport::make('relations.csv')->name('csv')];
    }
}
