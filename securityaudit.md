# MastoLens Security Audit

**Date:** 30 April 2026  
**Auditor:** GitHub Copilot (automated static analysis)  
**Scope:** Full codebase, dependencies, Docker/infrastructure configuration  
**Version audited:** Current working tree  

---

## Summary Table

| # | Title | Severity | Category |
|---|-------|----------|----------|
| 1 | Scheduler container runs as root | HIGH | Docker / Privilege Escalation |
| 2 | `trustProxies(at: '*')` — all proxies trusted unconditionally | HIGH | Network / IP Spoofing |
| 3 | `APP_DEBUG=true` in `.env.example` default | HIGH | Information Disclosure |
| 4 | Weak default database passwords in `.env.example` and `docker-compose.yml` | HIGH | Secrets Management |
| 5 | No HTTP security headers (CSP, X-Frame-Options, HSTS, etc.) | MEDIUM | HTTP Headers |
| 6 | Unvalidated external URLs used in `href` and `src` attributes | MEDIUM | XSS / javascript: injection |
| 7 | `addslashes()` used for JS context escaping (incorrect) | MEDIUM | XSS |
| 8 | `html_entity_decode(strip_tags(...))` — fragile XSS mitigation | MEDIUM | XSS |
| 9 | SSRF: `instance_domain` not validated before HTTP requests | MEDIUM | SSRF |
| 10 | `ALLOW_FRESH_MIGRATE=true` hardcoded in `docker-compose.yml` | MEDIUM | Data Safety |
| 11 | Session cookie not enforced as `secure` | MEDIUM | Session Security |
| 12 | No Content Security Policy — inline scripts and external images unconstrained | MEDIUM | CSP |
| 13 | Open registration enabled by default | LOW | Access Control |
| 14 | `SESSION_ENCRYPT=false` default | LOW | Session Security |
| 15 | MariaDB data volume not encrypted at rest | LOW | Data Storage |
| 16 | No rate limiting on tracked-account creation | LOW | Abuse / DoS |
| 17 | `composer:latest` base image is mutable | LOW | Supply Chain |
| 18 | `php:8.4-fpm-alpine` and `nginx:alpine` — no pinned digests | LOW | Supply Chain |
| 19 | `@tailwindcss/vite ^4.0.0` — build dependency, no known CVEs, up to date | INFO | Dependency |
| 20 | All production PHP dependencies — no known CVEs as of audit date | INFO | Dependency |

---

## Findings Detail

---

### FINDING 1 — Scheduler container runs as root
**Severity: HIGH**  
**File:** `docker-compose.yml` lines 92–113

```yaml
scheduler:
    user: root
    command: sh -c "chown -R www-data:www-data /var/www/html/storage && crond -f -l 8"
```

The scheduler container explicitly runs as `root` in order to `chown` the storage bind mount before starting `crond`. This means the cron daemon itself — and every `php artisan schedule:run` subprocess — executes with full root privileges inside the container.

**Risk:** If any Laravel job, Artisan command, or dependency has a remote code execution vulnerability, the attacker gains root inside the container. Combined with the bind-mounted `./docker/data/storage` volume, a root process can write to the host filesystem.

**Recommendation:** Use a Docker volume or a startup `initContainer`-style helper to fix ownership before switching to `www-data`. Alternatively use `docker run --user www-data` and mount storage with correct host ownership so `chown` is not needed at all. The `crond` in Alpine supports running as a non-root user if the crontab is placed at `/etc/crontabs/www-data` (which it already is) — the `chown` step is the only reason root is needed.

---

### FINDING 2 — `trustProxies(at: '*')` — all hosts trusted
**Severity: HIGH**  
**File:** `bootstrap/app.php` line 14

```php
$middleware->trustProxies(at: '*');
```

This configures Laravel to trust `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Proto`, and `X-Forwarded-Port` headers from **any** IP address. An attacker with direct access to the app container (port 9000 is PHP-FPM, not directly exposed, but any internal network peer) can forge these headers.

**Risk:** Fake `X-Forwarded-For` headers can poison rate-limiter keys (Fortify login rate limiting uses `$request->ip()`), bypass IP-based access controls, and produce false audit logs.

