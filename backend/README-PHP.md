# CENUSIS Operations, PHP Backend

PHP 8.2+ backend re-implementing the old Node/TS (Express + enders-sync)
backend with an identical wire protocol, backed by MySQL (PDO, raw SQL).

## Running locally (without Docker)

Requirements: PHP 8.2+ with `pdo_mysql`, `json`, `mbstring` extensions.

```sh
cd backend
composer install        # generates vendor/autoload.php
php -S localhost:4000 public/index.php
```

If `composer` is not installed, `public/index.php` falls back to a minimal
PSR-4 autoloader for the `Cenusis\` namespace (helpers are required
explicitly), so the built-in server still works. PhpSpreadsheet-dependent
features (xlsx routes) require composer.

Environment variables (see root `.env.example`):

- `DB_HOST` (default `mysql`), `DB_PORT` (3306), `DB_NAME` (`cenusis_ops`),
  `DB_USER` (`dev`), `DB_PASSWORD` (`dev123456`)
- `JWT_SECRET`
- `NODE_ENV` (production enables the `secure` cookie flag)

## Running with Docker

From the repo root:

```sh
cp .env.example .env   # edit values
docker compose up --build
```

The backend container runs PHP-FPM with nginx (port 4000 exposed),
front controller `public/index.php`.

## Structure

- `public/index.php`, front controller; mounts the four RPC endpoints and
  the xlsx routes hook (`routes/students_xlsx.php`).
- `src/App.php`, tiny router (RPC mounts + `:param` GET/POST routes).
- `src/Rpc/Rpc.php`, enders-sync wire-protocol clone:
  - `GET {path}/discover` → JSON array of registered function names
  - `POST {path}/call` with `{method, params[]}` → `{success, data?, error?}`
  - HTTP 200 even for RPC errors; `400 {"error":"Invalid JSON"}` for
    empty/invalid bodies; handler exceptions become `{success:false, error}`.
- `src/Auth/Jwt.php`, pure-PHP HS256 JWT (hash_hmac + base64url).
- `src/Auth/Auth.php`, validators (`public|admin|superadmin|teacher`),
  `login` / `logout` / `getAccountInfo` RPC functions and the NoRPC
  helpers, ported from `backend/src/auth.ts`.
- `src/Db/Db.php`, PDO MySQL singleton: `query`, `first`, `execute`
  (returns stmt + lastInsertId), `transaction`.
- `src/helpers/`, ports of `normalize_arabic`, `validate_params`,
  `fts_sanatize`, `headers_translate`.

## RPC endpoints

Frontend (React) talks to:

- `/api/public`, no auth (`login`, `logout`)
- `/api/admin`, cookie JWT with `role: "admin"` (`getAccountInfo`)
- `/api/superadmin`, role `superadmin` (`getAccountInfo`, `logout`)
- `/api/teacher`, role `teacher` (`getAccountInfo`, `logout`)

The auth token is a HS256 JWT stored in the `auth-token` cookie
(httpOnly, sameSite=lax, secure only in production, 1 day expiry,
issuer `<role>-auth-service`, audience `<role>`).
