# MastoLens

**Track public Mastodon accounts and analyze how their posts perform over time.**

MastoLens is a self-hosted Laravel web application that lets you follow public Mastodon accounts and watch their engagement metrics evolve — favourites, boosts, and replies — captured at intelligent intervals over 30 days. No Mastodon API keys required. No data leaves your server.

---

## Why MastoLens?

Mastodon doesn't provide analytics for accounts you don't own. If you're a researcher, community manager, or just curious about how content performs in the fediverse, there's no built-in way to track engagement over time.

MastoLens solves this by:

- Polling **public** Mastodon API endpoints (no authentication with Mastodon needed)
- Storing engagement snapshots at strategic intervals
- Building historical growth curves for every tracked post
- Running entirely on your own infrastructure — no third-party services, no CDNs, no tracking

---

## What It Does

- **Track any public Mastodon account** — enter `user@instance.tld` and MastoLens resolves it via WebFinger + the instance API
- **Discover posts automatically** — fetches the latest 20 public statuses per tracked account every 5 minutes
- **Snapshot engagement metrics** — captures favourites, boosts, and reply counts on an intelligent schedule (see below)
- **Classify post types** — originals, replies, boosts (with full original post content and author info)
- **Account averages (dashboard/account page)** — average favourites, boosts, and replies are calculated across all tracked time, using original posts only (replies/boosts excluded)
- **Visualize growth** — per-post engagement timeline charts (Chart.js, fully self-hosted)
- **Track followers** — daily follower count snapshots with trend charts
- **Archive gracefully** — after 30 days, polling stops but all data remains visible
- **Multi-user** — each user manages their own tracked accounts with private data

---

## How the Snapshot System Works

This is the core of MastoLens. Rather than polling every post every minute (which would hammer the API), MastoLens uses an **intelligent age-based snapshot schedule**.

### The Snapshot Targets

Each discovered post gets snapshots at these ages:

| Age | Interval |
|---|---|
| 0 min | Captured inline at discovery |
| 15 min | First follow-up |
| 30 min | |
| 1 hour | |
| 2 hours | |
| 4 hours | |
| 8 hours | |
| 12 hours | |
| 24 hours | |
| 2 days | |
| 3 days | |
| 7 days | |
| 14 days | |
| 30 days | Final snapshot, then archived |

**Chart note:** On the status detail chart, MastoLens shows a synthetic **Posted** baseline at zero engagement (`0,0,0`) so the curve starts at publication. The first **real** data point is the discovery snapshot (labeled **Initial** in the UI), which may be a few minutes after publish depending on the 5-minute sync cycle.

### How It Works in Practice

1. **Discovery** — Every 5 minutes, MastoLens fetches the latest 20 statuses for each tracked account. New posts are stored with an initial **real** snapshot taken inline at discovery time.

2. **Scheduling** — Each post has a `next_snapshot_due_at` timestamp. After discovery, this is set to the next target age relative to the post creation time.

3. **Capture** — Every minute, the scheduler checks: "Are there any posts whose `next_snapshot_due_at` has passed?" If yes, it dispatches a job that:
   - Fetches current metrics from the Mastodon API
   - Stores the snapshot
   - Sets `next_snapshot_due_at` to the **next** target time

4. **Between targets** — The post is simply skipped. No API calls, no wasted resources.

5. **Archive** — After the 30-day snapshot, `next_snapshot_due_at` is set to `null`. The post is archived — no more API calls, ever. But all historical data remains visible.

**Example walkthrough**: A post is published at 3:03 PM. The next account sync runs at 3:05 PM, discovers it, and stores the first real snapshot (**Initial** in the chart).

The chart also includes a synthetic **Posted** baseline at 3:03 PM with zero engagement so growth is visually anchored to publication time. Because 15 minutes after publish is 3:18 PM, `next_snapshot_due_at` is set to 3:18 PM. At 3:18, the scheduler captures metrics and advances to the next target (3:33, 4:03, 5:03, etc.) until 30 days.

Over the entire lifetime of tracking, only **14 API calls** are made per post.

---

## Deployment Guide

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- Git

That's it. Everything else runs inside containers.

### 1. Clone the Repository

