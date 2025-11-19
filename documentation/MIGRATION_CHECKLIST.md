# Migration Verification Checklist

Use this checklist to verify client applications after migrating from the legacy `api/index.php` routing to the unified `api.php` + Router architecture.

## Pre-Checks
- Confirm `.env` values (`APP_URL`, `APP_DEBUG`, DB settings).
- Ensure `src/bootstrap.php` binds `ConfigurationManager`, `AuthService`, repositories, and services.
- Verify `api.php` exists and is the sole API entry point.

## Web Server Routing
- Apache: `.htaccess` rewrites `/api/*` to `api.php`.
- Nginx: `location ~ ^/api/(.*)$ { try_files $uri /api.php; }`.
- Dev server: `php -S 127.0.0.1:8080 dev-router.php` forwards `/api/*` to `api.php`.

## System Health
- `GET /api/system/health` returns JSON with `status: OK`, `version`, and `timestamp`.

## Authentication Flow
- Login: `POST /api/auth/login` with `username` and `password` responds with success and session.
- Current user: `GET /api/auth/me` returns authenticated user details.
- Logout: `POST /api/auth/logout` clears session.
- Negative tests: invalid credentials → proper error and no session.

## Members Endpoints
- List: `GET /api/members` returns paginated results.
- By ID: `GET /api/members/{id}` returns a single member.
- Search: `GET /api/members/search?q=John&status=Active&page=1&limit=10` returns filtered results.
- Summary: `GET /api/members/{id}/summary` returns member details and loan summary.

## Loans Endpoints
- List: `GET /api/loans` returns paginated loans.
- Create: `POST /api/loans` with valid JSON creates a loan (include `csrf_token` if required).
- By ID: `GET /api/loans/{id}` returns loan details.
- Negative tests: invalid payloads or permissions → proper errors.

## Headers, CORS, and Content Type
- All responses have `Content-Type: application/json`.
- CORS (if applicable): preflight and allowed methods working.
- Security headers enabled (where configured).

## Sessions and CSRF
- Session cookie present on login; `session_regenerate_id` occurs appropriately.
- State-changing endpoints require `csrf_token` (unless disabled for testing).

## Error Handling
- Consistent error format: `{ success: false, error, message, errors }`.
- Server errors do not expose stack traces in production (`APP_DEBUG=false`).

## Legacy Endpoints
- Contributions: `/api/contributions/*` treated as legacy; either disabled or bound intentionally.
- Verify clients are updated to new endpoints; avoid mixed legacy/new usage.

## Rollback Plan
- Ability to temporarily re-enable legacy routing if needed.
- Backup of `.htaccess`/Nginx configs and client configs before changes.

## Sign-Off
- Auth: login/logout/me verified.
- Members: list/id/search/summary verified.
- Loans: list/create/id verified.
- Health: system health verified.
- Error handling and CSRF validated.
- Routing: rewrites confirmed across environments.