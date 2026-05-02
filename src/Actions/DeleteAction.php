<?php

namespace Digit7s\InertiaTable\Actions;

use Digit7s\InertiaTable\Action;

class DeleteAction extends Action
{
    protected string $method = 'delete';

    protected bool $requiresConfirmation = true;

    protected ?string $confirmTitle = 'Are you absolutely sure?';

    protected ?string $confirmDescription = 'This action cannot be undone. This will permanently delete this record.';

    public static function make(string $key = 'delete', string $label = 'Delete'): static
    {
        return (new static($key, $label))->icon('Trash');
    }
}
