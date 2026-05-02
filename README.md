# Inertia Table

A backend-driven Inertia.js Datatable for Laravel and Vue 3. This package allows you to define your table structure, querying, sorting, and searching in PHP, while providing a beautiful, headless-ready Vue component to render it.

## Features

- 🚀 **Backend-driven**: Define columns, sorting, searching, and filtering in PHP.
- 🔍 **Searching**: Integrated search across multiple columns.
- 🎛️ **Filtering**: Backend-driven dynamic filters (supports `text`, `select`, `boolean` inputs).
- 🔃 **Sorting**: Built-in support for column-based sorting.
- 📄 **Pagination**: Seamless integration with Laravel pagination (can be toggled on/off).
- 🎨 **Vue 3 + Tailwind CSS**: Modern UI built with Lucide icons and Shadcn/UI inspired components.
- 🧩 **Slots**: Full flexibility to customize cell rendering via Vue slots.

## Installation

Since this is a local package, you can install it via Composer by adding a path repository to your `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/digit7s/*"
    }
],
"require-dev": {
    "digit7s/inertia-table": "@dev"
}
```

Then run:

```bash
composer update digit7s/inertia-table
```

## Backend Usage

### 1. Run artisan command
```bash
# Basic usage (Uses App\Models\User and creates UsersTable)
php artisan make:inertia-table User

# Explicit name and model
php artisan make:inertia-table PostTable Post

# If you skip 'Table' suffix, it's auto-added
php artisan make:inertia-table Post # creates app/Tables/PostTable.php
```

### 2. Create a Table Class

Extend `Digit7s\InertiaTable\AbstractTable` and implement the `query` and `columns` methods.

```php
namespace App\Tables;

use App\Models\User;
use Digit7s\InertiaTable\AbstractTable;
use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Filter;
use Illuminate\Database\Eloquent\Builder;

class UsersTable extends AbstractTable
{
    protected ?string $model = User::class;

    public function query(): Builder
    {
        return User::query();
    }

    public function columns(): array
    {
        return [
            Column::make('id', 'ID')->sortable(),
            Column::make('name')->searchable()->sortable(),
            Column::make('email')->searchable(),
            Column::make('roles.name', 'Role')->searchable(), // Nested relation dot-notation
            Column::make('created_at', 'Joined')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('role', 'User Role')
                ->type('select')
                ->options(['admin' => 'Admin', 'user' => 'User'])
                ->query(function (Builder $query, $value) {
                    // Custom relationship filtering
                    $query->whereHas('roles', function (Builder $query) use ($value) {
                        $query->where('name', $value);
                    });
                }),
            Filter::make('is_active', 'Active Only')
                ->type('boolean'),
        ];
    }
}
```

### 3. Use in Controller

Instantiate your table class and use the `toInertia()` method to pass data to your Inertia page.

```php
use App\Tables\UsersTable;
use Inertia\Inertia;

public function index()
{
    return Inertia::render('Users/Index', [
        'users' => (new UsersTable())->toInertia(),
    ]);
}
```

## Frontend Usage

### 1. Basic Implementation

Import the `InertiaTable` component and pass the `tableData` prop.

```vue
<script setup lang="ts">
import InertiaTable from '@/components/InertiaTable.vue';

defineProps<{
    users: any;
}>();
</script>

<template>
    <InertiaTable :tableData="users" />
</template>
```

### 2. Customizing Cells

Use slots to customize specific columns. The slot name follows the pattern `cell-{column_key}`.

```vue
<template>
    <InertiaTable :tableData="users">
        <!-- Customize the 'name' column -->
        <template #cell-name="{ item }">
            <div class="font-bold">{{ item.name }}</div>
            <div class="text-xs text-muted-foreground">{{ item.email }}</div>
        </template>

        <!-- Add an actions column -->
        <template #cell-actions="{ item }">
            <button @click="edit(item)">Edit</button>
        </template>
    </InertiaTable>
</template>
```

## Row Actions & Links

The package natively supports adding interactive clicking behaviors and `shadcn-vue` dropdown menus to your rows!

### Clicking entire rows (`rowLink`)
Add `rowLink()` to your `query()` method. The table will automatically handle stopping event propagation on sub-elements, transforming your rows precisely to become clickable, receiving Shadcn `cursor-pointer hover:bg-muted/50` visual queues out-of-the-box.
```php
public function query(): Builder
{
    $this->rowLink(fn (User $user) => route('users.edit', $user->id));

    return User::query();
}
```

### Row Actions Dropdown
You can append an automatic Actions dropdown to the right side of every row.

1. Inject the placeholder **ActionColumn** structurally into your table `columns()` array:
```php
use Digit7s\InertiaTable\Columns\ActionColumn;

public function columns(): array
{
    return [
        Column::make('name'),
        ActionColumn::new(),
    ];
}
```
2. Define the actual dropdown items in the `actions()` method mapping against Lucide strings using the core `Action` builder:
```php
use Digit7s\InertiaTable\Action;

public function actions(): array
{
    return [
        Action::make('edit', 'Edit')
            ->icon('Pencil')
            ->url(fn (User $user) => route('users.edit', $user->id)),
            
        Action::make('delete', 'Delete')
            ->icon('Trash')
            ->url(fn (User $user) => route('users.destroy', $user->id)),
    ];
    ];
}
```

### Bulk Actions
You can define actions that execute against multiple sequentially selected rows simultaneously. When you define `bulkActions()`, the datatable automatically injects a frontend Checkbox column onto the far left of the table and spawns a floating Action Bar at the base of the screen when rows are actively selected.

