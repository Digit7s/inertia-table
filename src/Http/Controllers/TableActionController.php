<?php

namespace Digit7s\InertiaTable\Http\Controllers;

use Digit7s\InertiaTable\BulkAction;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TableActionController extends Controller
{
    public function handleBulkAction(Request $request)
    {
        $validated = $request->validate([
            'table_class' => 'required|string',
            'action_key' => 'required|string',
            'ids' => 'sometimes|array|max:1000',
            'ids.*' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_int($value) && ! is_string($value)) {
                    $fail("The {$attribute} must be an integer or string identifier.");

                    return;
                }

                if (is_string($value) && (trim($value) === '' || mb_strlen($value) > 191)) {
                    $fail("The {$attribute} must be a non-empty identifier of at most 191 characters.");
                }
            }],
            'selectAllMatching' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:500',
            'filters' => 'sometimes|string|max:20000',
            'filter' => 'sometimes|array|max:50',
        ]);

        try {
            $tableClass = decrypt($request->table_class);
        } catch (\Exception $e) {
            abort(403, 'Invalid table signature.');
        }

        if (! is_subclass_of($tableClass, InertiaTable::class)) {
            abort(403, 'Invalid table class.');
        }

        /** @var InertiaTable $table */
        $table = new $tableClass($request);

        // Find the matching action
        $bulkAction = collect($table->bulkActions())->first(function (BulkAction $action) use ($validated) {
            return $action->key === $validated['action_key'];
        });

        if (! $bulkAction) {
            abort(404, 'Bulk action not found.');
        }

        // Resolve and validate request state before selecting or mutating any records.
        $table->resolveTableState();

        $ids = $validated['ids'] ?? [];
        $selectAllMatching = $request->boolean('selectAllMatching', false);

        if ($selectAllMatching) {
            if ($ids !== []) {
                abort(422, 'Do not submit explicit IDs with select-all-matching.');
            }

            $query = $table->query();
            $table->applySearch($query);
            $table->applyFiltering($query);

            $primaryKey = $table->getPrimaryKey();
            $matchingCount = (clone $query)->distinct()->count($primaryKey);

            if ($matchingCount > $table->maxBulkActionRecords()) {
                abort(422, 'The matching selection exceeds this table’s bulk-action limit.');
            }

            $ids = (clone $query)->distinct()->pluck($primaryKey)->all();

            if ($ids === []) {
                return back();
            }
        } else {
            if ($ids === []) {
                abort(422, 'Select at least one record or enable select-all-matching.');
            }

            // De-duplicate identifiers while preserving the submitted identifier types/order.
            $uniqueIds = [];
            $seenIds = [];

            foreach ($ids as $id) {
                $normalizedId = (string) $id;

                if (! isset($seenIds[$normalizedId])) {
                    $seenIds[$normalizedId] = true;
                    $uniqueIds[] = $id;
                }
            }

            $primaryKey = $table->getPrimaryKey();
            $inScopeIds = (clone $table->query())
                ->whereIn($primaryKey, $uniqueIds)
                ->distinct()
                ->pluck($primaryKey)
                ->map(fn ($id) => (string) $id)
                ->all();

            if (array_diff(array_map('strval', $uniqueIds), $inScopeIds) !== []) {
                abort(422, 'One or more selected records are stale or outside the table scope.');
            }

            $ids = $uniqueIds;
        }

        $bulkAction->handle($ids, $selectAllMatching);

        return back();
    }
}
