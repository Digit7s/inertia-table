# Searching

InertiaTable provides a built-in search feature that allows users to filter the table data based on a search query.

## Making a Column Searchable

To make a column searchable, you can use the `searchable()` method on the column definition.

```php
use Digit7s\InertiaTable\Column;

Column::make('name')
    ->searchable(),
```

By default, it will search the column's key (in this case, `name`).

## Custom Search Columns

If you want to search a different column in the database than the one displayed, you can pass the column name to `searchable()`:

```php
Column::make('name')
    ->searchable('full_name'),
```

You can also pass an array of columns to search:

```php
Column::make('name')
    ->searchable(['first_name', 'last_name']),
```

## Relation Search

InertiaTable supports searching relationship columns using dot notation:

```php
Column::make('author.name')
    ->searchable('author.name'),
```

## How it Works

When a user types in the search input on the frontend, the `DataTableFilters.vue` component sends a `search` query parameter to the backend. InertiaTable automatically applies a `where` clause to the query with `like "%{$search}%"` for all searchable columns. The query state is preserved in the Inertia URL.
