<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class NumericColumn extends Column
{
    protected string $type = 'numeric';

    public function __construct(string $key, string $label)
    {
        parent::__construct($key, $label);

        // Numerics look best aligned to the right by default
        $this->meta('align', 'right');
    }

    public function align(string $alignment): static
    {
        return $this->meta('align', $alignment);
    }
}
