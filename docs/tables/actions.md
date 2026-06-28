# Actions

InertiaTable allows you to define actions for individual rows in the table.

## Defining Actions

To add actions to your table, override the `actions()` method in your table class:

```php
use Digit7s\InertiaTable\Action;

public function actions(): array
{
    return [
        Action::make('edit', 'Edit')
            ->url(fn ($model) => route('users.edit', $model))
            ->icon('heroicon-o-pencil'),
            
        Action::make('delete', 'Delete')
            ->url(fn ($model) => route('users.destroy', $model))
            ->method('delete')
            ->requiresConfirmation()
            ->confirmTitle('Delete User')
            ->confirmDescription('Are you sure you want to delete this user?'),
    ];
}
```

## Action Properties

### URL

The `url()` method takes a closure that receives the current row's model instance. You should return the URL for the action.

```php
->url(fn ($model) => route('users.show', $model))
```

### Icon

You can specify an icon for the action using the `icon()` method. The value depends on what your frontend component supports.

```php
->icon('pencil')
```

### Method

By default, actions use the `GET` method. You can change this to `POST`, `PUT`, `PATCH`, or `DELETE` using the `method()` method. Non-GET methods will be sent via Inertia's router.

```php
->method('post')
```

### Confirmation & Loading State

For destructive actions, you can require user confirmation before proceeding.
The UI natively prevents duplicate submissions while the request is in flight by disabling the confirmation button.

```php
->requiresConfirmation()
->confirmTitle('Are you sure?')
->confirmDescription('This action cannot be undone.')
```

## How it Works

InertiaTable evaluates the URL for each action per row and injects the evaluated actions into the row data under the `_actions` key. The frontend component can then render these actions as buttons or menu items.
