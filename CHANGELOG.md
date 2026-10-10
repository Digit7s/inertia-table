# Changelog

All notable changes to `digit7s/inertia-table` will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning where possible.

## [Unreleased]

### Support

- Set the v2.0.0 support range to Laravel 12 and 13, PHP 8.2+ (Laravel 12) and PHP 8.3+ (Laravel 13), Inertia Laravel 2 and 3 respectively, and Vue 3. Laravel 10/11 remain associated with the earlier package release line; this does not imply upstream security support for those Laravel releases.

### Fixed

- Normalize serialized `searchable` metadata to a boolean while retaining configured search fields on the server.
- Respect date and datetime format metadata in the Vue component and safely handle date-only, null, and invalid values.
- Exclude the example application's recursive package symlink from ESLint traversal.
- Extend Testbench development constraints for the declared Laravel test matrix.

### Changed

- Validate bulk-action IDs against the scoped table query and preserve select-all matching semantics.
- Resolve dotted relationship column values into `_column_values` without serializing automatically loaded relationship models.
- Apply configured relationship-aware sort, filter, and search state consistently to table and export queries; stabilize paginated tie order.
- Neutralize formula-leading text in CSV cells and require applications to provide export authorization through route middleware or the export callback.

### Migration notes

- See [Migration guide for v2](docs/migration-v2.md) for framework requirements, payload changes, stricter bulk-action validation, CSV formula neutralization, and authorization contracts.

### Documentation

- Document the Laravel 12/13 support line, relationship slot payload migration, date formatting and timezone behavior, and v2 upgrade requirements.

## [1.1.0] - 2026-06-29

### Added

- Added optional CSV export support using Laravel streamed downloads and PHP `fputcsv()`.
- Added table-level export configuration through `{Module}Table::exports()`.
- Added export metadata support for frontend toolbar rendering.
- Added Laravel example app setup for package verification.
- Added a `DataTableExportButton` component to the published package components.
- Added optional table `aria-label` attribute capability in `InertiaTable`.

### Changed

- Extracted pagination, filters, and bulk action bar into separate reusable components (`DataTablePagination`, `DataTableFilters`, `DataTableBulkActionBar`) for better structure.
- Refactored `InertiaTable.vue` to rely on the smaller extracted child components, improving code readability.
- Improved relative path resolution for package components.

### Fixed

- Fixed missing accessible names on table control buttons using `aria-label`.
- Fixed pagination buttons to expose `aria-current="page"` for the active page and added `<nav aria-label="Pagination">`.
- Fixed non-submit table controls by explicitly setting `type="button"`.
- Fixed duplicated confirmation/processing behavior by adding an `isConfirming` loading state to `ConfirmDialog`.
- Fixed sortable headers accessibility state using `aria-sort`.

### Documentation

- Added comprehensive documentation structure inside the `docs/` directory.
- Added documentation for CSV export configuration.
- Added accessibility documentation for table controls and pagination.
- Added example app setup and troubleshooting notes.
- Updated README to point directly to the dedicated documentation files.

### Internal

- Added Laravel example app verification notes and configured a local path repository.
- Added tests for CSV export functionality (`tests/Feature/ExportTest.php`).
- Updated internal type definitions (`types.ts`) to include `ExportAction`.

[Unreleased]: https://github.com/Digit7s/inertia-table/compare/v1.1.0...HEAD
