<?php

namespace Digit7s\InertiaTable\Exports;

use Closure;
use Digit7s\InertiaTable\InertiaTable;
use Illuminate\Http\Request;

/** @phpstan-consistent-constructor */
class CsvExport
{
    public string $name = 'csv';

    public string $label = 'Export CSV';

    public string $filename = 'export.csv';

    public bool $onlyVisibleColumns = true;

    public bool $withCurrentFilters = true;

    public bool $withCurrentSort = true;

    /** @var Closure(Request, InertiaTable, CsvExport): bool|null */
    protected ?Closure $authorization = null;

    public static function make(?string $filename = null): static
    {
        $export = new static;

        if ($filename !== null) {
            $export->filename($filename);
        }

        return $export;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function filename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function onlyVisibleColumns(bool $value = true): static
    {
        $this->onlyVisibleColumns = $value;

        return $this;
    }

    public function withCurrentFilters(bool $value = true): static
    {
        $this->withCurrentFilters = $value;

        return $this;
    }

    public function withCurrentSort(bool $value = true): static
    {
        $this->withCurrentSort = $value;

        return $this;
    }

    /**
     * Require an application-defined authorization check before this export runs.
     * The callback should return true when allowed; it may also call Gate::authorize().
     */
    public function authorizeUsing(Closure $callback): static
    {
        $this->authorization = $callback;

        return $this;
    }

    public function isAuthorized(Request $request, InertiaTable $table): bool
    {
        return $this->authorization === null || (bool) ($this->authorization)($request, $table, $this);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'filename' => $this->filename,
        ];
    }
}
