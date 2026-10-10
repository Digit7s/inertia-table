<?php

namespace Digit7s\InertiaTable;

use Illuminate\Database\Eloquent\Builder;

/** @phpstan-consistent-constructor */
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

    public function hasQueryCallback(): bool
    {
        return $this->queryCallback !== null;
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

        $this->applyValue($query, $this->key, $value);
    }

    /** Apply the built-in filter operator to a validated field name. */
    public function applyValue(Builder $query, string $field, mixed $value): void
    {
        if ($this->queryCallback) {
            throw new \LogicException('Custom filter callbacks must be applied through apply().');
        }

        if ($this->type === 'text') {
            $wrappedField = $query->getQuery()->getGrammar()->wrap($field);
            $query->whereRaw('LOWER('.$wrappedField.') LIKE ?', ['%'.mb_strtolower((string) $value).'%']);
        } elseif ($this->type === 'boolean') {
            $query->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where($field, $value);
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