```bash
git clone <your-repo-url> mastolens
cd mastolens
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

```env
APP_PORT=8080          # Port the app will be available on
DB_PASSWORD=secret     # Change this for production
DB_ROOT_PASSWORD=rootsecret  # Change this for production
```

### 3. Start the Application

```bash
docker compose up -d --build
```

### 4. Generate Application Key

This is **required** — Laravel needs an encryption key for sessions, cookies, and CSRF tokens. Without it, the app will not function.

First, generate and display a key:

```bash
docker compose exec app php artisan key:generate --show
```

This outputs a key like `base64:AbCdEf...`. Copy it into your `.env` file:

```env
APP_KEY=base64:AbCdEf...
```

Then rebuild so the container picks up the key:

```bash
docker compose up -d --build app web
```

> **Important**: Save this key. If you lose it, active sessions become invalid and any encrypted data becomes unreadable. When migrating to a new server, always bring your `.env` file (which contains this key) along with `docker/data/`.

This builds and starts 5 containers:

| Container | Role |
|---|---|
| `mastolens-app` | PHP-FPM application server (runs migrations on startup) |
| `mastolens-web` | Nginx reverse proxy (serves static assets + proxies to app) |
| `mastolens-db` | MariaDB 11 database |
| `mastolens-worker` | Queue worker (processes sync, snapshot, and archive jobs) |
| `mastolens-scheduler` | Cron-based scheduler (triggers Laravel's task scheduler every minute) |

### 5. Access the App

Open `http://localhost:8080` (or whatever `APP_PORT` you set).

Register a new account, log in, and start tracking Mastodon accounts.

### 6. Start Tracking

1. Go to **Tracked Accounts** → **Add Account**
2. Enter a handle like `user@mastodon.social`
3. MastoLens resolves the account, fetches their latest posts, and begins tracking automatically

---

## Environment Variables

### Essential

| Variable | Default | Description |
|---|---|---|
| `APP_PORT` | `8080` | Host port for the web interface |
| `DB_PASSWORD` | `secret` | MariaDB user password — **change in production** |
| `DB_ROOT_PASSWORD` | `rootsecret` | MariaDB root password — **change in production** |
| `APP_DEBUG` | `true` | Set to `false` in production |
| `APP_ENV` | `local` | Set to `production` in production |
| `REGISTRATION_ENABLED` | `true` | Set to `false` to disable public user registration |

### SSL / Domain

| Variable | Default | Description |
|---|---|---|
| `APP_DOMAIN` | `localhost` | Your FQDN (e.g., `stats.example.com`) |
| `APP_URL` | `http://localhost:8080` | Full base URL of the application |
| `SSL_ENABLED` | `false` | Enable HTTPS in Nginx |
| `SSL_PORT` | `8443` | Host port for HTTPS |
| `SSL_CERT_PATH` | `/etc/nginx/ssl/cert.pem` | Path to SSL certificate inside container |
| `SSL_KEY_PATH` | `/etc/nginx/ssl/key.pem` | Path to SSL key inside container |

### Database

| Variable | Default | Description |
|---|---|---|
| `DB_DATABASE` | `mastolens` | Database name |
| `DB_USERNAME` | `mastolens` | Database user |
| `DB_HOST` | `db` | Database host (the Docker service name) |

### Safety

| Variable | Default | Description |
|---|---|---|
| `ALLOW_FRESH_MIGRATE` | `true` | Safety guard: if the DB appears empty but has many pending migrations, the entrypoint will refuse to run unless this is `true`. Prevents accidental data loss if a volume is wiped. |

---

## Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Browser    │────▶│    Nginx     │────▶│  PHP-FPM    │
│              │     │  (web)      │     │  (app)      │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                          ┌────────────────────┼────────────────────┐
                          │                    │                    │
                    ┌─────▼─────┐       ┌─────▼─────┐       ┌─────▼─────┐
                    │  MariaDB  │       │  Worker   │       │ Scheduler │
                    │  (db)     │       │ (queue)   │       │ (cron)    │
                    └───────────┘       └───────────┘       └───────────┘
