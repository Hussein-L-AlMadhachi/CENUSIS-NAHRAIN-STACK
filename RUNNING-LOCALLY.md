# Running CENUSIS-Ops Locally (PHP + MySQL)

This guide runs the full stack on your machine: MariaDB (via Docker), the PHP backend, and the React frontend with Vite.

> The PHP backend implements the same `enders-sync` RPC wire protocol as the old Node backend, so the frontend needs no changes.

## Prerequisites

- PHP 8.2+ with these extensions: `pdo_mysql`, `zip`, `gd`, `mbstring`, `xml`
  (check with `php -m | grep -iE 'pdo_mysql|zip|gd|mbstring|xml'`)
- Docker (for MariaDB)
- Node + npm, or Bun (for the frontend)

## 1. Start MariaDB

```bash
sudo docker run --name mariadb-test -e MARIADB_ROOT_PASSWORD=123456 -p 3306:3306 -d mariadb:latest
```

If the container already exists but is stopped:

```bash
sudo docker start mariadb-test
```

## 2. Create the database schema (once)

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD=123456 DB_NAME=cenusis_ops \
php backend/cli/create.php
```

Expected output: all 12 tables `[OK]`, followed by `Tables created successfully`.

## 3. Create the admin accounts (once)

Create a file named `.default_accounts.json` in the repository root
(it is gitignored, so it stays local):

```json
{
    "admin": { "username": "admin", "password": "change-me-123" },
    "superadmin": { "username": "superadmin", "password": "change-me-123" }
}
```

Then run:

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD=123456 DB_NAME=cenusis_ops \
php backend/cli/admin.php
```

Expected output:

```
 [DONE]  ADMIN user is ready (id:X)
 [DONE]  SUPERADMIN user is ready (id:X)
```

> Passwords must be at least 8 characters.

## 4. Install backend dependencies (once)

```bash
cd backend
php composer.phar install
cd ..
```

(If `vendor/` already exists, you can skip this.)

## 5. Start the backend

Run from the **repository root** (the router path is relative, so the working
directory matters):

```bash
cd /home/hussein/Repos/CENUSIS-Operations

DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD=123456 DB_NAME=cenusis_ops \
JWT_SECRET=any-long-random-string \
php -S 0.0.0.0:3000 backend/public/index.php
```

If you prefer to run from inside `backend/`, use the path relative to it:

```bash
cd backend
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD=123456 DB_NAME=cenusis_ops \
JWT_SECRET=any-long-random-string \
php -S 0.0.0.0:3000 public/index.php
```

> Wrong directory + relative path gives a per-request fatal like
> `Failed opening required 'backend/public/index.php'` — that is a launch
> path issue, not a backend bug. Check the process cwd with
> `readlink /proc/$(pgrep -f 'php -S')/cwd` if in doubt.

Notes:

- `JWT_SECRET` is **required** — without it, login authenticates but fails with
  `Error creating token`.
- Defaults inside Docker are `DB_HOST=mysql`, so pass `DB_HOST=127.0.0.1` locally.
- The server listens on port 3000, which is what the frontend expects.

## 6. Sanity-check the backend

```bash
# should return ["login","logout"]
curl http://localhost:3000/api/public/discover

# should return {"success":false,"error":"Unauthorized"}
curl -X POST http://localhost:3000/api/public/call \
  -H 'Content-Type: application/json' \
  -d '{"method":"login","params":["admin","wrong-password"]}'

# should return {"success":true,"data":{...,"role":"admin"}}
curl -c /tmp/cookies.txt -X POST http://localhost:3000/api/public/call \
  -H 'Content-Type: application/json' \
  -d '{"method":"login","params":["admin","change-me-123"]}'
```

## 7. Start the frontend (separate terminal)

```bash
cd frontend
bun run dev     # or: npm run dev
```

Vite automatically proxies `/api/*` to `http://localhost:3000`
(already configured in `frontend/vite.config.ts`).

Open Vite's URL (usually `http://localhost:5173`) and log in with the admin
account from step 3.

## Environment variables reference

| Variable       | Local value        | Docker default | Purpose                        |
|----------------|--------------------|----------------|--------------------------------|
| `DB_HOST`      | `127.0.0.1`        | `mysql`        | Database host                  |
| `DB_PORT`      | `3306`             | `3306`         | Database port                  |
| `DB_NAME`      | `cenusis_ops`      | `cenusis_ops`  | Database name                  |
| `DB_USER`      | `root`             | `dev`          | Database user                  |
| `DB_PASSWORD`  | `123456`           | `dev123456`    | Database password              |
| `JWT_SECRET`   | any random string  | set in compose | JWT signing key (required)     |

## Schema changes / re-runs

- `php backend/cli/create.php` is idempotent (`CREATE TABLE IF NOT EXISTS`).
- `php backend/cli/alter.php` applies idempotent column alterations
  (checks `information_schema` before each `ADD COLUMN`).

## Running the test suites

```bash
# pure PHP tests (no DB needed)
php backend/test/php/rpc_protocol_test.php
php backend/test/wiring_smoke.php

# DB-backed tests
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD=123456 \
       DB_NAME=cenusis_ops JWT_SECRET=test-secret-key
php backend/test/core_features_smoke.php
php backend/test/ownership_idor_test.php

# HTTP integration suite (backend must be running on :3000)
BASE=http://127.0.0.1:3001 bash backend/test/integration.sh
```

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `could not find driver` | `pdo_mysql` extension missing, `sudo dnf install php-mysqlnd` |
| `Error creating token` on login | `JWT_SECRET` not set |
| `SQLSTATE[HY000] [1049] Unknown database` | run `php backend/cli/create.php` first |
| `SQLSTATE[HY000] [2002] ... mysql` | you forgot `DB_HOST=127.0.0.1` (defaults to the Docker hostname `mysql`) |
| Class `PhpOffice\...` not found on XLSX routes | run `php composer.phar install` in `backend/` |
| Port 3000 already in use | an old `php -S` instance, `pkill -f 'php -S'` |
