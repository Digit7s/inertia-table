# Roadmap

The `digit7s/inertia-table` package is actively maintained. Below are features that are currently planned but **not yet implemented** in the current release.

## Planned Features

- **Background / Queued CSV Export**: While the current release supports streaming CSV exports synchronously, we plan to implement a delayed background job dispatch system allowing for a UI-driven "Download Center" for extremely large datasets.
- **Excel Export**: Native bindings to export directly to `.xlsx` files without requiring heavy external dependencies.
- **PDF Export**: Built-in support to stream table data into a basic PDF document.
- **Deeper Type Declarations**: Expanding the TypeScript definitions for `TableData` and `Column` props out-of-the-box.
- **Additional Frontend Adapters**: Potential support for raw Tailwind without `shadcn-vue`, or alternative frameworks like React.

*Note: Please do not rely on these features until they are officially documented in the respective usage guides.*
