<?php

namespace Digit7s\InertiaTable;

class Column
{
    protected string $type = 'text';

    protected bool|\Closure $visible = true;

    protected bool|\Closure $toggleable = true;

    protected bool|\Closure $toggledHiddenByDefault = false;

    protected bool|string|array|\Closure $searchable = false;

    protected bool|\Closure $sortable = false;

    protected bool|string|\Closure $groupable = false;

    protected ?string $component = null;

    protected array $meta = [];

    protected ?\Closure $groupValueCallback = null;

    public function __construct(
        public string $key,
        public string $label
    ) {}

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label ?? str($key)->headline()->toString());
    }

    public function sortable(bool|\Closure $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool|string|array|\Closure $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function visible(bool|\Closure $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function hidden(bool|\Closure $condition = true): static
    {
        $this->visible = is_bool($condition) ? ! $condition : fn () => ! $this->evaluate($condition);

        return $this;
    }

    public function toggleable(bool|\Closure $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function toggledHiddenByDefault(bool|\Closure $condition = true): static
    {
        $this->toggledHiddenByDefault = $condition;

        return $this;
    }

    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    protected function evaluate(mixed $value): mixed
    {
        return $value instanceof \Closure ? $value() : $value;
    }

    public function groupable(bool|string $groupable = true): static
    {
        $this->groupable = $groupable;

        return $this;
    }

    public function groupUsing(\Closure $callback): static
    {
        $this->groupValueCallback = $callback;

        return $this;
    }

    public function meta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function isSortable(): bool
    {
        return (bool) $this->evaluate($this->sortable);
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }

    public function isGroupable(): bool
    {
        return (bool) $this->evaluate($this->groupable);
    }

    public function getGroupColumn(): string
    {
        return is_string($this->groupable) ? $this->groupable : $this->key;
    }

    public function getGroupValue(mixed $row): mixed
    {
        if ($this->groupValueCallback) {
            return ($this->groupValueCallback)($row);
        }

        return null;
    }

    public function getSearchableColumns(): array
    {
        if (is_string($this->searchable)) {
            return [$this->searchable];
        }

        if (is_array($this->searchable)) {
            return $this->searchable;
        }

        return [$this->key];
    }

    public function toArray(): array
    {
        $visible = $this->evaluate($this->visible);

        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => (bool) $this->evaluate($this->sortable),
            'searchable' => $this->evaluate($this->searchable),
            'visible' => $visible,
            'toggleable' => $visible && (bool) $this->evaluate($this->toggleable),
            'toggledHiddenByDefault' => (bool) $this->evaluate($this->toggledHiddenByDefault),
            'groupable' => $this->evaluate($this->groupable),
            'component' => $this->component,
            'meta' => $this->meta,
        ];
    }
}
