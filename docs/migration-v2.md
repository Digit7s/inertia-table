# Migrating to v2.0.0

## Framework requirements

Inertia Table v2.0.0 supports Laravel 12 and 13. Laravel 12 requires PHP 8.2 or newer; Laravel 13 requires PHP 8.3 or newer. The supported pairings are Inertia Laravel 2 with Laravel 12, and Inertia Laravel 3 with Laravel 13. Vue 3 is required for the published components.

Laravel 10 and 11 are outside the v2 support policy. They are associated with the earlier package release line, but that association does not mean those Laravel versions continue to receive security updates. Upgrade the application to Laravel 12 or 13 before requiring `digit7s/inertia-table:^2.0`.

## Consumer changes

### Custom slots for relationship columns

If a slot relied on the package's automatic eager loading to serialize a dotted relationship onto the row, read the resolved value from `_column_values`:

```vue
<!-- Before: only reliable when the application query serialized company -->
<template #cell-company.name="{ item }">{{ item.company?.name }}</template>

<!-- v2 -->
<template #cell-company.name="{ item }">{{ item._column_values?.['company.name'] }}</template>
```

Flat column values keep their existing row positions. Relationships explicitly eager-loaded by the application's own query continue to serialize as Laravel normally does. The new map avoids adding whole automatically loaded related models and their unrelated attributes to the Inertia payload.

### Bulk actions

Bulk-action IDs are now checked against the table's scoped query. Out-of-scope or stale explicit IDs reject the request, duplicate IDs are collapsed, and the client-supplied count is ignored. Select-all-matching requests rebuild the scoped query and apply submitted search and filters; the package limits how many matching IDs are materialized. Review callbacks that independently query or mutate records: they should use only the supplied IDs and perform the application's authorization checks before mutation. No database migration is required.

### CSV exports and authorization

Text cells beginning with spreadsheet formula characters are prefixed with an apostrophe before CSV quoting. Numeric values remain numeric. Affected text may display the apostrophe in non-spreadsheet consumers; applications that depend on byte-for-byte raw CSV values should account for this behavior.

The export route uses the `web` middleware. Configure route authentication/authorization or an export-specific `authorizeUsing()` callback, and keep tenant, visibility, soft-delete, and confidentiality scopes in the table's `query()`. The encrypted table identifier and hidden UI controls are not authorization. Review any application that exposed exports without route protection or a policy callback.

### Search, filtering, sorting, and metadata

Search and text-filter matching now normalizes ASCII case across SQLite, PostgreSQL, and MySQL, relationship-aware table and export state is applied consistently, and pagination uses a stable tie order. Search input is validated (string, at most 500 characters); malformed or oversized state receives a validation error. Unicode case folding and locale-specific matching remain database dependent.

The frontend `searchable` metadata is now a boolean; configured search fields remain server-side. Consumers reading table metadata directly should not expect the previous search-field representation. Date and datetime format metadata is respected by the Vue component, with date-only, null, and invalid values handled safely.

Republish and rebuild the Vue components after upgrading so the consuming app uses the v2 payload and metadata contract:

```bash
php artisan vendor:publish --tag="inertia-table-components" --force
npm run build
```

Review local customizations before using `--force`; preserve and reapply app-specific edits. There are no package database migrations.
