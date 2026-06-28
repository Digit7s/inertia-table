# Bulk Actions

InertiaTable allows you to define actions that can be performed on multiple selected rows simultaneously.

## Defining Bulk Actions

To add bulk actions to your table, override the `bulkActions()` method in your table class:

```php
use Digit7s\InertiaTable\BulkAction;

public function bulkActions(): array
{
    return [
        BulkAction::make('delete', 'Delete Selected')
            ->action(function (array $ids, bool $selectAllMatching) {
                if ($selectAllMatching) {
                    // Delete all matching records
                    User::query()->delete(); // You should apply the table's current filters here if needed
                } else {
                    // Delete specific IDs
                    User::whereIn('id', $ids)->delete();
                }
            })
            ->icon('trash')
            ->requiresConfirmation()
            ->confirmTitle('Delete Selected Users')
            ->confirmDescription('Are you sure you want to delete the selected users?'),
    ];
}
```

## Bulk Action Callback

The `action()` method takes a closure that receives two arguments:
1. `array $ids`: An array of selected row IDs.
2. `bool $selectAllMatching`: A boolean indicating if the user selected all matching records across all pages (not just the visible ones).

## Confirmation & Loading State

Similar to row actions, you can require confirmation for bulk actions. 
The UI natively prevents duplicate submissions while the request is in flight by disabling the confirmation button.

```php
->requiresConfirmation()
->confirmTitle('Are you sure?')
->confirmDescription('This will delete all selected items.')
```

## How it Works

Bulk actions are exposed to the frontend in the `bulk_actions` key of the response. The `DataTableBulkActionBar.vue` frontend component renders a floating bar when items are selected. It emits a POST request containing the selected `ids` and a `selectAllMatching` boolean.
