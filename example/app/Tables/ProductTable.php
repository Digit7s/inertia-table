<?php

namespace App\Tables;

use App\Models\Product;
use Digit7s\InertiaTable\InertiaTable;
use Digit7s\InertiaTable\Columns\TextColumn;
use Digit7s\InertiaTable\Columns\NumericColumn;
use Digit7s\InertiaTable\Columns\BooleanColumn;
use Digit7s\InertiaTable\Columns\DateColumn;
use Digit7s\InertiaTable\Columns\BadgeColumn;
use Digit7s\InertiaTable\Filter;
use Digit7s\InertiaTable\BulkAction;
use Digit7s\InertiaTable\Exports\CsvExport;
use Illuminate\Database\Eloquent\Builder;

class ProductTable extends InertiaTable
{
    public function query(): Builder
    {
        return Product::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Product Name')
                ->sortable()
                ->searchable(),
                
            TextColumn::make('sku', 'SKU')
                ->sortable()
                ->searchable(),

            TextColumn::make('category', 'Category')
                ->sortable()
                ->groupable(),

            BadgeColumn::make('status', 'Status')
                ->sortable()
                ->groupable()
                ->variant([
                    'published' => 'default',
                    'draft' => 'secondary',
                    'archived' => 'outline',
                ]),

            NumericColumn::make('price', 'Price')
                ->sortable(),

            NumericColumn::make('stock', 'Stock')
                ->sortable(),

            BooleanColumn::make('is_active', 'Active')
                ->sortable(),

            DateColumn::make('created_at', 'Created At')
                ->sortable()
                ->format('M j, Y'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('category')
                ->type('select')
                ->options([
                    'Electronics' => 'Electronics',
                    'Clothing' => 'Clothing',
                    'Home' => 'Home',
                    'Toys' => 'Toys',
                    'Sports' => 'Sports',
                ]),
                
            Filter::make('status')
                ->type('select')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),

            Filter::make('is_active', 'Active Status')
                ->type('boolean'),
        ];
    }
    
    public function rowActions(): array
    {
        return [
            Action::make('view', 'View')
                ->icon('Eye')
                ->url(fn (Product $product) => '#'),

            Action::make('edit', 'Edit')
                ->icon('Pencil')
                ->url(fn (Product $product) => '#'),

            Action::make('delete', 'Delete')
                ->icon('Trash')
                ->method('delete')
                ->url(fn (Product $product) => '#')
                ->requiresConfirmation(),
        ];
    }

    public function bulkActions(): array
    {
        return [
            BulkAction::make('mark_active', 'Mark Active')
                ->icon('CheckCircle'),

            BulkAction::make('mark_inactive', 'Mark Inactive')
                ->icon('XCircle'),

            BulkAction::make('delete', 'Delete')
                ->icon('Trash')
                ->variant('destructive')
                ->requiresConfirmation(),
        ];
    }

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
}
