# Advisor

A personal AI advisor that gets more useful over time. It's built to be brutally honest — no validation, no encouragement, no sycophancy. It challenges ideas, plays devil's advocate, flags when excitement is outrunning evidence, and tracks patterns in how you think across sessions.

## What It Does

**Chat** — Start a session and send messages. Claude responds in real-time via streaming. The advisor is tuned to resist validation, call out prior art, give explicit probability estimates, and flag known blind spots.

**Learns between sessions** — When you close a session, a background job analyzes the conversation and extracts:
- **Learnings** — blind spots, thinking patterns, follow-through history, reactions to feedback
- **Profile** — stable traits like risk tolerance, decision speed, overconfidence tendencies
- **Projects** — tracks every idea or project mentioned, including abandoned ones
- **Signals** — satisfaction ratings (explicit like "7/10" or inferred from conversation tone)

**Memory feeds back in** — Before every response, the system prompt is assembled fresh from all accumulated memory, so the advisor builds a model of how you specifically think and uses it.

## API

All advisor endpoints are under `/api/v1/advisor/` and require Sanctum authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/advisor/sessions` | List sessions (paginated) |
| `POST` | `/api/v1/advisor/sessions` | Create a new session |
| `GET` | `/api/v1/advisor/sessions/{id}` | Get session with thread |
| `POST` | `/api/v1/advisor/sessions/{id}/message` | Send a message (SSE stream) |
| `POST` | `/api/v1/advisor/sessions/{id}/close` | Close session + queue learning |
| `GET` | `/api/v1/advisor/system-prompt` | Debug: view current system prompt |

## Environment Variables

```env
ANTHROPIC_API_KEY=        # Required — your Anthropic API key
ADVISOR_MODEL=            # Default: claude-sonnet-4-20250514
ADVISOR_MAX_TOKENS=       # Default: 2048
ADVISOR_LEARNING_QUEUE=   # Default: learning
```

## Queue Worker

The learning extraction job runs on the `learning` queue. Start a worker:

```bash
./vendor/bin/sail artisan queue:work --queue=learning
```

## Known Issues / To Do

None.

## Stack

- **PHP** 8.5 / **Laravel** 13
- **Jetstream** (Inertia + Vue 3) — authentication, profile management
- **Tailwind CSS** v4 / **Vite** 8
- **Sail** — Docker-based local development environment
- **MySQL** 8.4
- **Redis** — cache
- **Mailpit** — local email capture
- **Telescope** — local request/query/job debugging

## Requirements

- Docker
- Node.js & npm
- Shared nginx proxy running (`~/docker/proxy`) — see `NEW_PROJECT.md`

## Getting Started

This is a project template. For a new project, follow `NEW_PROJECT.md` first.

For an existing checkout:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
npm run build
```

The app will be available at `https://{APP_DOMAIN}` once the nginx proxy is running and a cert is in place.

## Common Commands

```bash
# Start containers
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail stop

# Run artisan commands
./vendor/bin/sail artisan <command>

# Run tests
./vendor/bin/sail artisan test --compact

# Connect to MySQL
./vendor/bin/sail mysql

# Frontend dev server with HMR
./vendor/bin/sail npm run dev
```

## Local Services

| Service | URL |
|---|---|
| App | `https://{APP_DOMAIN}` |
| Mailpit | `http://localhost:{FORWARD_MAILPIT_DASHBOARD_PORT}` |
| Telescope | `https://{APP_DOMAIN}/telescope` |

## Testing

Tests use an in-memory SQLite database:

```bash
./vendor/bin/sail artisan test --compact
```
