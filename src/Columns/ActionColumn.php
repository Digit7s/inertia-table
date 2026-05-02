<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class ActionColumn extends Column
{
    protected string $type = 'action';

    public function __construct(string $key = 'actions', ?string $label = null)
    {
        parent::__construct($key, $label ?? 'Actions');
        $this->sortable(false);
        $this->searchable(false);
        $this->meta('align', 'right');
    }

    public static function new(string $label = 'Actions'): static
    {
        return new static('actions', $label);
    }
}
