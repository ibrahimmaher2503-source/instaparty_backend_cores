# Quickstart: Phase 0 — Foundation

**Date**: 2026-04-26
**Target**: Developer with a fresh clone of `instaparty_backend_cores`

---

## Prerequisites

- Docker Desktop (Windows/Mac) or Docker Engine + Compose Plugin (Linux)
- PHP 8.3+ locally (for Composer and Artisan — can skip if using Docker exec)
- Composer 2.x
- Git

---

## Step 1 — Clone and install

```bash
git clone <repo-url> instaparty_backend_cores
cd instaparty_backend_cores

composer install
cp .env.example .env
php artisan key:generate
```

---

## Step 2 — Start the Docker stack

```bash
docker compose up -d
```

Services started:
| Service | Port | Purpose |
|---|---|---|
| `app` | 8000 | Laravel + Octane |
| `mysql` | 3306 | MySQL 8 (data persisted in named volume) |
| `redis` | 6379 | Cache / queue / sessions |
| `meilisearch` | 7700 | Search (not used until Phase 3) |
| `mailpit` | 8025 | Email preview UI |
| `minio` | 9000 / 9001 | Object storage (console at :9001) |

---

## Step 3 — Bootstrap the dev environment

```bash
php artisan app:setup-dev-env
```

This idempotent command:
- Creates the MinIO bucket (`instaparty-dev`)
- Verifies Meilisearch connectivity
- Reports any missing services

---

## Step 4 — Run migrations and seed

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=EgyptGeographySeeder
```

Or run all at once:

```bash
php artisan migrate --seed
```

(Only works if `DatabaseSeeder` calls all four seeders in dependency order.)

---

## Step 5 — Access the admin panel

Open: [http://localhost:8000/admin](http://localhost:8000/admin)

Default admin credentials (set in `AdminUserSeeder`):
- **Email**: `admin@instaparty.local`
- **Password**: `password` *(change immediately on staging)*

---

## Step 6 — Verify Geography

1. Click **Geography > Governorates** in the sidebar.
2. Confirm Egypt's governorates appear with English names.
3. Click the language switcher (top bar) → switch to **العربية**.
4. Confirm Arabic names render correctly.

---

## Common commands

```bash
# Run the test suite
./vendor/bin/pest

# Run only Geography tests
./vendor/bin/pest --group=geography

# Run only migration smoke tests
./vendor/bin/pest --group=migrations

# Format code
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Generate Filament Shield permissions (run after adding a new Resource)
php artisan shield:generate --all

# Clear all caches
php artisan optimize:clear
```

---

## Troubleshooting

### Migrations fail with charset error
Ensure `DB_CHARSET=utf8mb4` and `DB_COLLATION=utf8mb4_unicode_ci` are set in `.env`.

### MinIO bucket not found
Run `php artisan app:setup-dev-env` to create the bucket. The MinIO console is at [http://localhost:9001](http://localhost:9001) (credentials in `.env`).

### Filament Resources not appearing
Run `php artisan filament:cache-components` and `php artisan optimize:clear`. Confirm the module's ServiceProvider is registered in `bootstrap/providers.php`.

### Shield permissions missing
Run `php artisan shield:generate --all` after any new Filament Resource is added.

---

## CI / Staging

CI runs on every push via `.github/workflows/ci.yml`. Credentials are provided via GitHub Actions Secrets — never commit them.

Staging deploy: `https://staging.instaparty.com/admin` (Hetzner CX22 + Caddy + Docker Compose).
