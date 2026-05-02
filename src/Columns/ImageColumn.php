<?php

namespace Digit7s\InertiaTable\Columns;

use Digit7s\InertiaTable\Column;

class ImageColumn extends Column
{
    protected string $type = 'image';

    public function __construct(string $key, ?string $label = null)
    {
        parent::__construct($key, $label ?? str($key)->headline()->toString());
        $this->sortable(false);
        $this->searchable(false);
    }

    public function defaultUrl(string $url): static
    {
        return $this->meta('default_url', $url);
    }

    public function circular(): static
    {
        return $this->meta('circular', true);
    }

    public function rounded(): static
    {
        return $this->meta('rounded', true);
    }

    public function size(string $sizeClass): static
    {
        return $this->meta('size_class', $sizeClass);
    }

    public function small(): static
    {
        return $this->size('size-4');
    }

    public function medium(): static
    {
        return $this->size('size-6');
    }

    public function large(): static
    {
        return $this->size('size-8');
    }

    public function extraLarge(): static
    {
        return $this->size('size-10');
    }

    public function width(int|string $width): static
    {
        return $this->meta('width', "{$width}px");
    }

    public function height(int|string $height): static
    {
        return $this->meta('height', "{$height}px");
    }

    public function dimensions(int|string $width, int|string $height): static
    {
        $this->width($width);
        $this->height($height);

        return $this;
    }

    public function class(string $class): static
    {
        return $this->meta('class', $class);
    }

    public function alt(string $alt): static
    {
        return $this->meta('alt', $alt);
    }

    public function title(string $title): static
    {
        return $this->meta('title', $title);
    }
}
