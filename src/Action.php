<?php

namespace Digit7s\InertiaTable;

class Action
{
    protected ?\Closure $urlCallback = null;

    protected ?string $icon = null;

    protected string $method = 'get';

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

    public function url(\Closure $callback): static
    {
        $this->urlCallback = $callback;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtolower($method);

        return $this;
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

    public function getUrl(mixed $model): ?string
    {
        if ($this->urlCallback) {
            return call_user_func($this->urlCallback, $model);
        }

        return null;
    }

    public function toArray(mixed $model): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->getUrl($model),
            'icon' => $this->icon,
            'method' => $this->method,
            'requires_confirmation' => $this->requiresConfirmation,
            'confirm_title' => $this->confirmTitle,
            'confirm_description' => $this->confirmDescription,
        ];
    }
}
