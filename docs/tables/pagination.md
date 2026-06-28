# Pagination

InertiaTable handles pagination of your data automatically.

## Enabling/Disabling Pagination

By default, tables are paginated. You can disable pagination by calling `paginated(false)` or overriding the property:

```php
public function __construct()
{
    parent::__construct();
    $this->paginated(false);
}
```

Or override the property in your class:

```php
protected bool $paginated = false;
```

## Customizing Per Page Options

You can customize the options available for records per page and the default value by overriding the protected properties in your table class:

```php
protected ?array $perPageOptions = [10, 25, 50, 100];

protected ?int $defaultPerPage = 25;
```

## How it Works

When pagination is enabled, InertiaTable looks for the `perPage` query parameter in the request. If not present or not in the allowed options, it falls back to `defaultPerPage`.
It uses Laravel's `paginate()` method on the query and returns the standard pagination meta data and links to the frontend.

## Accessibility

The provided `DataTablePagination.vue` component complies with web accessibility standards:
- All pagination links and buttons have clear, descriptive `aria-label` attributes (e.g., "Go to next page").
- The currently active page button is marked with `aria-current="page"`.
- It uses a semantic `<nav>` region for screen readers.
