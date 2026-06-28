<?php

namespace Digit7s\InertiaTable;

class BulkAction
{
    protected ?string $icon = null;

    protected ?string $variant = null;

    protected ?\Closure $actionCallback = null;

    protected bool $requiresConfirmation = false;

    protected ?string $confirmTitle = null;

    protected ?string $confirmDescription = null;

    public function __construct(
        public string $key,
        public string $label,
    ) {}

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function action(\Closure $callback): static
    {
        $this->actionCallback = $callback;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function handle(array $ids, bool $selectAllMatching): mixed
    {
        if ($this->actionCallback) {
            return call_user_func($this->actionCallback, $ids, $selectAllMatching);
        }

        return null;
    }

    public function requiresConfirmation(bool $requires = true): static
    {
        $this->requiresConfirmation = $requires;

        return $this;
    }

    public function confirmTitle(string $title): static
    {
        $this->confirmTitle = $title;

        return $this;
    }

    public function confirmDescription(string $description): static
    {
        $this->confirmDescription = $description;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'requires_confirmation' => $this->requiresConfirmation,
            'confirm_title' => $this->confirmTitle,
            'confirm_description' => $this->confirmDescription,
        ];
    }
}
