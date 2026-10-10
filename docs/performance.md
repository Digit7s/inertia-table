# Performance & Package Philosophy

The architecture of `digit7s/inertia-table` is designed to be lightweight, un-opinionated where possible, and strictly backend-driven.

## Backend-Driven

All data processing, sorting, filtering, and pagination occurs securely on the Laravel backend. The Vue frontend acts exclusively as a presentational layer synchronized to the backend query state through Inertia's URL parameters. This ensures that large datasets do not consume excessive memory in the user's browser.

## Native Streaming Export

The CSV export feature leverages Laravel's native `response()->streamDownload()`. This directly flushes the CSV buffer to the browser progressively without loading the entire result set into memory on the server.

- We intentionally avoided heavy dependencies such as `maatwebsite/excel` or `league/csv`.
- Queue-based exports (e.g. background job dispatching with a UI download center) are not part of the current core version, as they introduce unnecessary complexity and infrastructure requirements for basic use cases.

Exports use offset-based `chunk(1000)` queries so requested sort order and the configured Eloquent query are retained. This bounds root model memory and eager-loads relationships per batch, but deep offsets may cost more as the export advances. Query count grows by one root query per batch, plus one eager-load query per loaded relationship (and related morph type); collection size within an individual batch can still be large. The exporter does not provide a database snapshot across concurrent writes. Use a queued or snapshot export in the application when very large exports, long-running consistency, or client-disconnect recovery are requirements.

The repository's opt-in SQLite benchmark (`RUN_EXPORT_BENCHMARKS=1 vendor/bin/pest tests/Feature/ExportPerformanceTest.php`) measures 1k, 10k, and 100k rows for flat, singular, collection, and mixed search/filter/sort exports. It records query count separately from runtime and PHP peak memory. Results vary by PHP/database version and data shape; query count alone does not describe correlated subquery cost.

## Relationship Columns and Callbacks

Valid relationship prefixes used by dotted column keys are eager-loaded before paginated rows are fetched. Nested relationship columns are eager-loaded by path, while the consuming table's existing `with()` constraints remain in place. If a query selects only specific model columns, the package adds the model and relationship matching keys needed by Eloquent; injected foreign keys are omitted from the serialized row.

Callbacks are application code and cannot be inspected safely. If a row link, action, accessor, or other callback reads relations that are not also represented by dotted columns, eager-load those relations in `query()` with `with()`. For example:

```php
public function query(): Builder
{
    return User::query()->with(['profile', 'roles']);
}
```

The package does not enable lazy-loading prevention globally. Apps can enable it in tests to catch callback or accessor relationship access that was not eager-loaded.

## Database Behavior and Indexing

The query behavior is covered against SQLite, PostgreSQL, and MySQL. Built-in text search and text filters use quoted `LOWER(column) LIKE ?` predicates for consistent ASCII case-insensitive matching; the leading `%` pattern is not served efficiently by a normal B-tree index. Database collation and Unicode case-folding can still vary, so applications with locale-sensitive search requirements should use a database-specific search strategy.

Singular relationship sorting is a correlated scalar subquery with a stable primary-key tie-breaker. PostgreSQL uses `NULLS LAST`; SQLite and MySQL use a `CASE WHEN alias IS NULL THEN 1 ELSE 0 END` ordering expression. Missing singular relations therefore sort last in either direction. Relationship filters use `EXISTS`/`whereHas`, which avoids duplicate parent rows and preserves model global scopes.

For eager-loaded collections, index child foreign keys used by generated `WHERE ... IN (...)` queries, such as `posts(user_id)`, pivot `role_user(user_id, role_id)`, or polymorphic `(imageable_type, imageable_id)` pairs. Confirm each recommendation with `EXPLAIN` on representative data. `LOWER(column) LIKE '%term%'` generally needs a search-specific index or full-text strategy to improve large-table substring search; a normal index on the original column is not sufficient. The package does not create indexes in consuming applications.

On the local fixture databases, relationship-sort `EXPLAIN` showed a full scan of the root table plus a correlated company lookup using the company primary key; PostgreSQL also sorts the root rows for the requested related value, and MySQL labels the company lookup as a dependent subquery. Relationship filtering used `EXISTS`; PostgreSQL chose a hash join and sequential scans on the tiny fixture, while MySQL used a nested loop and primary-key lookups. SQLite's plan used rowid seeks for company rows. These plans are scale-dependent, not proof of production cost. A separate 100k-row temporary child-table experiment changed the related-row lookup from table scan to indexed lookup on SQLite and PostgreSQL, and from table scan to covering index range scan on MySQL after adding `user_id`; this supports indexing collection foreign keys where application data volume warrants it.

## Consuming App Responsibilities

Performance concerns at the page or application level are inherently outside the scope of the package:
- **Cache Headers:** The package does not attach HTTP cache policies to the Inertia page response. This should be configured at the application's route middleware or web server level.
- **Lighthouse Scores:** While the package components are optimized for performance, overall Lighthouse scores depend significantly on how the consuming app layout loads fonts, renders images, and registers CSS.
