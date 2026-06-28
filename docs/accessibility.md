# Accessibility

`digit7s/inertia-table` aims to provide an accessible baseline for interactive data tables out-of-the-box, ensuring users utilizing assistive technologies (like screen readers) or keyboard navigation can use the data table effectively.

## Built-in Package Features

The package components handle the following interactive accessibility concerns:

- **Icon-Only Buttons**: All icon buttons (like pagination controls and the View Options toggle) have hidden `aria-label` texts so their purpose is announced by screen readers.
- **Pagination Navigation**: The pagination component (`DataTablePagination.vue`) utilizes a semantic `<nav aria-label="Pagination">` and the active page button uses `aria-current="page"`.
- **Sorting Headers**: Sortable table headers implement the `aria-sort` attribute (indicating ascending, descending, or none), which updates dynamically based on the current active sort state.
- **Form Controls**: Non-submitting UI controls (like filter dropdown triggers, view toggles, etc.) explicitly use `type="button"` to prevent accidental form submissions within your layouts.

## Consuming App Guidance

While the package handles the interactive state of the table, some accessibility responsibilities belong strictly to the consuming Laravel/Inertia application structure where the table is rendered.

**Page Layout & Landmarks**
The package does not wrap the table in a `<main>` landmark. Your application's layout file should provide the `<main>` tag around the primary content area where the table is rendered.

**SEO and Meta Data**
The package does not inject meta descriptions, document titles, or SEO caching headers. These must be defined within your Inertia page (using tools like `@inertiajs/vue3` `Head` component) and your Laravel route caching layer.

**Table Labels (Optional)**
You can supply an accessible label or caption to the table if required by providing standard HTML attributes to the root `<InertiaTable>` component.
