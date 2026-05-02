<?php

namespace Digit7s\InertiaTable\Http\Controllers;

use Digit7s\InertiaTable\AbstractTable;
use Digit7s\InertiaTable\BulkAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TableActionController extends Controller
{
    public function handleBulkAction(Request $request)
    {
        $request->validate([
            'table_class' => 'required|string',
            'action_key' => 'required|string',
            'ids' => 'array',
            'selectAllMatching' => 'boolean',
        ]);

        try {
            $tableClass = decrypt($request->table_class);
        } catch (\Exception $e) {
            abort(403, 'Invalid table signature.');
        }

        if (! is_subclass_of($tableClass, AbstractTable::class)) {
            abort(403, 'Invalid table class.');
        }

        /** @var AbstractTable $table */
        $table = new $tableClass($request);

        // Find the matching action
        $bulkAction = collect($table->bulkActions())->first(function (BulkAction $action) use ($request) {
            return $action->key === $request->action_key;
        });

        if (! $bulkAction) {
            abort(404, 'Bulk action not found.');
        }

        $ids = $request->ids ?? [];
        $selectAllMatching = $request->boolean('selectAllMatching', false);

        if ($selectAllMatching) {
            $query = $table->query();

            // Re-apply the scope context inherently mapping the frontend state
            $table->applySearch($query);
            $table->applyFiltering($query);

            // Pluck all records matching the bounds globally
            $ids = $query->pluck($table->getPrimaryKey())->toArray();
        }

        // Execute natively over the mapped pipeline
        $bulkAction->handle($ids, $selectAllMatching);

        return back();
    }
}
