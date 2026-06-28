# Installation

This guide will walk you through setting up `digit7s/inertia-table` in your Laravel + Inertia application.

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13
- Inertia.js (Vue 3 adapter)
- Tailwind CSS (v3 or v4)
- `shadcn-vue` (for the frontend components)

## 1. Install the Package

Install the package via Composer:

```bash
composer require digit7s/inertia-table
```

## 2. Publish the Vue Components

The package requires its Vue components to be published directly into your consuming application so you can customize them if needed.

Publish the components to your `resources/js/components/inertia-table` directory:

```bash
php artisan vendor:publish --tag="inertia-table-components"
```

## 3. Frontend Requirements (shadcn-vue)

The published Vue components utilize several base UI components from `shadcn-vue`.

Ensure you have initialized `shadcn-vue` in your project and installed the following base components:

```bash
npx shadcn-vue@latest add button
npx shadcn-vue@latest add table
npx shadcn-vue@latest add dropdown-menu
npx shadcn-vue@latest add input
npx shadcn-vue@latest add badge
npx shadcn-vue@latest add select
npx shadcn-vue@latest add dialog
```

*Note: If your Vite build fails because it cannot resolve animations, you may also need to install `tw-animate-css`:*
```bash
npm install tw-animate-css
```

Once installed, your table is ready to be used! Head over to the [Basic Usage](./usage.md) documentation to create your first table.
