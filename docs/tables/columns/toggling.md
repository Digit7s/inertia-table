# Toggling Columns

InertiaTable allows users to manage the visibility of columns at runtime. This feature is inspired by FilamentPHP and provides a "View" dropdown where users can toggle columns.

## Allowing Users to Manage Columns

To make a column toggleable, you can use the `toggleable()` method. By default, columns are toggleable unless explicitly disabled.

```php
use Digit7s\InertiaTable\Columns\TextColumn;

TextColumn::make('email')
    ->toggleable(),
```

If you want to prevent a column from being toggled (forcing it to always be visible if it is not hidden), you can pass `false`:

```php
TextColumn::make('id')
    ->toggleable(false),
```

## Hiding Columns by Default

Sometimes you may want a column to be available in the "View" menu but hidden when the table is first loaded. You can use the `toggledHiddenByDefault()` method for this:

```php
TextColumn::make('created_at')
    ->toggledHiddenByDefault(),
```

## Permanently Hiding Columns

If you want to hide a column completely based on a condition (e.g., user permissions), you can use the `hidden()` or `visible()` methods.

- `hidden()`: Hides the column from the table and removes it from the "View" menu.
- `visible()`: Shows the column based on a condition.

Both methods accept a boolean or a Closure:

```php
TextColumn::make('secret_token')
    ->hidden(! auth()->user()->isAdmin()),

TextColumn::make('management_actions')
    ->visible(fn () => auth()->user()->can('manage_items')),
```

## Persistent State

Column visibility preferences are automatically saved to the user's browser `localStorage`. 

### Unique Identifiers
To prevent visibility states from colliding between different tables, InertiaTable uses the `table_class` (the encrypted name of your Table class) as a unique key. This ensure that:
1. Different tables on the same page maintain separate states.
2. The same table across different pages maintains the same state.

## Complete Example

```php
public function columns(): array
{
    return [
        TextColumn::make('name')
            ->searchable()
            ->sortable(),

        TextColumn::make('email')
            ->toggleable(false), // User cannot hide the email

        TextColumn::make('phone')
            ->toggledHiddenByDefault(), // Hidden initially, but can be shown

        TextColumn::make('internal_id')
            ->hidden(fn () => ! auth()->user()->isStaff()), // Completely hidden for non-staff
    ];
}
```
