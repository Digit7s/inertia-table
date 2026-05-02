<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class DateColumn extends Column
{
    protected string $type = 'date';

    public function format(string $format): static
    {
        return $this->meta('format', $format);
    }
}
