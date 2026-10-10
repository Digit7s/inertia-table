# Bulk Actions

InertiaTable allows you to define actions that can be performed on multiple selected rows simultaneously.

## Defining Bulk Actions

To add bulk actions to your table, override the `bulkActions()` method in your table class:

```php
use Digit7s\InertiaTable\BulkAction;
use Illuminate\Support\Facades\Gate;

public function bulkActions(): array
{
    return [
        BulkAction::make('delete', 'Delete Selected')
            ->action(function (array $ids, bool $selectAllMatching) {
                foreach (User::query()->whereIn('id', $ids)->get() as $user) {
                    Gate::authorize('delete', $user);
                    $user->delete();
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
2. `bool $selectAllMatching`: Whether the user selected all matching records across all pages (not just the visible ones).

The controller supplies the IDs to process in both modes. For select-all-matching requests, it rebuilds the table's base `query()` (including application scopes), then applies the submitted search and filters before collecting IDs. The existing Vue component sends search in `search` and filters as a JSON string in `filters`; the controller validates and resolves this state. The client-supplied record count is ignored.

For explicit selection, submitted IDs are checked against the table's base query. Stale or out-of-scope IDs reject the whole request. Duplicate IDs are collapsed. Explicit selection is intentionally limited to the records selected by ID; it does not reapply the current search or filters, so selections can remain valid when a user changes table state.

The package rejects empty explicit selections, mixed explicit IDs plus select-all-matching, malformed state, and more than 1,000 explicit IDs. Select-all-matching actions are limited to 10,000 records by default because the callback API receives an array. Override `maxBulkActionRecords()` on a table to set a different server-side limit.

## Authorization contract

The package guarantees that IDs passed to the callback belong to the table query scope. The consuming application owns authorization: put tenant and visibility scopes in `query()`, and authorize each record or the operation inside the callback before mutating data. Do not treat frontend action visibility, table class encryption, or submitted IDs as authorization. Keep the callback limited to the supplied `$ids`; do not replace them with an unscoped `Model::query()->delete()` operation.

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
