# Grouping

InertiaTable supports grouping table rows by a specific column.

## Making a Column Groupable

To allow users to group the table by a column, use the `groupable()` method:

```php
use Digit7s\InertiaTable\Columns\TextColumn;

TextColumn::make('category')
    ->groupable(),
```

By default, it will group by the column's key (`category`).

## Custom Group Column

If you want to group by a different database column than the one displayed, you can pass the column name to `groupable()`:

```php
TextColumn::make('category')
    ->groupable('category_id'),
```

## Custom Group Value

If you want to customize the value displayed for the group header (e.g., formatting a date or looking up a label), you can use `groupUsing()`:

```php
TextColumn::make('created_at')
    ->groupable()
    ->groupUsing(function ($row) {
        return $row->created_at->format('F Y'); // Group by Month and Year
    }),
```

## How it Works

The frontend sends the grouping column in the `group` query parameter and direction in `group_dir`.
InertiaTable applies an `orderBy` clause for the grouping column to ensure records are sorted together.
On the frontend, the component can use the `_group_value` injected into each item's data to render group headers.
