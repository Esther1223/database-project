# Database Project Frontend

Standalone Vite/Vue frontend for the classroom reservation system.

Deployment commands are documented in `../DEPLOYMENT.md`.

## Run

```bash
npm install
npm run dev
```

The frontend expects the Laravel API at `http://127.0.0.1:8000` by default. Override it with:

```env
VITE_API_URL=http://127.0.0.1:8000
```

## Separation TODO

- [x] Copy Vue pages and components into `database_project_front`.
- [x] Replace Inertia bootstrap with a standalone Vue Router app.
- [x] Add axios API client with cookie credentials.
- [x] Replace Inertia `Head`, `Link`, and `router` usage with local `HeadTitle` and Vue Router.
- [x] Add API-backed page loader for prop-based pages.
- [x] Replace remaining full-page `href` navigation with Vue Router navigation.
- [x] Remove the Inertia compatibility shim.
- [x] Add route-level role checks and unauthorized/not-found screens.
- [ ] Add loading and empty states to every API-backed page.
- [ ] Add frontend tests for login, room browsing, reservation flow, and admin flows.