**Recommendation:** Restrict to the actual Nginx container's internal Docker network IP or use the container name: `trustProxies(at: '172.0.0.0/8')` or the specific subnet of the `mastolens` Docker bridge network. Since this is a single-stack deployment, `trustProxies(at: '172.0.0.0/8')` is a reasonable minimum; a tighter value would be the Nginx container's specific IP range.

---

### FINDING 3 — `APP_DEBUG=true` default in `.env.example`
**Severity: HIGH**  
**File:** `.env.example` line 4

```
APP_DEBUG=true
```

The template `.env.example` ships with `APP_DEBUG=true`. Any deployment that copies `.env.example` to `.env` without changing this value (including the automated `composer setup` script which does exactly this: `file_exists('.env') || copy('.env.example', '.env')`) will run in debug mode in production.

**Risk:** Debug mode exposes full stack traces, environment variable values, SQL query strings, and Laravel's Telescope-style dump in error pages. This is a critical information disclosure in production.

**Recommendation:** Change `.env.example` to `APP_DEBUG=false` and document that developers must set it to `true` manually.

---

### FINDING 4 — Weak default database passwords
**Severity: HIGH**  
**Files:** `.env.example` lines 37–38, `docker-compose.yml` lines 60–63

```
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
```

```yaml
MARIADB_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-rootsecret}"
MARIADB_PASSWORD: "${DB_PASSWORD:-secret}"
```

Both the `.env.example` and docker-compose fallback defaults are trivially weak passwords. The `docker-compose.yml` fallback means that even if `.env` is not created, the database starts with `rootsecret` / `secret`.

**Risk:** If the database port is ever inadvertently exposed (misconfiguration, future change), these credentials make brute-force trivial. The root password `rootsecret` grants full database server control.

**Recommendation:** Remove the `:-rootsecret` and `:-secret` fallbacks from `docker-compose.yml` (force explicit configuration). Replace `.env.example` values with `CHANGE_ME` placeholders. Document strong password generation in the README.

---

### FINDING 5 — No HTTP security headers
**Severity: MEDIUM**  
**Files:** `docker/nginx/http.conf.template`, `docker/nginx/https.conf.template`

Neither Nginx config adds any of the standard HTTP security headers:

- `X-Frame-Options: SAMEORIGIN` — missing (clickjacking)
- `X-Content-Type-Options: nosniff` — missing (MIME sniffing)
- `Content-Security-Policy` — missing (XSS, inline scripts, external resources)
- `Referrer-Policy` — missing
- `Permissions-Policy` — missing
- `Strict-Transport-Security` (HSTS) — missing even in the HTTPS template

**Risk:** Without `X-Frame-Options`, the app can be embedded in a malicious iframe. Without `X-Content-Type-Options`, browsers may execute mis-typed responses. Without HSTS, users on HTTPS are not protected against protocol downgrade attacks.

**Recommendation:** Add to both Nginx templates (and move to HTTPS template only for HSTS):

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), camera=(), microphone=()" always;
# HTTPS template only:
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

---

### FINDING 6 — Unvalidated external URLs in `href` and `src` attributes
**Severity: MEDIUM**  
**Files:** Multiple Blade views

The following URLs are stored from external Mastodon API responses and rendered directly into HTML attributes without protocol validation:

- `$trackedAccount->account_url` → `href` (`show.blade.php:21`)
- `$failed->status_url` → `href` (`show.blade.php:153`)
- `$status->boost_data_json['author_url']` → `href` (`statuses/show.blade.php:54`)
- `$status->boost_data_json['original_url']` → `href` (`statuses/show.blade.php:64`)
- `$account->avatar_url`, `$trackedAccount->avatar_url`, `$status->boost_data_json['author_avatar']` → `src` (multiple views)

**Risk:** A malicious Mastodon instance could return `javascript:alert(1)` or `data:text/html,...` as a URL value. Blade's `{{ }}` escaping will HTML-encode `<>&"` but does **not** prevent `javascript:` URI schemes. Clicking such a link would execute JavaScript in the user's browser (XSS).

For `src` attributes (avatar images), a `data:` URI or a URL to an attacker-controlled server can be used for tracking pixel attacks or CSP bypass. Additionally, HTTP avatar URLs served over a mixed-content HTTP page would trigger browser warnings if the app ever moves to HTTPS.

**Recommendation:** Validate that stored URLs start with `https://` before use. This can be done at storage time in the job (`SyncTrackedAccountStatusesJob`) or at render time with a Blade helper. At minimum, validate in the service layer that all returned URL fields pass `filter_var($url, FILTER_VALIDATE_URL)` and start with `https://`.

