# CSV Export

InertiaTable features a lightweight, backend-driven CSV export mechanism that utilizes Laravel's native streaming capabilities. 

## Features

- **No External Dependencies**: Operates entirely using Laravel's `response()->streamDownload()` and PHP's native `fputcsv()`. Does not require heavy packages like `maatwebsite/excel` or `league/csv`.
- **Query Aware**: Respects the active search, filter, and sort state of the table. 
- **Bulk Export**: Exports all matching filtered records across the entire query result, not just the currently visible paginated page.
- **Opt-in Only**: CSV export is disabled by default and must be explicitly enabled per Table class.
- **Relationship Aware**: Dotted relationship columns use the same Eloquent value resolver as Inertia rows. Collection values are written as comma-and-space-separated scalar values.
- **Bounded Chunks**: Rows are read in batches of 1,000; inferred relationship eager loads are applied to each batch rather than loading the full result set.
- **Formula Neutralization**: Text cells beginning with a spreadsheet formula marker, including after leading Unicode whitespace or control characters, receive a leading apostrophe before CSV quoting.

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

## Authorization and Data Boundaries

The package export route has the `web` middleware only. Encrypting the table class prevents arbitrary class selection; it is not authorization. Protect exports with the same application authentication and route middleware used for the page that exposes the export, and put tenant, visibility, soft-delete, and other confidentiality scopes in the table's `query()` method. Export requests rerun that query and apply the configured search, filters, sorting, and grouping state when enabled.

An individual export can also require an explicit application authorization check:

```php
CsvExport::make('customers.csv')
    ->name('customers')
    ->authorizeUsing(fn (Request $request, CustomersTable $table): bool =>
        $request->user()?->can('export', Customer::class) ?? false
    );
```

The callback receives the request, table instance, and export definition. Return `false` to deny with HTTP 403, or call `Gate::authorize()` to use Laravel's authorization exception flow. If no callback is set, the package does not impose a global policy for backward compatibility; the consuming application must secure the named route or configure the export callback. Do not rely on the encrypted class token or button visibility as an access control.

The request cannot submit export column names or definitions. The server selects the named definition from `exports()` and exports only declared, exportable columns. With `onlyVisibleColumns()` enabled, permanently hidden columns are excluded and the UI's `hidden` list can remove additional columns; client input cannot add columns. Treat column definitions as an explicit data allowlist and mark secrets with `exportable(false)` or keep them out of the table entirely. UI visibility is not a permission system.

Search, filter, and sort inputs are validated against the table's configured searchable columns, filters, and sortable columns. The query method remains the authoritative source for tenant and application scopes. Authorization callbacks and query scopes should be covered by application feature tests.

## CSV Safety and Consumption

CSV is a text interchange format. Quoting a field protects delimiters and newlines, but does not stop spreadsheet applications from evaluating formulas. Before `fputcsv()`, the exporter prefixes text whose first non-whitespace/control character is `=`, `+`, `-`, or `@` with an apostrophe. This includes Unicode separators and control/format characters. Numeric PHP values and numeric text such as `-12.5` or `+2e3` stay numeric. Booleans are `1`/`0`, nulls are empty fields, dates use ISO-like timestamps, and collection values are joined with `, ` before the cell is checked and quoted.

The generated CSV is intended for opening or importing into common spreadsheet applications and preserves risky text as text. The apostrophe is part of the CSV value and may be visible in non-spreadsheet consumers; consumers needing exact original bytes should use a structured format such as JSON rather than bypassing this protection. CSV output always applies neutralization; there is no raw/unsafe export option.

## Large Exports and Relationships

The exporter streams the response and materializes at most 1,000 root models per query batch. Eloquent eager-loads configured dotted relationship paths for each batch, avoiding per-row relation queries while keeping relationship scopes and custom eager-load constraints. Large collection relationships can still materialize many related rows for a single batch; keep exported relationship collections bounded or provide a scalar aggregate column for very large child sets. Offset-based chunks can become more expensive at high offsets and are not a snapshot against concurrent inserts/deletes. For a consistent export, schedule it against stable data or add application-level snapshot behavior.

Flat, singular relationship, and collection columns are supported when represented by configured column keys. Morph relationships that Eloquent can eager-load are resolved by the shared column resolver; deeper `morphTo` paths should be explicitly eager-loaded with application-owned `morphWith()` constraints. Callbacks that read other relationships must eager-load them in `query()`.

## Disabling Export for Specific Columns

If you have columns dedicated to UI actions (e.g. an Edit or Delete button column), you will likely want to exclude them from the CSV output. You can use the `exportable(false)` method on the column:

```php
Column::make('actions', '')
    ->exportable(false)
```
