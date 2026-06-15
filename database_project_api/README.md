# Database Project API

Laravel API for the separated classroom reservation system.

Deployment commands are documented in `../DEPLOYMENT.md`.

## Run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Set `FRONTEND_URLS` in `.env` if the frontend runs somewhere other than:

```env
FRONTEND_URLS=http://localhost:5173,http://127.0.0.1:5173
```

## Separation TODO

- [x] Copy Laravel backend into `database_project_api`.
- [x] Move browser-facing routes under `/api`.
- [x] Convert Inertia page endpoints to JSON responses.
- [x] Allow frontend origin CORS credentials.
- [x] Exclude `/api/*` from CSRF validation for the first separated session-auth version.
- [x] Remove unused Inertia dependency/imports after all endpoints are confirmed JSON-only.
- [x] Add feature tests for login, reservation creation, room management, payment, and approval endpoints.
- [x] Update audited Composer dependencies with security advisories.
- [ ] Consider Laravel Sanctum token auth if this API must serve mobile apps or third-party clients.
