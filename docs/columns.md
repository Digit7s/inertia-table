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

### Relationship Values

Dotted column keys can traverse valid Eloquent relationship methods. Relationship prefixes are eager-loaded for the table query, while ordinary dotted attribute paths (such as JSON casts) are not treated as relationships unless the prefix is an Eloquent relation.

```php
TextColumn::make('company.name');          // Singular relation: string or null
TextColumn::make('company.owner.name');    // Nested singular relation
TagsColumn::make('roles.name');            // Collection relation: ['Admin', 'Editor']
TextColumn::make('posts.comments.body');    // Nested collections: flattened scalar list
```

Singular missing relations and missing attributes resolve to `null`. Empty collection relations resolve to `[]`. Values use Eloquent attribute access, including casts and accessors. Nested collection values are flattened in the order Eloquent returns related rows; add an `orderBy()` to the relation when a stable order is required. A dotted value is added under the row's `_column_values` map, keyed by the complete column key; existing row fields and flat-column values remain unchanged. Automatically eager-loaded relation objects are not copied into the raw row payload. Existing eager loads declared by the application retain their current serialization behavior.

Custom cell slots continue to receive the row through the `item` slot prop. They may read a dotted resolved value with `item._column_values?.['company.name']`; flat keys remain at their existing positions. Column visibility controls presentation only and does not authorize access to model data.

Legacy slots that read `item.company?.name` continue to work when `company` was already eager-loaded by the application's `query()` and remains in the model's serialized relations. A relationship loaded automatically only to resolve a table column is intentionally removed from the serialized row. Migrate those slots to `_column_values` rather than serializing entire related models, which could expose undeclared attributes.

### Date formatting

`DateColumn::format()` and `DateTimeColumn::format()` accept PHP-style date tokens: `Y`, `y`, `m`, `n`, `d`, `j`, `D`, `l`, `N`, `w`, `F`, `M`, `H`, `G`, `h`, `g`, `i`, `s`, `a`, and `A`. Backslash escapes the next character. For example:

```php
DateColumn::make('created_at')->format('M j, Y');
DateTimeColumn::make('created_at')->format('Y-m-d H:i');
```

Without a format, values use the browser locale. Date-only `YYYY-MM-DD` values are treated as local calendar dates to avoid moving a day across time zones. Datetimes with offsets are parsed as instants and displayed in the browser's local time zone. Null and invalid values render as empty text.

Relationship methods on row-link/action callbacks that are not also declared as relationship columns cannot be inferred. Eager-load those explicitly in the table's existing `query()` method:

```php
public function query(): Builder
{
    return User::query()->with(['profile', 'roles']);
}
```

For nested relations beyond a `MorphTo`, configure the concrete types with Eloquent's `morphWith()` in `query()`; the table can safely eager-load the `MorphTo` itself, but cannot infer its concrete target graph.

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
