# Performance & Package Philosophy

The architecture of `digit7s/inertia-table` is designed to be lightweight, un-opinionated where possible, and strictly backend-driven.

## Backend-Driven

All data processing, sorting, filtering, and pagination occurs securely on the Laravel backend. The Vue frontend acts exclusively as a presentational layer synchronized to the backend query state through Inertia's URL parameters. This ensures that large datasets do not consume excessive memory in the user's browser.

## Native Streaming Export

The CSV export feature leverages Laravel's native `response()->streamDownload()`. This directly flushes the CSV buffer to the browser progressively without loading the entire result set into memory on the server.

- We intentionally avoided heavy dependencies such as `maatwebsite/excel` or `league/csv`.
- Queue-based exports (e.g. background job dispatching with a UI download center) are not part of the current core version, as they introduce unnecessary complexity and infrastructure requirements for basic use cases.

## Consuming App Responsibilities

Performance concerns at the page or application level are inherently outside the scope of the package:
- **Cache Headers:** The package does not attach HTTP cache policies to the Inertia page response. This should be configured at the application's route middleware or web server level.
- **Lighthouse Scores:** While the package components are optimized for performance, overall Lighthouse scores depend significantly on how the consuming app layout loads fonts, renders images, and registers CSS.
