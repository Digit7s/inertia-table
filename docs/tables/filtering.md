# Filtering

InertiaTable allows you to define custom filters to narrow down the table data.

## Defining Filters

To add filters to your table, override the `filters()` method in your table class:

```php
use Digit7s\InertiaTable\Filter;

public function filters(): array
{
    return [
        Filter::make('status')
            ->type('select')
            ->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ]),
        Filter::make('is_featured')
            ->type('boolean'),
    ];
}
```

## Filter Types

### Text Filter (Default)

By default, filters are of type `text`. They search the column using a `like` query.

```php
Filter::make('email')
```

### Select Filter

For filters with a set of options, use the `select` type and provide options:

```php
Filter::make('role')
    ->type('select')
    ->options([
        'admin' => 'Admin',
        'user' => 'User',
    ])
```

### Boolean Filter

For boolean fields, use the `boolean` type:

```php
Filter::make('is_verified')
    ->type('boolean')
```

## Custom Filter Queries

If you need to customize how the filter applies to the query, you can use the `query()` method:

```php
Filter::make('created_after')
    ->query(function (Builder $query, $value) {
        $query->where('created_at', '>=', $value);
    })
```

## How it Works

The frontend sends selected filters in the `filter` query parameter as an array: `filter[status]=active`. The filters are preserved in the Inertia URL automatically, meaning page refreshes and direct links keep the filter state intact.

InertiaTable automatically loops through the request filters and applies them to the query using the `apply()` method on the corresponding `Filter` instance.
