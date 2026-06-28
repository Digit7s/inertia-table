# Basic Usage

`digit7s/inertia-table` is designed around creating dedicated Table classes for your resources.

## 1. Create a Table Class

A Table class defines the query, columns, filters, and actions for a specific resource.

Create a new class extending `Digit7s\InertiaTable\InertiaTable` (e.g., `app/Tables/UserTable.php`):

```php
<?php

namespace App\Tables;

use App\Models\User;
use Digit7s\InertiaTable\InertiaTable;
use Digit7s\InertiaTable\Column;
use Illuminate\Database\Eloquent\Builder;

class UserTable extends InertiaTable
{
    public function query(): Builder
    {
        return User::query();
    }

    public function columns(): array
    {
        return [
            Column::make('id', 'ID')->sortable(),
            Column::make('name', 'Name')->searchable()->sortable(),
            Column::make('email', 'Email Address')->searchable(),
            Column::make('created_at', 'Joined Date')->sortable(),
        ];
    }
}
```

## 2. Return Table Data from a Controller

Instantiate your table class, passing in the current Request, and call `->toInertia()` to serialize the state for the frontend:

```php
use App\Tables\UserTable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $table = new UserTable($request);

        return Inertia::render('Users/Index', [
            'tableData' => $table->toInertia(),
        ]);
    }
}
```

## 3. Render the Table in Vue

In your Inertia page (e.g., `resources/js/Pages/Users/Index.vue`), import the published `InertiaTable` component and pass the `tableData` prop to it:

```vue
<script setup lang="ts">
import InertiaTable from '@/components/inertia-table/InertiaTable.vue';

defineProps<{
    tableData: any;
}>();
</script>

<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Users</h1>
        
        <InertiaTable :table-data="tableData">
            <!-- Optional: Provide custom cell rendering if needed via slots -->
            <template #cell-created_at="{ item }">
                {{ new Date(item.created_at).toLocaleDateString() }}
            </template>
        </InertiaTable>
    </div>
</template>
```

That's it! Your table will automatically render with sorting, searching, and pagination fully functional, keeping state synchronized via Inertia's URL parameters.
