# Inertia Table

[![Latest Version on Packagist](https://img.shields.io/packagist/v/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![Total Downloads](https://img.shields.io/packagist/dt/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![PHP Version Compliance](https://img.shields.io/packagist/php-v/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![License](https://img.shields.io/packagist/l/digit7s/inertia-table.svg?style=flat-square)](LICENSE.md)

A backend-driven datatable package for **Laravel**, **Inertia.js**, and **Vue 3**.

Define your table structure, query, searching, sorting, filtering, row actions, bulk actions, and grouping in PHP, then render the result with published Vue 3 components in your Laravel/Inertia application.

---

## ✨ Features

* 🚀 **Backend-driven tables**: Manage columns, sorting, searching, filtering, actions, and pagination from PHP.
* 🔍 **Global search**: Search across multiple columns, including relationship fields.
* 🎛️ **Dynamic filters**: Supports `text`, `select`, and `boolean` filters.
* 🔃 **Smart sorting**: Built-in column-based sorting with direction handling.
* 📄 **Pagination**: Integrates with Laravel's native paginator.
* 🎨 **Modern Vue UI**: Published Vue 3 components built for Tailwind CSS and shadcn-vue.
* 🧩 **Custom cell rendering**: Override cell rendering using Vue slots.
* 📦 **Bulk actions**: Built-in multi-row actions with confirmation support.
* 📁 **Grouping**: Organize rows into expandable groups by column.
* 🧱 **Publishable frontend components**: Publish and customize the Vue table components inside your own app.

---

## ✅ Version Support

| Package Version | PHP    | Laravel                        | Inertia Laravel | Vue.js | Tailwind CSS | shadcn-vue | Status |
| --------------- | ------ | ------------------------------ | --------------- | ------ | ------------ | ---------- | ------ |
| `1.x`           | `^8.2` | `10.x`, `11.x`, `12.x`, `13.x` | `^1.0`, `^2.0`, `^3.0` | `3.x`  | `3.x`, `4.x` | Compatible | Active |


Version support is based on the Composer constraints and tested compatibility for each package release.

---

## 🛠 Requirements

Please refer to the [Version Support](#-version-support) table for PHP, Laravel, Inertia Laravel, and Vue.js compatibility.

This package has two parts:

1. A Laravel/PHP backend table builder.
2. Publishable Vue 3 frontend components for your Laravel/Inertia app.

### Backend Requirements

* PHP `8.2+`
* Laravel `10.x`, `11.x`, or `12.x`
* Inertia Laravel `1.x` or `2.x`

### Frontend Requirements

The published Vue components expect your Laravel/Inertia app to already have the following frontend stack installed and configured:

* Vue 3
* `@inertiajs/vue3`
* Tailwind CSS
* shadcn-vue
* `lucide-vue-next`

### Required shadcn-vue Components

The published components import shadcn-vue components from your app's `@/components/ui` directory.

Make sure these components exist in your consuming Laravel app:

* `badge`
* `button`
* `checkbox`
* `dialog`
* `dropdown-menu`
* `input`
* `select`
* `table`

Depending on the features you use, additional shadcn-vue components may be required in future versions.

> **Note:** shadcn-vue components are generated into your application. They are not normal npm package imports. This package expects those components to exist in the consuming Laravel app after publishing the table components.

---

## 🚀 Installation

Install the package via Composer:

```bash
composer require digit7s/inertia-table
```

### Publish Vue Components

Since the table UI is built with Vue 3 and Tailwind CSS, publish the components into your Laravel app:

```bash
php artisan vendor:publish --tag="inertia-table-components"
```

This will publish the components to:

```txt
resources/js/components/inertia-table/
```

After publishing, you may customize the Vue components to match your application's design system.

---

## 📖 Basic Usage

### 1. Generate a Table Class

Use the Artisan command to create a new table class:

```bash
php artisan make:inertia-table UsersTable User
```

This will create:

```txt
app/Tables/UsersTable.php
```

---

### 2. Configure the Table

Define your query and columns in the generated table class:

```php
namespace App\Tables;

use App\Models\User;
use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Columns\DateColumn;
use Digit7s\InertiaTable\Columns\TextColumn;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Database\Eloquent\Builder;

class UsersTable extends InertiaTable
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
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('roles.name', 'Role')->searchable(),
            DateColumn::make('created_at', 'Joined')->sortable(),
        ];
    }
}
```

---

### 3. Return the Table from a Controller

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

---

### 4. Render the Table in Vue

```vue
<script setup lang="ts">
import InertiaTable from '@/components/inertia-table/InertiaTable.vue';

defineProps<{
    users: Record<string, unknown>;
}>();
</script>

<template>
    <InertiaTable :table-data="users" />
</template>
```

> If your component currently expects `tableData` instead of `table-data`, both are the same Vue prop in templates because Vue supports kebab-case usage for camelCase props.

---

## 💎 Advanced Features

### Column Types

The package provides specialized column types for common table data:

* **`TextColumn`**: Default text representation.
* **`NumericColumn`**: Right-aligned number/currency display.
* **`BadgeColumn`**: Displays values as badges.
* **`BooleanColumn`**: Displays boolean values with visual indicators.
* **`DateColumn` / `DateTimeColumn`**: Formats date and datetime values.
* **`ImageColumn`**: Displays image/avatar-style values.
* **`TagsColumn`**: Displays arrays as badge groups.

Example:

```php
use Digit7s\InertiaTable\Columns\TagsColumn;

TagsColumn::make('tags')
    ->labelKey('name')
    ->badgeClass('bg-blue-500 text-white');
```

---

## 🔗 Row Actions & Links

### Clickable Rows

Make the entire row clickable:

```php
public function query(): Builder
{
    $this->rowLink(fn (User $user) => route('users.show', $user->id));

    return User::query();
}
```

### Row Actions Dropdown

Add row-level actions to your table:

```php
use Digit7s\InertiaTable\Action;
use Digit7s\InertiaTable\Columns\ActionColumn;

public function columns(): array
{
    return [
        TextColumn::make('name'),
        ActionColumn::new(),
    ];
}

public function actions(): array
{
    return [
        Action::make('edit', 'Edit')
            ->icon('Pencil')
            ->url(fn (User $user) => route('users.edit', $user->id)),

        Action::make('delete', 'Delete')
            ->icon('Trash')
            ->method('delete')
            ->requiresConfirmation()
            ->url(fn (User $user) => route('users.destroy', $user->id)),
    ];
}
```

---

## 📦 Bulk Actions

Enable multi-row operations with a floating bulk action bar:

```php
use Digit7s\InertiaTable\BulkAction;

public function bulkActions(): array
{
    return [
        BulkAction::make('delete', 'Delete Selected')
            ->icon('Trash')
            ->requiresConfirmation()
            ->action(function (array $ids) {
                User::whereIn('id', $ids)->delete();
            }),
    ];
}
```

## CSV Export

CSV export is disabled by default.

Enable it in your table class:

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

The package uses Laravel `streamDownload()` and PHP `fputcsv()`.
No external export dependency is required.

## 📁 Grouping

Organize records into expandable groups:

```php
Column::make('status')->groupable();
```

You may also define a default group:

```php
protected ?string $defaultGroup = 'status';
```

---

## 🎨 Customizing Cells

You can override any cell's rendering using Vue slots.

```vue
<template>
    <InertiaTable :table-data="users">
        <template #cell-name="{ item }">
            <div class="flex flex-col">
                <span class="font-bold">{{ item.name }}</span>
                <span class="text-xs text-gray-500">{{ item.email }}</span>
            </div>
        </template>

        <template #cell-actions="{ item }">
            <Button variant="outline" size="sm" @click="edit(item)">
                Edit
            </Button>
        </template>
    </InertiaTable>
</template>
```

Slot names follow this format:

```txt
cell-{column-key}
```

For example:

| Column Key | Slot Name      |
| ---------- | -------------- |
| `name`     | `cell-name`    |
| `email`    | `cell-email`   |
| `status`   | `cell-status`  |
| `actions`  | `cell-actions` |

---

## 🧩 Frontend Customization

After publishing the components, you can customize them directly in your app:

```txt
resources/js/components/inertia-table/
```

Common customization points:

* Table layout
* Cell styling
* Empty state
* Pagination UI
* Filter UI
* Row action dropdown
* Bulk action bar
* Confirmation dialog
* Column visibility menu
* Responsive behavior

Because the components are published into your application, you are free to adapt them to your design system.

---

## 📚 Documentation

For detailed information on each feature, see the documentation inside the `docs/` folder:

* [Documentation Index](docs/index.md)
* [Changelog](CHANGELOG.md)
* [Installation](docs/installation.md)
* [Usage](docs/usage.md)
* [CSV Export](docs/export.md)
* [Accessibility](docs/accessibility.md)
* [Example App](docs/example.md)
* [Troubleshooting](docs/troubleshooting.md)

---

## 🧪 Testing

If you are contributing to the package, run the test suite:

```bash
composer test
```

If no test script is configured, run Pest directly:

```bash
vendor/bin/pest
```

You may also run Laravel Pint if it is installed:

```bash
vendor/bin/pint
```

---

## 🧭 Roadmap

See the [Roadmap](docs/roadmap.md) for planned and potential future improvements.

---

## 🤝 Contributing

Contributions are welcome.

Before submitting a pull request:

1. Keep changes focused.
2. Follow the existing code style.
3. Add or update tests when possible.
4. Update documentation when behavior changes.
5. Confirm the package still works in a Laravel/Inertia/Vue app.

---

## 🔒 Security

If you discover a security vulnerability, please report it privately instead of opening a public issue.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