---

### FINDING 7 — `addslashes()` used for JavaScript context escaping
**Severity: MEDIUM**  
**File:** `resources/views/tracked-accounts/index.blade.php:57`

```php
onclick="openRemoveModal(this.closest('form'), '{{ addslashes($account->display_name ?: $account->username) }}')"
```

`addslashes()` escapes `'`, `"`, `\` and null bytes — but it is not sufficient for HTML+JavaScript context injection. A display name containing `</script>` or sequences that break out of the attribute context is not handled. More critically, `addslashes()` does not encode HTML entities, so an account with a display name like `' onmouseover='alert(1)` would break the onclick argument even after Blade's outer `{{ }}` escaping (which runs after `addslashes` here, so only the HTML layer is escaped, not the JS string layer).

**Risk:** Stored XSS if a Mastodon account's display name contains crafted JavaScript-breaking characters. The attack surface is limited to the logged-in user's own session (only they see their tracked accounts), but it is still a logic vulnerability.

**Recommendation:** Use `json_encode()` instead of `addslashes()` for embedding PHP values into JavaScript context:

```php
onclick="openRemoveModal(this.closest('form'), {{ json_encode($account->display_name ?: $account->username) }})"
```

Or better, use a `data-*` attribute and read it in JS, avoiding inline onclick entirely:

```html
data-account-name="{{ $account->display_name ?: $account->username }}"
```

---

### FINDING 8 — `html_entity_decode(strip_tags(...))` — fragile XSS mitigation
**Severity: MEDIUM**  
**Files:**  
- `resources/views/tracked-accounts/archive.blade.php:89`  
- `resources/views/tracked-accounts/show.blade.php:335`  
- `resources/views/statuses/show.blade.php:78`

```php
{{ html_entity_decode(strip_tags($status->content_html)) }}
```

The pattern `strip_tags()` → `html_entity_decode()` → Blade `{{ }}` (re-encode) is applied to HTML content from a Mastodon post's `content` field (which is `<p>Hello <a href="...">world</a></p>` style HTML).

The specific concern is `html_entity_decode()` called on already-stripped content: after `strip_tags` removes tags, encoded entities like `&lt;script&gt;` become literal `<script>`. Blade's `{{ }}` will then re-encode those, so in this specific chained case XSS is ultimately prevented by the final `{{ }}` encoding. However, this is a fragile and confusing pattern that relies on the final Blade encoding being the last step.

**Risk:** Low in current implementation but dangerous if the pattern is copied elsewhere and `{{ }}` is accidentally changed to `{!! !!}`. Also, `html_entity_decode()` converts `&amp;`, `&quot;`, `&lt;`, `&gt;` back to raw characters, meaning the displayed text will contain unencoded `<`, `>`, `"` etc. before Blade re-encodes. This is actually correct for plain-text display but the code is unintuitive and the intent is unclear to future maintainers.

**Recommendation:** Use `strip_tags()` alone (without `html_entity_decode`) for text extraction. The HTML entity characters (`&amp;Name&amp;`) are already rendered correctly by the browser when output through `{{ }}` — no decoding step is needed:

```php
{{ strip_tags($status->content_html) }}
```

If proper HTML entity rendering (showing `&` as `&` rather than `&amp;amp;`) is needed, use `html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')` — but this is cosmetic not a security change.

---

### FINDING 9 — SSRF: `instance_domain` not validated before outbound HTTP requests
**Severity: MEDIUM**  
**File:** `app/Services/MastodonApiService.php`, `app/Services/WebFingerService.php`

The `instance_domain` from a user-supplied Mastodon handle is stored in the database and later used to construct outbound HTTP requests:

```php
$url = "https://{$instanceDomain}/api/v1/accounts/lookup";
$url = "https://{$instanceDomain}/api/v1/accounts/{$remoteAccountId}/statuses";
$url = "https://{$instanceDomain}/.well-known/webfinger";
```

**Input validation at entry time:** The `WebFingerService::parseHandle()` regex validates the domain format as `/^[a-zA-Z0-9._-]+\.[a-zA-Z]{2,}$/` — this prevents obvious bypasses like `localhost`, `127.0.0.1`, `[::1]`, etc. **This is partially mitigated.**

