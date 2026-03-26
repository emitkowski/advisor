# Project

This is a Laravel 13 application using Jetstream with Inertia.js and Vue 3 for the frontend.

## Key Packages

- **Jetstream** — handles authentication, profile management, and API tokens via Sanctum
- **Inertia.js** — connects Laravel controllers to Vue 3 components without a separate API
- **Ziggy** — makes named Laravel routes available in Vue components via the `route()` helper
- **Sail** — Docker environment; run all artisan/composer/npm commands via `./vendor/bin/sail`
- **Redis** — default cache driver (`CACHE_STORE=redis`)
- **Mailpit** — local email capture; all outbound mail is caught here during development

## Frontend Conventions

- Vue components live in `resources/js/Pages` (full pages) and `resources/js/Components` (shared)
- Layouts are in `resources/js/Layouts`
- Use the `route()` helper from Ziggy for all internal links
- Tailwind CSS is the styling framework

## Debugging with Telescope

- Laravel Telescope is installed and available at `/telescope` in local development
- Telescope is disabled in tests (`TELESCOPE_ENABLED=false`) and restricted in production
- Before asking the user to reproduce an issue, query the `telescope_entries` table using the `database-query` tool to investigate what already happened
- Filter by `type` to find relevant entries — useful types: `request`, `query`, `exception`, `job`, `log`, `model`, `event`, `gate`, `command`
- The `content` column is a JSON blob — use `JSON_EXTRACT` or `->` to read specific fields
- Example: find recent exceptions with `SELECT content FROM telescope_entries WHERE type = 'exception' ORDER BY created_at DESC LIMIT 5`
- Example: find slow queries with `SELECT content FROM telescope_entries WHERE type = 'query' AND JSON_EXTRACT(content, '$.time') > 100 ORDER BY created_at DESC`

## Local Email (Mailpit)

- All mail sent locally is captured by Mailpit — nothing reaches real inboxes
- Mailpit dashboard is at `http://localhost:{FORWARD_MAILPIT_DASHBOARD_PORT}` (see `.env`)
- Useful for testing Jetstream emails: registration verification, password reset, team invitations

## API Conventions

- All API routes are versioned under `/api/v1/` — add new versions in `routes/api/v2.php` etc.
- API route files live in `routes/api/` and are loaded from `routes/api.php`
- API controllers extend `App\Http\Controllers\Api\V1\Controller` and live in `app/Http/Controllers/Api/V1/`
- All API responses use Eloquent API Resources from `app/Http/Resources/`
- API authentication uses Sanctum token auth (`auth:sanctum` middleware)
- Name API routes with the `api.v1.` prefix (e.g. `api.v1.users.index`)

## Development Workflow

- Start the environment: `./vendor/bin/sail up -d`
- Build assets: `npm run dev` (HMR) or `npm run build` (production)
- The app runs at `https://{APP_DOMAIN}` — requires the shared nginx proxy (`~/docker/proxy`) and a valid mkcert cert in `docker/certs/`
