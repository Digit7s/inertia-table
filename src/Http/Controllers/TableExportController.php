<?php

namespace Digit7s\InertiaTable\Http\Controllers;

use Digit7s\InertiaTable\Exports\CsvExport;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TableExportController extends Controller
{
    public function handleExport(Request $request)
    {
        $request->validate([
            'table_class' => 'required|string',
            'export_name' => 'required|string',
            'hidden' => 'nullable|string',
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

        // Find the matching export definition
        $export = collect($table->exports())->first(function (CsvExport $e) use ($request) {
            return $e->name === $request->export_name;
        });

        if (! $export) {
            abort(404, 'Export action not found.');
        }

        $query = $table->query();

        if ($export->withCurrentFilters) {
            $table->applySearch($query);
            $table->applyFiltering($query);
        }

        if ($export->withCurrentSort) {
            $table->applySorting($query);
            $table->applyGrouping($query);
        }

        $columns = collect($table->columns())
            ->filter(function ($column) {
                return $column->toArray()['type'] !== 'action' && $column->isExportable();
            });

        if ($export->onlyVisibleColumns) {
            $hiddenColumns = $request->has('hidden') ? explode(',', $request->hidden) : [];
            $columns = $columns->filter(function ($column) use ($hiddenColumns) {
                // If it was explicitly hidden by the user in the UI
                if (in_array($column->key, $hiddenColumns)) {
                    return false;
                }
                
                // If it is toggledHiddenByDefault and not explicitly made visible
                // Wait, it's easier to just trust the hidden array if it's synced. 
                // Let's just use the frontend's hidden columns.
                return true;
            });
        }

        $headers = $columns->pluck('label')->toArray();
        $keys = $columns->pluck('key')->toArray();

        return response()->streamDownload(function () use ($headers, $keys, $query) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            // Fetch in chunks to save memory
            $query->chunk(1000, function ($rows) use ($handle, $keys) {
                foreach ($rows as $row) {
                    $csvRow = [];
                    foreach ($keys as $key) {
                        $csvRow[] = data_get($row, $key, '');
                    }
                    fputcsv($handle, $csvRow);
                }
            });

            fclose($handle);
        }, $export->filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
