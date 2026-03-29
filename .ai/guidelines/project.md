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
