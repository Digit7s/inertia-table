<?php

use Digit7s\InertiaTable\Column;

it('serializes configured searchable columns as a boolean UI capability', function () {
    $column = Column::make('label')->searchable(['users.first_name', 'users.last_name']);

    expect($column->toArray()['searchable'])->toBeTrue()
        ->and($column->getSearchableColumns())->toBe(['users.first_name', 'users.last_name']);
});
