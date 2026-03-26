# New Project Setup

Follow these steps in order when setting up a new project from this template.

## Prerequisites (one-time machine setup)

### Shared nginx proxy

All local projects are served through a shared Docker nginx proxy. Set it up once:

```bash
cd ~/docker/proxy
docker compose up -d
```

This runs an nginx-proxy container on ports 80/443 that automatically routes traffic to
any Sail project with a `VIRTUAL_HOST` environment variable.

### mkcert

Install mkcert if not already installed:

```bash
# WSL
sudo apt install mkcert

# Install the local CA — run both so Windows browsers trust it too
mkcert -install
powershell.exe -Command "Start-Process powershell -Verb RunAs -ArgumentList 'cd $(wslpath -w $(mkcert -CAROOT)); certutil -addstore -f Root rootCA.pem'"
```

---

## 1. Update Project Identity

Set these in both `.env` and `.env.example`:

| Variable | Example | Notes |
|---|---|---|
| `APP_NAME` | `MyApp` | |
| `APP_URL` | `https://myapp.test` | |
| `APP_DOMAIN` | `myapp.test` | |
| `DB_DATABASE` | `myapp` | |
| `FORWARD_DB_PORT` | `3307` | Must be unique per project |
| `FORWARD_REDIS_PORT` | `6379` | Must be unique per project |
| `VITE_PORT` | `5173` | Must be unique per project |
| `FORWARD_MAILPIT_PORT` | `1025` | Must be unique per project |
| `FORWARD_MAILPIT_DASHBOARD_PORT` | `8025` | Must be unique per project |

Then:
- `composer.json` — update `name` and `description`
- `README.md` — update the project name and description at the top

## 2. Generate SSL Certificate

Generate the cert for this project and register it with the shared proxy:

```bash
# Generate cert in the project
cd docker/certs
mkcert -cert-file cert.pem -key-file key.pem myapp.test

# Register with the shared proxy (nginx-proxy looks for {domain}.crt/.key)
cp cert.pem ~/docker/proxy/certs/myapp.test.crt
cp key.pem ~/docker/proxy/certs/myapp.test.key

# Reload the proxy to pick up the new cert
docker exec nginx-proxy nginx -s reload
```

Replace `myapp.test` with this project's domain throughout.

## 3. Add Hosts Entry

Running on WSL2 — the entry must go in the **Windows** hosts file so the browser can resolve it.

```bash
# Add to Windows hosts file (requires admin — run this in WSL)
echo "127.0.0.1 myapp.test" | sudo tee -a /mnt/c/Windows/System32/drivers/etc/hosts

# Also add to WSL hosts file for internal resolution
echo "127.0.0.1 myapp.test" | sudo tee -a /etc/hosts
```

> If the `tee` to the Windows hosts file fails with a permission error, open Notepad as Administrator on Windows and add the line manually to `C:\Windows\System32\drivers\etc\hosts`.

## 4. Update AI Context

- `.ai/guidelines/project.md` — replace the project description, stack notes, and conventions with ones relevant to the new project
- `remote.md` — clear the notes and replace with the current project status

## 5. Install Dependencies

```bash
composer install
npm install
```

## 6. Regenerate Boost

```bash
php artisan boost:install
```

This regenerates `CLAUDE.md` and `.mcp.json` with the correct project path and any updated guidelines.

## 7. Generate App Key

```bash
php artisan key:generate
```

## 8. Start Sail

```bash
./vendor/bin/sail up -d
```

The `laravel.test` container automatically registers with the nginx proxy via the `VIRTUAL_HOST`
environment variable — no additional config needed.

## 9. Run Migrations

```bash
./vendor/bin/sail artisan migrate
```

## 10. Frontend

For production or a one-off build:

```bash
npm run build
```

For local development with hot reload:

```bash
npm run dev
```

Vite will serve over HTTPS on `https://myapp.test:VITE_PORT` using the cert from `docker/certs/`.
HMR connects back via `APP_DOMAIN` and `VITE_PORT` from `.env` — both must be set correctly in Step 1.

## 11. GitHub Setup

1. Create a new repository and push
2. Add the following secrets under **Settings → Secrets → Actions**:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | Server IP or hostname |
| `DEPLOY_USER` | SSH username |
| `DEPLOY_SSH_KEY` | Private SSH key |
| `DEPLOY_PATH` | Absolute path to the app on the server |

## 12. Verify

- [ ] App loads at `https://myapp.test` (trusted cert, no browser warning)
- [ ] Auth works (register/login via Jetstream)
- [ ] `npm run dev` starts Vite over HTTPS with no cert warnings
- [ ] Editing a Vue component triggers hot reload in the browser
- [ ] Tests pass: `./vendor/bin/sail artisan test --compact`
- [ ] GitHub Actions workflow appears under the Actions tab
