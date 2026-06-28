# Changelog

All notable changes to `digit7s/inertia-table` will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning where possible.

## [Unreleased]

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

[Unreleased]: https://github.com/Digit7s/inertia-table/compare/v1.0.0...HEAD
