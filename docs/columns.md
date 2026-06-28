# Columns

Columns define what data is displayed in your table and how users can interact with it.

You define columns by returning an array of `Digit7s\InertiaTable\Column` instances from the `columns()` method in your Table class.

## Basic Definition

The `Column::make()` method accepts two arguments:
1. `key`: The attribute name from your model.
2. `label` (optional): The human-readable column heading. If omitted, it defaults to a headline-cased version of the key.

```php
use Digit7s\InertiaTable\Column;

public function columns(): array
{
    return [
        Column::make('id', 'ID'),
        Column::make('name'), // Automatically labels as "Name"
        Column::make('email_address', 'Email'),
    ];
}
```

## Supported Column Behaviors

You can fluently chain methods to customize column behavior.

### Sortable Columns

Allow users to sort the table by this column. Clicking the column header will toggle ascending/descending order.

```php
Column::make('created_at')->sortable()
```

### Searchable Columns

Include this column when the user types in the global search input.

```php
Column::make('name')->searchable()
```
*Note: You can pass specific database columns to search across relationships if needed, e.g., `searchable(['users.first_name', 'users.last_name'])`.*

### Toggleable / Hidden Columns

Allow users to hide/show this column via the "View Options" dropdown in the UI.

```php
// The column is visible by default, but users can hide it:
Column::make('email')->toggleable()

// The column is hidden by default, but users can show it:
Column::make('deleted_at')->toggledHiddenByDefault()
```

### Action / Non-Exportable Columns

Some columns are for UI purposes only (e.g., an actions column with edit/delete buttons). You can mark them as non-exportable so they are excluded from CSV exports.

```php
Column::make('actions', '')
    ->exportable(false)
```

## Safe Defaults

By default, columns are:
- `visible`: `true`
- `sortable`: `false`
- `searchable`: `false`
- `toggleable`: `true`
- `exportable`: `true`

## Custom Rendering (Frontend)

The package relies on the Vue template to render the cell contents. By default, it outputs the raw text value of the model attribute. 

To render custom HTML, badges, or buttons, you can use dynamic slots in `InertiaTable.vue` based on the column's `key`:

```vue
<InertiaTable :table-data="tableData">
    <template #cell-status="{ item }">
        <span class="px-2 py-1 rounded bg-green-100 text-green-800" v-if="item.status === 'active'">
            Active
        </span>
    </template>
</InertiaTable>
```
