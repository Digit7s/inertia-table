# Troubleshooting

Common issues and solutions when working with `digit7s/inertia-table`.

## Vite cannot resolve `tw-animate-css`

**Symptom**: During `npm run build` or `npm run dev`, Vite throws an error about resolving the `tw-animate-css` dependency.

**Solution**: This is a common requirement introduced by `shadcn-vue` animations. Ensure the dependency is explicitly installed in your consuming app:
```bash
npm install tw-animate-css
```

## Missing shadcn-vue Components

**Symptom**: The published `InertiaTable.vue` component fails to compile, stating that components like `Button`, `Table`, or `DropdownMenu` cannot be found.

**Solution**: The package does not bundle UI dependencies. Ensure you have scaffolded `shadcn-vue` correctly in your project and installed the necessary base components:
```bash
npx shadcn-vue@latest add button table dropdown-menu input badge select dialog
```

## Wrong Import Path for Published Components

**Symptom**: You receive "file not found" errors when trying to import `InertiaTable` into your Vue pages.

**Solution**: Ensure you are importing from the `resources/js/components/inertia-table/` directory (or wherever you published them). Check that your `tsconfig.json` paths alias correctly resolves `@/components`.
```vue
import InertiaTable from '@/components/inertia-table/InertiaTable.vue';
```

## Lighthouse Accessibility Issues

### Missing `<main>` landmark or meta tags
**Symptom**: Lighthouse flags your data table page for lacking a `<main>` role or a missing meta description.

**Solution**: This is not an issue with the table package. The table is a child component, and the `<main>` tag must be implemented within your global app Layout component. Meta tags should be handled using Inertia's `<Head>` component on the specific page.

### Buttons missing accessible labels
**Symptom**: Lighthouse flags icon-only buttons for missing labels.

**Solution**: If you are using the latest package release, ensure you have published the latest Vue components using `--force`:
```bash
php artisan vendor:publish --tag="inertia-table-components" --force
```
