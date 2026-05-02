# Inertia Table

[![Latest Version on Packagist](https://img.shields.io/packagist/v/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![Total Downloads](https://img.shields.io/packagist/dt/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![PHP Version Compliance](https://img.shields.io/packagist/php-v/digit7s/inertia-table.svg?style=flat-square)](https://packagist.org/packages/digit7s/inertia-table)
[![License](https://img.shields.io/packagist/l/digit7s/inertia-table.svg?style=flat-square)](LICENSE.md)

A backend-driven, premium Datatable for **Laravel**, **Inertia.js**, and **Vue 3**. Define your table structure, querying, sorting, and searching entirely in PHP, and render it with a beautiful, headless-ready Vue component.

---

## ✨ Features

- 🚀 **Backend-driven**: Manage columns, sorting, searching, and filtering from your PHP classes.
- 🔍 **Global Search**: Integrated search across multiple columns (including relationships).
- 🎛️ **Dynamic Filters**: Support for `text`, `select`, and `boolean` filters out-of-the-box.
- 🔃 **Smart Sorting**: Built-in column-based sorting with multi-direction support.
- 📄 **Pagination**: Seamlessly integrates with Laravel's native paginator.
- 🎨 **Modern UI**: Built for **Tailwind CSS** and **Shadcn/UI**, using **Lucide Icons**.
- 🧩 **Flexible Customization**: Full control over cell rendering via Vue slots.
- 📦 **Bulk Actions**: Built-in support for multi-row operations with a floating action bar.
- 📁 **Grouping**: Organize records into expandable clusters based on any column.

---

## 🛠 Prerequisites

This package assumes you are using:
- **Laravel 10, 11, or 12**
- **Inertia.js (Vue 3)**
- **Tailwind CSS**
- **Shadcn-vue** (The components expect `ui/table`, `ui/button`, `ui/input`, `ui/badge`, `ui/select`, and `ui/dropdown-menu` to exist in `@/components/ui`)
- **Lucide Vue Next** icons

---

## 🚀 Installation

You can install the package via composer:

```bash
composer require digit7s/inertia-table
```

### Publish Vue Components

Since the table components are built with Vue and Tailwind, you need to publish them to your project's `resources/js` directory:

```bash
php artisan vendor:publish --tag="inertia-table-components"
```

This will place the components in `resources/js/components/inertia-table/`.

---

## 📖 Basic Usage

### 1. Generate a Table Class

Use the artisan command to create a new table class:

```bash
php artisan make:inertia-table UsersTable User
```

This will create `app/Tables/UsersTable.php`.

### 2. Configure the Table

Define your query and columns in the generated class:

```php
namespace App\Tables;

use App\Models\User;
use Digit7s\InertiaTable\InertiaTable;
use Digit7s\InertiaTable\Column;
use Digit7s\InertiaTable\Columns\TextColumn;
use Digit7s\InertiaTable\Columns\DateColumn;
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

### 3. Return from Controller

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

### 4. Render in Vue

```vue
<script setup lang="ts">
import InertiaTable from '@/components/inertia-table/InertiaTable.vue';

defineProps<{
    users: any;
}>();
</script>

<template>
    <InertiaTable :tableData="users" />
</template>
```

---

## 💎 Advanced Features

### Column Types

The package provides specialized column types for better data presentation:

- **`TextColumn`**: Default text representation.
- **`NumericColumn`**: Right-aligned for numbers and currency.
- **`BadgeColumn`**: Renders colorful badges based on values.
- **`BooleanColumn`**: Renders Check/X icons for boolean values.
- **`DateColumn` / `DateTimeColumn`**: Formats dates on the client side.
- **`ImageColumn`**: Renders circular or rounded images from URLs.
- **`TagsColumn`**: Renders an array of values as a cluster of badges.

```php
use Digit7s\InertiaTable\Columns\TagsColumn;

TagsColumn::make('tags')
    ->labelKey('name') // If tags are objects
    ->badgeClass('bg-blue-500 text-white');
```

### Row Actions & Links

#### Clickable Rows
Make the entire row a link:
```php
public function query(): Builder
{
    $this->rowLink(fn (User $user) => route('users.show', $user->id));
    return User::query();
}
```

#### Row Actions Dropdown
Add an actions dropdown to the right side of every row:
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

### Bulk Actions

Enable multi-row operations with a floating action bar:

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

### Grouping

Organize records into clusters:

```php
Column::make('status')->groupable();

// Or set a default group
protected ?string $defaultGroup = 'status';
```

---

## 🎨 Customizing Cells

You can override any cell's rendering using Vue slots:

```vue
<template>
    <InertiaTable :tableData="users">
        <!-- Customize specific cell -->
        <template #cell-name="{ item }">
            <div class="flex flex-col">
                <span class="font-bold">{{ item.name }}</span>
                <span class="text-xs text-gray-500">{{ item.email }}</span>
            </div>
        </template>

        <!-- Customize action buttons manually -->
        <template #cell-actions="{ item }">
            <Button variant="outline" size="sm" @click="edit(item)">Edit</Button>
        </template>
    </InertiaTable>
</template>
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
