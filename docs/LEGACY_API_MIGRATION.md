# Legacy API to Router Migration Guide

## Overview
- This guide helps migrate from the legacy `api/index.php` routing to the unified `api.php` entry that dispatches via `CSIMS\API\Router`.
- The unified approach improves consistency, dependency injection, and maintainability across all endpoints.

## What Changed
- API entry unified: all `/api/*` requests go to `api.php`.
- Routing centralized in `src/API/Router.php`.
- Services resolved via DI in `src/bootstrap.php` (e.g., `ConfigurationManager`, `AuthService`, `LoanService`).
- Dev server routing handled by `dev-router.php`.

## Endpoint Mapping
- Auth
  - `POST /auth/login` → `POST /api/auth/login`
  - `POST /auth/logout` → `POST /api/auth/logout`
  - New: `GET /api/auth/me` (current user)
- Members
  - `GET /api/members` → unchanged
  - `GET /api/members/{id}` → unchanged
  - New: `GET /api/members/search`
  - New: `GET /api/members/{id}/summary`
- Loans
  - `GET /api/loans` → unchanged
  - `POST /api/loans` → unchanged
  - `GET /api/loans/{id}` → unchanged
- Contributions
  - `GET /api/contributions/*` → legacy; may be disabled under Router

## Migration Steps
1. Web server rewrites
   - Apache: ensure `.htaccess` rewrites `/api/*` to `api.php`.
   - Nginx: route `/api/*` to `api.php` using `try_files`.
2. Dev server
   - Start with `php -S 127.0.0.1:8080 dev-router.php` to forward `/api/*` to `api.php`.
3. Client code updates
   - Update base URL to use `/api/...` paths; replace any `/auth/*` without `/api` prefix.
   - Remove `user_type` from login requests; use `username` and `password` (optionally `two_factor_code`).
4. Dependency injection
   - Confirm bindings in `src/bootstrap.php` for `ConfigurationManager`, `AuthService`, repositories, and services.
5. Testing
   - Health: `GET /api/system/health`
   - Auth: `POST /api/auth/login`, then `GET /api/auth/me`
   - Members: `GET /api/members`, `GET /api/members/search`
   - Loans: `GET /api/loans`, `POST /api/loans`

## Common Pitfalls
- Missing rewrites to `api.php` causing 404s.
- CORS/headers differing from legacy handlers; ensure `Content-Type: application/json` and proper CORS if needed.
- Session handling changes; verify session cookie path and regeneration.
- CSRF token required for state-changing operations (POST/PUT/DELETE).

## Example Client Changes
- JavaScript login
```
await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ username: 'admin', password: 'Admin123!' })
});
```

- PHP cURL login
```
$ch = curl_init('http://127.0.0.1:8080/api/auth/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'username' => $username,
  'password' => $password
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
```

## Legacy Coexistence
- If legacy endpoints must remain available, bind legacy controllers temporarily and plan deprecation.
- Prefer migrating clients to Router-managed endpoints for consistency and future maintenance.

## See Also
- `MIGRATION_CHECKLIST.md` — Detailed verification steps to validate clients and endpoints post-migration.

## Automated Smoke Tests

To quickly verify the unified API after migration, run the automated smoke tests script:

- Prerequisites: PHP CLI installed; dev server running (`php -S 127.0.0.1:8080 dev-router.php`) or your web server configured to route `/api/*` to `api.php`.
- Usage:

```
php scripts/migration_smoke_tests.php [base_url] [--write] [--username USER] [--password PASS]
```

- Examples:
  - Basic health and read-only checks (default to `http://127.0.0.1:8080`):
    - `php scripts/migration_smoke_tests.php`
  - Against a custom base URL:
    - `php scripts/migration_smoke_tests.php http://localhost:8000`
  - Include login and session-based checks:
    - `php scripts/migration_smoke_tests.php http://localhost:8000 --username admin --password Admin123!`
  - Attempt write tests (may require CSRF/session and will modify data):
    - `php scripts/migration_smoke_tests.php http://localhost:8000 --write --username admin --password Admin123!`

The script verifies:
- `/api/system/health` returns status 200 and `{"status":"OK"}`.
- Optional login (`/api/auth/login`) using provided credentials and `GET /api/auth/me`.
- Read endpoints for members and loans (`/api/members`, `/api/members/search`, `/api/loans`).
- Optional write endpoint for creating loans when `--write` is enabled.

Exit codes:
- `0` when all checks pass
- `2` when one or more checks fail