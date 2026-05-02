<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class TagsColumn extends Column
{
    protected string $type = 'tags';

    public function labelKey(string $key): static
    {
        return $this->meta('label_key', $key);
    }

    public function badgeClass(string $class): static
    {
        return $this->meta('badge_class', $class);
    }
}
