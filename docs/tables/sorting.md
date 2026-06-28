# Sorting

InertiaTable allows users to sort the table data by specific columns.

## Making a Column Sortable

To make a column sortable, you can use the `sortable()` method on the column definition.

```php
use Digit7s\InertiaTable\Column;

Column::make('name')
    ->sortable(),
```

## How it Works

When a user clicks on a sortable column header, the frontend sends `sort` and `dir` query parameters to the backend.
- `sort`: The key of the column to sort by.
- `dir`: The direction of the sort (`asc` or `desc`).

InertiaTable automatically applies an `orderBy` clause to the query.

```php
$query->orderBy($sort, $dir === 'desc' ? 'desc' : 'asc');
```

Only columns that are explicitly marked as sortable can be sorted. If a user tries to sort by a non-sortable column, the request is ignored.
