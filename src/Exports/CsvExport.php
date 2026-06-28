<?php

namespace Digit7s\InertiaTable\Exports;

class CsvExport
{
    public string $name = 'csv';
    public string $label = 'Export CSV';
    public string $filename = 'export.csv';
    public bool $onlyVisibleColumns = true;
    public bool $withCurrentFilters = true;
    public bool $withCurrentSort = true;

    public static function make(?string $filename = null): static
    {
        $export = new static();
        
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

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'filename' => $this->filename,
        ];
    }
}
