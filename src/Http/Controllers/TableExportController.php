<?php

namespace Digit7s\InertiaTable\Http\Controllers;

use Digit7s\InertiaTable\Exports\CsvExport;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TableExportController extends Controller
{
    public function handleExport(Request $request)
    {
        $request->validate([
            'table_class' => 'required|string',
            'export_name' => 'required|string',
            'hidden' => 'nullable|string|max:10000',
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

        abort_unless($export->isAuthorized($request, $table), 403, 'This export is not authorized.');

        $columns = collect($table->columns())
            ->filter(function ($column) use ($export) {
                $configuration = $column->toArray();

                return $configuration['type'] !== 'action'
                    && $column->isExportable()
                    && (! $export->onlyVisibleColumns || $configuration['visible']);
            });

        if ($export->onlyVisibleColumns) {
            $hiddenColumns = $request->has('hidden') ? explode(',', $request->hidden) : [];
            $columns = $columns->filter(function ($column) use ($hiddenColumns) {
                // If it was explicitly hidden by the user in the UI
                if (in_array($column->key, $hiddenColumns)) {
                    return false;
                }

                // The UI sends its current hidden-column state, including columns
                // hidden by default or hidden later by the user.
                return true;
            });
        }

        $headers = $columns->pluck('label')->toArray();
        $keys = $columns->pluck('key')->toArray();

        $query = $table->query();
        $table->eagerLoadColumnRelationships($query, $columns->all());

        if ($export->withCurrentFilters) {
            $table->applySearch($query);
            $table->applyFiltering($query);
        }

        if ($export->withCurrentSort) {
            $table->applySorting($query);
            $table->applyGrouping($query);
        }

        return response()->streamDownload(function () use ($headers, $keys, $query, $table) {
            $handle = fopen('php://output', 'w');

            try {
                // Add UTF-8 BOM for common spreadsheet applications.
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, array_map(fn ($header) => $this->safeCsvText((string) $header), $headers), ',', '"', '');

                // Fetch in chunks to bound root-row memory while retaining Eloquent eager loads.
                $query->chunk(1000, function ($rows) use ($handle, $keys, $table) {
                    foreach ($rows as $row) {
                        if (connection_aborted()) {
                            return false;
                        }

                        $csvRow = [];
                        foreach ($keys as $key) {
                            $value = $table->resolveColumnValue($row, $key);
                            $csvRow[] = $this->csvCell($value);
                        }
                        fputcsv($handle, $csvRow, ',', '"', '');
                    }
                });
            } finally {
                fclose($handle);
            }
        }, $export->filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\\TH:i:s.uP');
        }

        if ($value instanceof \BackedEnum) {
            return $this->csvCell($value->value);
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $value = implode(', ', array_map(fn ($item) => $this->collectionScalar($item), $value));
            } else {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return $this->safeCsvText((string) $value);
        }

        return '';
    }

    private function collectionScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\\TH:i:s.uP');
        }

        if ($value instanceof \BackedEnum) {
            return $this->collectionScalar($value->value);
        }

        if (is_array($value) || $value instanceof Arrayable || $value instanceof \JsonSerializable) {
            return (string) (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /** Prefix risky text with an apostrophe; CSV quoting by itself is not formula-safe. */
    private function safeCsvText(string $value): string
    {
        if ($value === '' || is_numeric($value) && preg_match('/\\A[\\p{Z}\\p{C}]/u', $value) !== 1) {
            return $value;
        }

        // Spreadsheet parsers commonly ignore leading whitespace and control characters
        // before formula markers. Include Unicode separators, format, and control chars.
        if (preg_match('/\\A[\\p{Z}\\p{C}]*[=+\\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
