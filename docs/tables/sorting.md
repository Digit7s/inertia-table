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

Only configured sortable columns are accepted. Invalid or non-sortable sort keys and invalid directions return a validation error.

Flat columns use the configured model query. Singular dotted relationship columns such as `company.name`, `profile.bio`, and `company.owner.name` are sorted through correlated Eloquent relationship subqueries. This preserves relationship scopes and does not join or duplicate the table's base records. Missing singular relationships sort last in either direction, and the model's primary key is appended as a stable tie-breaker.

The null-last expression is driver-aware: PostgreSQL uses `NULLS LAST`; SQLite and MySQL use a `CASE` expression against the selected sort alias. Built-in text search and text filters normalize ASCII case with `LOWER()` so SQLite, PostgreSQL, and MySQL do not inherit different default `LIKE` case behavior. Unicode case folding and locale-sensitive collation remain database-specific.

Collection relationships (`hasMany`, `belongsToMany`, `morphMany`, and `morphToMany`) do not have one inherent scalar sort value. Sorting them is rejected instead of choosing an arbitrary related row. `morphTo` sorting is also rejected because the target model varies by record. Use an explicitly configured scalar column or a custom application query when your table needs a particular aggregate or polymorphic strategy.