**Remaining risk:** The regex allows:
- `169.254.169.254.example.com` — a valid domain format that could resolve to AWS metadata
- Any subdomain of a legitimate TLD that could internally resolve to a private IP (DNS rebinding)
- Domain names that resolve to private RFC-1918 addresses (10.x, 172.16-31.x, 192.168.x) or localhost via crafted DNS
- The stored `instance_domain` is never re-validated when used in background jobs — a value stored before validation was tightened persists

**Recommendation:** Validate at HTTP request time (not just at input parsing time) that the resolved IP is not in a private/loopback range. Laravel's `Http::withOptions(['curl' => [CURLOPT_RESOLVE => ...]])` or a custom middleware can block private IPs. At minimum, add `FILTER_VALIDATE_DOMAIN` checks and document the SSRF risk.

---

### FINDING 10 — `ALLOW_FRESH_MIGRATE=true` hardcoded in `docker-compose.yml`
**Severity: MEDIUM**  
**File:** `docker-compose.yml` line 13

```yaml
environment:
    - ALLOW_FRESH_MIGRATE=true
```

This flag in the `app` service bypasses the entrypoint's data-loss protection check. The check is designed to prevent running all migrations on a non-empty database that appears empty (e.g., after accidentally wiping the volume). Hardcoding `ALLOW_FRESH_MIGRATE=true` permanently disables this safeguard for every deployment.

**Risk:** If the MariaDB volume is accidentally deleted and the container restarts, the entrypoint will proceed with a full migration without warning instead of halting and alerting the operator.

**Recommendation:** Remove `ALLOW_FRESH_MIGRATE=true` from `docker-compose.yml`. Document in the README that users should set this environment variable manually only for a fresh first install.

---

### FINDING 11 — Session cookie `secure` flag not set
**Severity: MEDIUM**  
**File:** `config/session.php`, `.env.example`

```php
'secure' => env('SESSION_SECURE_COOKIE'),
```

`SESSION_SECURE_COOKIE` is not set in `.env.example`, so it defaults to `null` (falsy). Session cookies are therefore sent over both HTTP and HTTPS.

**Risk:** If the application is accessed over HTTP at any point (e.g., before HTTPS redirect), the session cookie is transmitted in plaintext and susceptible to network interception.

**Recommendation:** Set `SESSION_SECURE_COOKIE=true` in `.env.example` for production deployments. Add a note that this should only be `false` during local HTTP development. Alternatively, conditionally set it based on `APP_ENV`:

```php
'secure' => env('SESSION_SECURE_COOKIE', app()->isProduction()),
```

---

### FINDING 12 — No Content Security Policy
**Severity: MEDIUM**  
**Files:** `docker/nginx/http.conf.template`, `docker/nginx/https.conf.template`

There is no `Content-Security-Policy` header in the Nginx config or Laravel middleware. This means:

- External avatar images are loaded from arbitrary Mastodon instance domains (no `img-src` restriction)
- Chart.js and Alpine.js are loaded from `/build/` but there is no policy preventing injection of additional scripts
- Inline styles are used (Tailwind generates them) — a CSP would need `unsafe-inline` for styles, which is acceptable
- No `frame-ancestors` directive to restrict embedding

**Risk:** Without CSP, any XSS vulnerability (including the ones noted above) has maximum impact. CSP provides defense-in-depth.

