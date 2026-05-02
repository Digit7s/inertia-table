<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class BadgeColumn extends Column
{
    protected string $type = 'badge';

    /**
     * Map exact values to specific badge variants (e.g. success, danger, warning)
     */
    public function variant(array $variants): static
    {
        return $this->meta('variants', $variants);
    }

    /**
     * Map exact values to specific lucide-vue-next icon components
     */
    public function icon(array $icons): static
    {
        return $this->meta('icons', $icons);
    }
}
