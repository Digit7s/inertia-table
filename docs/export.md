# CSV Export

InertiaTable features a lightweight, backend-driven CSV export mechanism that utilizes Laravel's native streaming capabilities. 

## Features

- **No External Dependencies**: Operates entirely using Laravel's `response()->streamDownload()` and PHP's native `fputcsv()`. Does not require heavy packages like `maatwebsite/excel` or `league/csv`.
- **Query Aware**: Respects the active search, filter, and sort state of the table. 
- **Bulk Export**: Exports all matching filtered records across the entire query result, not just the currently visible paginated page.
- **Opt-in Only**: CSV export is disabled by default and must be explicitly enabled per Table class.

## Enabling Exports

You can define export behavior inside each `{Module}Table.php` class by implementing the `exports()` method. If the method returns an empty array, the UI export button is automatically hidden.

```php
use Digit7s\InertiaTable\Exports\CsvExport;

public function exports(): array
{
    return [
        CsvExport::make('products.csv')
            ->label('Export CSV')
            ->onlyVisibleColumns()
            ->withCurrentFilters()
            ->withCurrentSort(),
    ];
}
```

By default, the table has exports disabled, which operates like this:

```php
public function exports(): array
{
    return [];
}
```

## Disabling Export for Specific Columns

If you have columns dedicated to UI actions (e.g. an Edit or Delete button column), you will likely want to exclude them from the CSV output. You can use the `exportable(false)` method on the column:

```php
Column::make('actions', '')
    ->exportable(false)
```