1. Implement the `bulkActions()` method on your Table class using the `BulkAction` builder:
```php
use Digit7s\InertiaTable\BulkAction;

public function bulkActions(): array
{
    return [
        BulkAction::make('export', 'Export Users')
            ->icon('Download')
            ->method('post')
            ->url(route('users.export')),
            
        BulkAction::make('delete', 'Delete Selected')
            ->icon('Trash')
            ->method('delete')
            ->requiresConfirmation()
            ->confirmTitle('Delete Selected Users?')
            ->confirmDescription('Are you absolutely sure you want to delete these users?')
            ->url(route('users.bulk-delete')),
    ];
}
```
**Important:** Your defined `url` endpoint will receive a `$request->input('ids')` parameter array holding the targeted row `id`s triggered by the frontend.

## Grouping Records

The datatable supports organizing records into clusters based on specific columns (e.g. Grouping Permissions by Resource or Employees by Department).

1. **Enable grouping in your Table class:**
```php
Column::make('status')->groupable();

// Or group by a specific database column (useful for relations)
Column::make('department.name')->groupable('department_id');
```

2. **Custom Group Values:** You can provide a closure to define how the group labels are generated. This is useful for extracting categories from strings.
```php
TextColumn::make('name')
    ->groupable()
    ->groupUsing(fn ($row) => explode(' ', $row->name)[1] ?? 'Other');
```

3. **Default Grouping:** You can set a default group that is applied automatically when the table loads.
```php
protected ?string $defaultGroup = 'name';
protected string $defaultGroupDir = 'asc';
```

4. **Backend Querying:** When a group is active, the `AbstractTable` automatically injects an `orderBy` clause for that group column *before* any other sorting. This ensures records are correctly clustered in the database result.

## Column Types

The package provides several specific column types tailored for different data representations. They all extend the base `Column` class, meaning you can chain standard methods like `sortable()` and `searchable()`.

### TextColumn
The default column used for string values.
```php
use Digit7s\InertiaTable\Columns\TextColumn;

TextColumn::make('name', 'Full Name')->sortable();
```

### NumericColumn
Automatically right-aligns data. Perfect for money, quantities, or IDs.
```php
use Digit7s\InertiaTable\Columns\NumericColumn;

NumericColumn::make('amount')->sortable();
```

### BadgeColumn
Renders shadcn-vue badges. You can map explicit cell values to badge variants (`default`, `secondary`, `destructive`, `outline`, etc.).
```php
use Digit7s\InertiaTable\Columns\BadgeColumn;

BadgeColumn::make('status')
    ->variant([
        'active' => 'default',
        'pending' => 'secondary',
        'banned' => 'destructive'
    ]);
```

### BooleanColumn
Displays Lucide `Check` and `X` icons matching true/false. Optionally attach display labels.
```php
use Digit7s\InertiaTable\Columns\BooleanColumn;

BooleanColumn::make('is_admin')
    ->trueLabel('Admin')
    ->falseLabel('User');
```

### DateColumn & DateTimeColumn
Parses timestamps locally onto the client.
```php
use Digit7s\InertiaTable\Columns\DateColumn;
use Digit7s\InertiaTable\Columns\DateTimeColumn;

DateColumn::make('created_at', 'Joined');
DateTimeColumn::make('updated_at', 'Last Modified');
```

### ImageColumn
Displays an image from a URL field.
```php
use Digit7s\InertiaTable\Columns\ImageColumn;

ImageColumn::make('avatar_url')->circular();
```

### ActionColumn
A non-sortable, right-aligned placeholder wrapper specifically for injecting the `#actions` slot on the row.
```php
use Digit7s\InertiaTable\Columns\ActionColumn;

ActionColumn::new('Manage Options');
```
*Tip: On your Vue template, you can customize actions easily over this column by dropping your content inside `<template #actions="{ item }">` instead of matching the full cell-key.*

## Column Configuration

The `Column` interface provides several base methods:

- `make(string $key, ?string $label = null)`: Create a new column. Supports dot notation for simple relation column resolutions (`e.g. roles.name`).
- `sortable(bool $sortable = true)`: Enable or disable sorting for this column.
- `searchable(bool|string|array $searchable = true)`: Include this column in the global search. **Supports relationships automatically** via dot notation.
- `visible(bool $visible = true)`: Determines if this column is shown by default when the table explicitly loads.
- `toggleable(bool $toggleable = true)`: Enables the user to actively turn the visibility of this column on or off using the View Settings dropdown.
- `component(string $component)`: (Optional) Specify a custom component for this column.

## Filter Configuration

The `Filter` class allows you to define constraints available to the user. The InertiaTable component dynamically renders the corresponding `shadcn-vue` input based on the provided type.

- `Filter::make(string $key, ?string $label = null)`: Create a new filter.
- `type(string $type)`: Set the filter type (`text`, `select`, or `boolean`). Defaults to `text`.
- `options(array $options)`: Used when the type is `select` to provide a key-value array of options.
- `query(callable $callback)`: Custom closure to execute specific query scopes (perfect for Relationship filters or complex SQL states).

## Pagination Configuration

By default, the table uses Laravel's standard pagination.

- `paginated(bool $paginated = true)`: Enable or disable pagination. If set to `false`, the table will load all records and hide pagination controls in the UI.
- `perPageOptions(array $options)`: Set the available rows-per-page options.
- `defaultPerPage(int $perPage)`: Set the default rows-per-page value.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