**Recommendation:** Implement a CSP. A reasonable starting point for MastoLens:

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';" always;
```

Note: `img-src https:` is needed because avatar images are loaded from any Mastodon instance. Tightening this would require proxying avatar images through the server.

---

### FINDING 13 — Open registration enabled by default
**Severity: LOW**  
**File:** `.env.example` line 22, `config/fortify.php` line 147

```
REGISTRATION_ENABLED=true
```

MastoLens is designed as a personal/small-team tool but ships with open registration enabled. Anyone who finds the URL can create an account and begin tracking Mastodon accounts.

**Risk:** Spam registrations, resource abuse (each tracked account triggers periodic API calls to external Mastodon instances), and unintended data collection.

**Recommendation:** Change the default to `REGISTRATION_ENABLED=false` and require operators to explicitly enable it. The README already documents how to create users via CLI. This is a deployment decision but the default is wrong for a personal analytics tool.

---

### FINDING 14 — `SESSION_ENCRYPT=false` default
**Severity: LOW**  
**File:** `.env.example` line 41, `config/session.php`

Session data is stored in the database unencrypted. Session records contain the Laravel session payload including authentication state.

**Risk:** If an attacker gains read access to the `sessions` table (e.g., via SQL injection elsewhere or database backup exposure), they can inspect session contents. Session data in Laravel is serialized PHP; unencrypted exposure could leak user context.

**Recommendation:** Enable session encryption: `SESSION_ENCRYPT=true`. There is minimal performance cost since sessions are small and Laravel handles this transparently.

---

### FINDING 15 — MariaDB data not encrypted at rest
**Severity: LOW**  
**File:** `docker-compose.yml`

The MariaDB data volume is a plain bind mount to `./docker/data/mariadb`. No encryption-at-rest is configured at the MariaDB level or the filesystem level.

**Risk:** If a server is physically compromised or a backup is stolen, all tracked account data, status content, metric history, and user credentials (hashed with bcrypt, so not directly recoverable, but the email addresses are plaintext) are exposed.

**Recommendation:** Enable MariaDB InnoDB transparent encryption (`innodb_encrypt_tables=ON`) or use a host-level encrypted filesystem. For Docker deployments, consider using Docker named volumes with encrypted backing storage.

---

### FINDING 16 — No rate limiting on tracked-account creation
**Severity: LOW**  
**File:** `routes/web.php`, `app/Http/Controllers/TrackedAccountController.php`

The `POST /tracked-accounts` route (add a tracked account) has no rate limiting. Each account creation triggers:
1. A WebFinger DNS + HTTP request to the target instance
2. A Mastodon API lookup HTTP request
3. A background `SyncTrackedAccountStatusesJob` (which makes 2 more external HTTP requests)

**Risk:** An authenticated user can create hundreds of tracked accounts in rapid succession, causing a large number of outbound HTTP requests to third-party Mastodon instances. This could be interpreted as a DDoS tool and get the server's IP banned. It also allows one user to consume all queue worker capacity.

**Recommendation:** Add a per-user rate limit on account creation (e.g., 10 per hour) and/or a maximum tracked account count per user. Use Laravel's `RateLimiter` facade in the controller or route definition.

---

### FINDING 17 — `composer:latest` base image is mutable
**Severity: LOW**  
**File:** `Dockerfile` line 7

```dockerfile
FROM composer:latest AS vendor
```

`composer:latest` resolves to whatever the latest tag is at build time. This is a mutable reference — the image can change between builds, introducing supply chain risk.

**Risk:** A compromised Composer image (or an unintended breaking change) would silently affect all future builds.

**Recommendation:** Pin to a specific version tag: `FROM composer:2.8` (current stable as of April 2026). Also consider pinning by image digest (`@sha256:...`) for maximum reproducibility.

---

### FINDING 18 — `php:8.4-fpm-alpine` and `nginx:alpine` not pinned by digest
**Severity: LOW**  
**Files:** `Dockerfile` lines 12, 71

```dockerfile
FROM php:8.4-fpm-alpine AS app
FROM nginx:alpine AS web
```

These are floating tags. `nginx:alpine` always points to the latest Alpine-based Nginx image and can change. `php:8.4-fpm-alpine` is more stable (tied to PHP 8.4.x) but still updates with patch releases.

**Risk:** An upstream image change (security patch or regression) silently applies on next build. In the case of a supply chain attack on the upstream Docker Hub images, malicious code would enter the build.

**Recommendation:** Pin to specific patch versions: `php:8.4.6-fpm-alpine3.21`, `nginx:1.27.4-alpine`. For critical security, also pin by digest.

---

## Dependency Versions — PHP (composer.lock)

All production PHP dependencies as of audit date. **No known CVEs identified** in any of these versions.

| Package | Installed | Notes |
|---------|-----------|-------|
| `laravel/framework` | v13.5.0 | Current. Latest is v13.x — check for patch releases. |
| `laravel/fortify` | v1.36.2 | Current. |
| `guzzlehttp/guzzle` | 7.10.0 | Current stable. |
| `symfony/*` | v8.0.8 | All Symfony components at same version. Current LTS. |
| `pragmarx/google2fa` | v9.0.0 | Current. |
| `league/commonmark` | 2.8.2 | Current. |
| `bacon/bacon-qr-code` | v3.1.1 | Current. |
| `ramsey/uuid` | 4.9.2 | Current. |
| `nesbot/carbon` | 3.11.4 | Current. |
| `vlucas/phpdotenv` | v5.6.3 | Current. |
| `monolog/monolog` | 3.10.0 | Current. |

**Observation:** All packages appear to be at or near their latest stable versions. No historically vulnerable versions detected.

---

## Dependency Versions — JavaScript (package-lock.json)

| Package | Installed | Notes |
|---------|-----------|-------|
| `alpinejs` | 3.14.x | Current stable. No known CVEs. |
| `chart.js` | 4.5.1 | Current stable. No known CVEs. |
| `vite` | 8.0.x | Current. Recent major release. |
| `tailwindcss` | 4.2.2 | Current v4. |
| `@tailwindcss/vite` | 4.2.2 | Current. |
| `laravel-vite-plugin` | 3.0.x | Current. |
| `concurrently` | 9.x | Dev only. No known CVEs. |

**Note:** All JS dependencies are **devDependencies** (build tools) or minimal runtime (`alpinejs`, `chart.js`). None of these packages are known to have active CVEs. Vite and Rolldown (used internally by Vite 8) are being actively developed; monitor for security patches.

---

## Architecture Observations (No CVE, Good Practices Noted)

| Item | Assessment |
|------|-----------|
| SQL injection | **Not found.** All database queries use Laravel Eloquent or the query builder with bound parameters. No raw string interpolation into SQL found. |
| CSRF protection | **Present.** Laravel's `VerifyCsrfToken` middleware is active by default for all non-API web routes. All forms in Blade templates use `@csrf`. |
| Authentication | **Solid.** Fortify provides bcrypt-hashed passwords (`BCRYPT_ROUNDS=12`), rate-limited login (5/min), TOTP two-factor authentication. |
| Authorization | **Consistent.** Every controller checks `user_id` ownership before accessing `TrackedAccount` or `Status` resources and returns `abort(403)` if mismatched. No IDOR vulnerabilities found. |
| Password rules | **Good.** `PasswordValidationRules` trait enforced on registration and password changes. |
| Mass assignment | **Good.** All models use explicit `$fillable` lists. The `TrackedAccount` and `Status` models are not using `guarded = []`. |
| Job queue isolation | **Good.** `ShouldBeUnique` implemented on both `SyncTrackedAccountStatusesJob` and `CaptureStatusSnapshotJob` preventing duplicate concurrent runs. |
| `.env` in git | **Not committed.** `.gitignore` correctly excludes `.env`, `.env.backup`, `.env.production`. |
| `vendor/` in git | **Not committed.** `.gitignore` excludes `vendor/`. |
| Nginx dotfile protection | **Present.** `location ~ /\.(?!well-known).*` blocks access to `.env`, `.git`, etc. |
| PHP version | **PHP 8.4** — current supported release. |
| TLS config | **Good when enabled.** `TLSv1.2 TLSv1.3` only, `ssl_prefer_server_ciphers on`, no weak ciphers. |
| `X-Powered-By` header | **Removed.** `fastcgi_hide_header X-Powered-By` in Nginx config. |
| Error handling | Jobs catch `\Throwable`, log to DB, and re-throw. No secret leakage in job error messages. |

---

## Priority Remediation Order

1. **(HIGH)** Fix scheduler root execution — use non-root user
2. **(HIGH)** Change `APP_DEBUG=false` in `.env.example`
3. **(HIGH)** Replace weak default passwords with `CHANGE_ME` placeholders
4. **(HIGH)** Restrict `trustProxies` to actual proxy IP range
5. **(MEDIUM)** Add HTTP security headers to Nginx configs
6. **(MEDIUM)** Validate URLs are `https://` before storing and rendering
7. **(MEDIUM)** Replace `addslashes()` with `json_encode()` for JS context
8. **(MEDIUM)** Remove `ALLOW_FRESH_MIGRATE=true` from `docker-compose.yml`
9. **(MEDIUM)** Add SSRF protection (IP blocklist for outbound HTTP)
10. **(MEDIUM)** Set `SESSION_SECURE_COOKIE=true` in production
11. **(LOW)** Add per-user rate limit on account creation
12. **(LOW)** Default `REGISTRATION_ENABLED=false`
13. **(LOW)** Enable `SESSION_ENCRYPT=true`
14. **(LOW)** Pin Docker base images to specific versions/digests

---

*End of audit. No changes were made to any application files.*
