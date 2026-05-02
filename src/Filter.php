<?php

namespace Digit7s\InertiaTable;

use Illuminate\Database\Eloquent\Builder;

class Filter
{
    protected string $type = 'text';

    protected array $options = [];

    /** @var callable|null */
    protected $queryCallback = null;

    public function __construct(
        public string $key,
        public string $label
    ) {}

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label ?? str($key)->headline()->toString());
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function query(callable $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function apply(Builder $query, mixed $value): void
    {
        if ($this->queryCallback) {
            call_user_func($this->queryCallback, $query, $value, $this);

            return;
        }

        if ($this->type === 'text') {
            $query->where($this->key, 'like', "%{$value}%");
        } elseif ($this->type === 'boolean') {
            $query->where($this->key, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where($this->key, $value);
        }
    }

    public function toArray(mixed $value = null): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'options' => (object) $this->options, // Cast to object so it is serialized as an associative object in JSON
            'value' => $value,
        ];
    }
}
