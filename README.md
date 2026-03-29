# Advisor

A personal AI advisor that gets more useful over time. It's built to be brutally honest — no validation, no encouragement, no sycophancy. It challenges ideas, plays devil's advocate, flags when excitement is outrunning evidence, and tracks patterns in how you think across sessions.

## What It Does

**Chat** — Start a session with an agent of your choosing and send messages. The advisor responds in real-time via SSE streaming. Each agent has its own identity, personality traits, and algorithm that shape how it responds.

**Agents** — Create custom advisors with their own name, color, system prompt preamble, algorithm, and personality trait sliders (0–100). Several preset agents ship by default (The Advisor, Devil's Advocate, Strategic Advisor, Technical Advisor, Coach, Samuel L. Jackson). Agents can be shared with a team.

**Learns between sessions** — When you close a session, a background job analyzes the conversation and extracts:
- **Learnings** — blind spots, thinking patterns, follow-through history, reactions to feedback
- **Profile** — stable traits like risk tolerance, decision speed, overconfidence tendencies
- **Projects** — tracks every idea or project mentioned, including abandoned ones
- **Signals** — satisfaction ratings (explicit like "7/10" or inferred from tone)
- **Summary** — a short shareable summary of the session's main topic

**Memory feeds back in** — Before every response, the system prompt is assembled fresh from all accumulated memory, so the advisor builds a model of how you specifically think and uses it.

**Sharing** — Sessions can be shared via a public read-only link. Shared pages include OG meta tags for rich link previews in Slack/iMessage/Teams.

**Teams** — Invite a partner to share agents and projects. Team members see each other's shared agents and shared projects are injected into the system prompt for all team members.

## API

All advisor endpoints are under `/api/v1/advisor/` and require Sanctum authentication unless noted.

### Sessions
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/advisor/sessions` | List sessions (paginated, filterable by status/agent/search) |
| `POST` | `/api/v1/advisor/sessions` | Create a new session |
| `GET` | `/api/v1/advisor/sessions/{id}` | Get session with full thread |
| `POST` | `/api/v1/advisor/sessions/{id}/message` | Send a message (SSE stream) |
| `PATCH` | `/api/v1/advisor/sessions/{id}/title` | Update session title |
| `POST` | `/api/v1/advisor/sessions/{id}/close` | Close session + queue learning |
| `POST` | `/api/v1/advisor/sessions/{id}/rate` | Rate a session (1–10) |
| `POST` | `/api/v1/advisor/sessions/{id}/share` | Enable public sharing, returns share URL |
| `DELETE` | `/api/v1/advisor/sessions/{id}/share` | Revoke public share link |

### Agents
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/advisor/agents` | List visible agents (personal + team) |
| `POST` | `/api/v1/advisor/agents` | Create a custom agent |
| `PATCH` | `/api/v1/advisor/agents/{id}` | Update agent |
| `DELETE` | `/api/v1/advisor/agents/{id}` | Delete agent |

### Profile
| Method | Endpoint | Description |
|--------|----------|-------------|
| `DELETE` | `/api/v1/advisor/profile-observations/{id}` | Remove a profile observation |
| `DELETE` | `/api/v1/advisor/learnings/{id}` | Remove a learning |

### Teams
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/advisor/teams/current` | Get current team with members and invitations |
| `POST` | `/api/v1/advisor/teams` | Create a team |
| `POST` | `/api/v1/advisor/teams/invite` | Invite a member by email |
| `DELETE` | `/api/v1/advisor/teams/members/{userId}` | Remove a team member (owner only) |
| `DELETE` | `/api/v1/advisor/teams` | Disband team (owner only) |
| `GET` | `/api/v1/advisor/teams/invitations/{token}` | Check invitation validity (public) |
| `POST` | `/api/v1/advisor/teams/invitations/{token}/accept` | Accept an invitation |

### Debug
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/advisor/system-prompt` | View current assembled system prompt |

## Environment Variables

```env
ANTHROPIC_API_KEY=        # Required — your Anthropic API key
ADVISOR_MODEL=            # Default: claude-opus-4-6
ADVISOR_MAX_TOKENS=       # Default: 2048
ADVISOR_LEARNING_QUEUE=   # Default: learning
```

## Queue Worker

The learning extraction job runs on the `learning` queue. Start a worker:

```bash
./vendor/bin/sail artisan queue:work --queue=learning
```

## Stack

- **PHP** 8.5 / **Laravel** 13
- **Jetstream** (Inertia + Vue 3) — authentication, profile management
- **Tailwind CSS** v4 / **Vite** 8
- **Sail** — Docker-based local development environment
- **MySQL** 8.4
- **Redis** — cache
- **Mailpit** — local email capture (dashboard at `http://localhost:8025`)
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
| Mailpit | `http://localhost:8025` |
| Telescope | `https://{APP_DOMAIN}/telescope` |

## Testing

Tests use an in-memory SQLite database:

```bash
./vendor/bin/sail artisan test --compact
```
