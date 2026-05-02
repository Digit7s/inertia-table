<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class BooleanColumn extends Column
{
    protected string $type = 'boolean';

    public function trueLabel(string $label): static
    {
        return $this->meta('true_label', $label);
    }

    public function falseLabel(string $label): static
    {
        return $this->meta('false_label', $label);
    }
}
