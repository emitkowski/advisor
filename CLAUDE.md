<laravel-boost-guidelines>
=== .ai/project rules ===

# Project

This is a Laravel 13 application — a personal AI advisor that learns from conversations over time. It uses Jetstream with Inertia.js and Vue 3 for the frontend.

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
- `@tailwindcss/forms` is active — it sets `appearance: none` on `<select>` elements and injects a custom arrow via `background-image`. Always use `pl-3 pr-10` (not `px-3`) on selects to leave room for the arrow

## Core Domain Concepts

### Agents

- Agents define the advisor's identity, personality, and algorithm
- Each agent has: `name`, `color`, `description`, `system_prompt_preamble`, `algorithm`, `personality` (JSON array of `{trait, value, description}`)
- Preset agents are seeded via `Agent::seedDefaults()` — see `agent.md` for all definitions
- **Visibility pattern**: agents are either personal (`user_id = auth, team_id = null`) or team-shared (`team_id = team`). Use `visibleAgentsQuery()` in controllers (defined in both `AgentController` and `AdvisorController`) to scope correctly — never query agents without it
- Only the creator (`user_id`) can mutate an agent; any team member can read it

### Sessions & Streaming

- Sessions hold a thread (JSON array of `{role, content}` messages)
- Chat responses stream via SSE — the `message` endpoint yields tagged arrays: `['type' => 'text', 'content' => '...']`, `['type' => 'search_start']`, etc.
- Session close queues a `ProcessSessionLearning` job on the `learning` queue

### Learning Pipeline (`ProcessSessionLearning`)

After a session closes the job runs in order:
1. `generateTitle()` — sets session title if not already set
2. `generateSummary()` — short shareable summary stored in `session.summary`
3. `extractLearnings()` — categorized insights (blind_spot / pattern / follow_through / value / reaction / domain)
4. `extractProfileObservations()` — stable traits with confidence scores
5. `extractSignals()` — satisfaction ratings
6. `extractProjects()` — ideas/projects with status (active/paused/completed/abandoned/unclear)

All extraction calls use `AnthropicService::completeJson()` which returns structured JSON from Claude.

### System Prompt Assembly (`SystemPromptBuilder`)

Built fresh before every message:
1. Agent's `system_prompt_preamble`
2. Personality traits block
3. Memory context (profile observations, learnings by category)
4. Project context (`Project::buildProjectContext()`) — personal + team projects
5. Agent's `algorithm`

`teamId()` resolves via `User::find($userId)?->currentOrOwnedTeam()?->id` — so team context is implicit, not passed explicitly.

### Teams (Custom — NOT Jetstream teams)

Jetstream's team feature is disabled. This app uses a lightweight custom team system:
- Tables: `teams`, `team_members` (pivot), `team_invitations`
- A user can own at most one team (`ownedTeam` HasOne) and belong to many (`teams` BelongsToMany)
- `User::currentOrOwnedTeam()` — returns `$this->currentTeam ?? $this->ownedTeam`; used throughout controllers and the system prompt builder
- Invitations use 64-char random tokens with 7-day expiry; `TeamInvitation::generate()` is the factory
- Invite emails go to Mailpit locally (dashboard at `http://localhost:8025`)
- Accept invitation page: `/invite/{token}` → `InvitationController::show()` → `Team/AcceptInvitation.vue`

### Sharing

- Sessions can be shared via a public token stored in `advisor_sessions.share_token`
- Public URL: `/shared/{token}` — no auth required
- Shared pages include full OG + Twitter Card meta tags for rich link previews

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
- Mailpit dashboard is at `http://localhost:8025`
- Used for: team invitation emails

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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
