# Row Links

InertiaTable allows you to make table rows clickable by defining a link for each row.

## Defining Row Links

To add a link to rows, use the `rowLink()` method in your table class:

```php
$this->rowLink(function ($model) {
    return route('users.show', $model);
});
```

The closure receives the current row's model instance and should return the URL.

## How it Works

InertiaTable evaluates the callback for each row and adds the URL to the row data under the `_row_link` key. The frontend component can use this to make the entire row clickable.
