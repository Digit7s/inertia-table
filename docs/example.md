# Example App

The package includes a fully functional Laravel application in the `example` directory. This is used for local development, Lighthouse accessibility testing, and verifying integration across the Laravel, Inertia, and Vue 3 ecosystem.

## Local Path Repository Setup

The example app links to your local checkout of the package using a Composer path repository. To install the package locally:

```bash
cd example
composer update digit7s/inertia-table
```

## Publishing Components

To publish the newest package components into the example app (overwriting old ones), run:

```bash
php artisan vendor:publish --tag="inertia-table-components" --force
```

## Running Builds and Type Checks

To ensure there are no TypeScript regressions in the Vue components:

```bash
npm run types:check
```

To compile the assets for a production test:

```bash
npm run build
```

*Note on Missing Dependencies:* If the Vite build fails stating `tw-animate-css` cannot be resolved, ensure you manually install the dependency within the example app (`npm install tw-animate-css`). This is a common requirement for `shadcn-vue` animations.

## Lighthouse Testing Notes

When running Lighthouse audits on the example app's tables (e.g. `/products`):
1. **Use Production Build**: Always run tests against an `npm run build` compilation.
2. **Incognito Mode**: Run audits in an incognito window without extensions to prevent false positives from browser plugins.
3. **App Responsibilities**: Disregard page-level SEO errors (like missing `<main>` landmarks or meta tags) as these are responsibilities of the surrounding layout template in the example app, not the `inertia-table` package itself.
