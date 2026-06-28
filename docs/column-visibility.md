# Column Visibility

InertiaTable includes built-in column visibility toggling, allowing users to select which columns to show or hide in their view. 

## Toggleable Columns

By default, all columns are visible and toggleable. Users can access a "View Options" dropdown button in the table toolbar to check/uncheck visible columns.

If you want to prevent a column from being hidden (e.g. the primary ID or main title column), use the `toggleable(false)` method:

```php
use Digit7s\InertiaTable\Column;

Column::make('id')->toggleable(false)
```

## Hidden by Default

Sometimes you have columns that you want to be available to the user but hidden by default on initial page load (e.g. an obscure timestamp or metadata field). You can use the `toggledHiddenByDefault()` method:

```php
Column::make('deleted_at', 'Deleted At')
    ->toggledHiddenByDefault()
```

## How it Works (UI & Mobile)

On the frontend, the `DataTableViewOptions.vue` component provides a dropdown menu containing checkboxes for all toggleable columns.

Column visibility is primarily a client-side layout feature to improve user experience on smaller screens or dense data grids.

- Visibility toggles are NOT persisted to the backend query state.
- Columns marked as hidden by default will be stripped from the initial DOM render to improve performance.
- Mobile behaviors and responsive grids will rely on the user toggling off irrelevant columns for a better experience.
- The UI controls provide semantic `aria-labels` and dropdown roles for accessibility.