```

- **4-stage Dockerfile**: Frontend assets (Node/Vite) → PHP dependencies (Composer) → App image (PHP-FPM) → Web image (Nginx)
- **No external services**: Queue, cache, and sessions all use the database. No Redis, no external APIs beyond Mastodon instances.
- **All assets self-hosted**: Chart.js is bundled via Vite. No CDN calls.

---

## Data Persistence

All persistent data is stored in `./docker/data/`:

| Path | Contents |
|---|---|
| `docker/data/mariadb/` | MariaDB data files |
| `docker/data/storage/` | Laravel storage (logs, cache, etc.) |

These directories are Docker volume mounts. Your data survives container rebuilds. To start completely fresh, stop containers and delete `docker/data/`.

---

## Common Operations

### Rebuild after code changes

```bash
docker compose up -d --build app web
```

Always rebuild `app` and `web` together to keep Vite asset hashes in sync.

### View logs

```bash
# Application logs
docker compose exec app cat storage/logs/laravel.log | tail -50

# Worker output
docker compose logs worker --tail 50

# Scheduler output
docker compose logs scheduler --tail 50
```

### Manually trigger a sync

```bash
docker compose exec app php artisan tinker --execute="
    App\Jobs\SyncTrackedAccountStatusesJob::dispatch(
        App\Models\TrackedAccount::find(1)
    );
"
```

### Check container status

```bash
docker compose ps
```

### Stop everything

```bash
docker compose down
```

### Reset database (destructive)

```bash
docker compose down
rm -rf docker/data/mariadb
docker compose up -d --build
```

---

## Tech Stack

| Component | Technology |
|---|---|
| Backend | Laravel (PHP 8.4) |
| Database | MariaDB 11 |
| Queue | Laravel database driver |
| Scheduler | Cron → `php artisan schedule:run` |
| Frontend | Blade templates, Tailwind CSS |
| Charts | Chart.js (bundled via Vite) |
| Auth | Laravel Fortify |
| Containers | Docker (4-stage multi-stage build) |

---

## Security Notes

- The app is designed for **private, authenticated use**. All routes require login.
- Mastodon data is fetched from **public API endpoints only** — no OAuth tokens or API keys needed.
- The `ALLOW_FRESH_MIGRATE` safety check prevents accidental data wipes if the database volume is lost.
- Set `APP_DEBUG=false` and `APP_ENV=production` before exposing to the internet.
- Change default database passwords in `.env` for any non-local deployment.
- For HTTPS setup, see the production deployment section below.

---

## Production Deployment

### Domain & SSL Setup

To deploy MastoLens on a real server with a domain (e.g., `mastolens.example.tld`):

**1. Update `.env`:**

```env
APP_NAME=MastoLens
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mastolens.example.tld

APP_DOMAIN=mastolens.example.tld
APP_PORT=80
SSL_ENABLED=true
SSL_PORT=443

DB_PASSWORD=use-a-strong-password-here
DB_ROOT_PASSWORD=use-a-different-strong-password-here
```

**2. Obtain SSL certificates and configure:**

MastoLens needs SSL certificate files to serve HTTPS. Choose one of these approaches:

**Option A: Certbot on the host (simplest)**

```bash
# Install certbot (Ubuntu/Debian)
apt install certbot

# Stop MastoLens so port 80 is free for the challenge
docker compose down

# Obtain certificate
certbot certonly --standalone -d mastolens.example.tld

# Copy certs into the project
mkdir -p docker/ssl
cp /etc/letsencrypt/live/mastolens.example.tld/fullchain.pem docker/ssl/cert.pem
cp /etc/letsencrypt/live/mastolens.example.tld/privkey.pem docker/ssl/key.pem
```

Then uncomment the SSL volume mount in `docker-compose.yml`:

```yaml
web:
  volumes:
    - ./docker/data/storage/app/public:/var/www/html/public/storage:ro
    - ./docker/ssl:/etc/nginx/ssl:ro    # ← uncomment this line
```

Let's Encrypt certs expire every 90 days. Set up a cron job on the host to renew:

```bash
# Add to root's crontab (crontab -e)
0 3 1 */2 * cd /path/to/mastolens && docker compose stop web && certbot renew --quiet && cp /etc/letsencrypt/live/mastolens.example.tld/fullchain.pem docker/ssl/cert.pem && cp /etc/letsencrypt/live/mastolens.example.tld/privkey.pem docker/ssl/key.pem && docker compose up -d web
```

**Option B: Reverse proxy with automatic SSL (Caddy)**

If you'd rather not manage certificates at all, place [Caddy](https://caddyserver.com/) in front of MastoLens. Caddy obtains and renews Let's Encrypt certificates automatically with zero configuration:

```bash
# Install Caddy on the host, then create a Caddyfile:
mastolens.example.tld {
    reverse_proxy localhost:8080
}
```

In this setup, keep `SSL_ENABLED=false` and `APP_PORT=8080` in your `.env` — Caddy handles all SSL. No `docker/ssl/` directory or volume mount needed.

**3. Build and start:**

```bash
docker compose up -d --build
```

**How the ports work:**

| Setting | What happens |
|---|---|
| `APP_PORT=80` | Host port 80 → Nginx port 80 inside container |
| `SSL_PORT=443` | Host port 443 → Nginx port 443 inside container |
| `SSL_ENABLED=true` | Nginx uses the HTTPS config: serves SSL on 443, redirects HTTP→HTTPS on 80 |
| `SSL_ENABLED=false` | Nginx serves plain HTTP on port 80 only |

When `SSL_ENABLED=true`, visitors hitting `http://mastolens.example.tld` are automatically redirected to `https://mastolens.example.tld`. You don't need a separate reverse proxy — Nginx inside the container handles both SSL termination and the HTTP→HTTPS redirect.

For local development, the defaults (`APP_PORT=8080`, `SSL_ENABLED=false`) serve the app at `http://localhost:8080` with no SSL.

> **Note**: If your server already runs a reverse proxy (e.g., Traefik, Caddy, or another Nginx), you can keep `SSL_ENABLED=false` and let the outer proxy handle SSL. In that case, set `APP_PORT` to an internal port (e.g., `8080`) and proxy to it.

### Email Configuration

By default, MastoLens logs all emails to `storage/logs/laravel.log` instead of sending them. This is fine for local development, but for production you'll want real email delivery (for password resets and other notifications).

**SMTP provider** (e.g., Mailgun, Postmark, Amazon SES, or any SMTP server):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org        # Your SMTP host
MAIL_PORT=587                     # Usually 587 (TLS) or 465 (SSL)
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls               # tls or ssl
MAIL_FROM_ADDRESS=noreply@mastolens.example.tld
MAIL_FROM_NAME="${APP_NAME}"
```

**Common providers:**

| Provider | MAIL_HOST | MAIL_PORT | Notes |
|---|---|---|---|
| Mailgun | `smtp.mailgun.org` | `587` | Free tier: 1,000 emails/month |
| Postmark | `smtp.postmarkapp.com` | `587` | Free tier: 100 emails/month |
| Amazon SES | `email-smtp.us-east-1.amazonaws.com` | `587` | Very cheap at scale |
| Gmail | `smtp.gmail.com` | `587` | Use App Passwords, not your real password |
| Generic SMTP | Your server's SMTP host | `587` | Works with any standard SMTP server |

After changing mail settings, rebuild the app container to pick up the new config:

```bash
docker compose up -d --build app
```

> **Tip**: To test your mail configuration, register a new user account — the password reset flow will send an email.

### Disabling Registration

Once you've created your account(s), you can lock down the app so nobody else can register. Add this to your `.env`:

```env
REGISTRATION_ENABLED=false
```

Rebuild to apply:

```bash
docker compose up -d --build app worker
```

The `/register` route will return a 404. Login, password reset, and all other features continue to work normally.

### Creating Users from the Command Line

If registration is disabled (or you simply prefer not to use the web form), you can create users via the command line:

```bash
docker compose exec app php artisan tinker --execute="
    App\Models\User::create([
        'name' => 'Your Name',
        'email' => 'you@example.com',
        'password' => Hash::make('YourSecurePassword123!'),
    ]);
"
```

To reset a user's password:

```bash
docker compose exec app php artisan tinker --execute="
    \$user = App\Models\User::where('email', 'you@example.com')->first();
    \$user->update(['password' => Hash::make('NewPassword123!')]);
"
```

---

## Running Tests

Tests use PHPUnit with an in-memory SQLite database — no MariaDB required. Run them alongside your existing containers:

```bash
docker compose run --rm --no-deps \
  -v "$PWD/tests:/var/www/html/tests" \
  app php artisan test
```

The `tests/` directory is excluded from Docker images (via `.dockerignore`) to keep production builds lean. The `-v` flag mounts it into a throwaway container for the test run.

---

## License

This project is private software. All rights reserved.
