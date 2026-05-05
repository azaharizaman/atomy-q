# AGENTS.md — Atomy-Q

## Repo layout

- `API/` — Laravel 12 backend (PHP 8.3), 203 endpoints, JWT auth, multi-tenant
- `WEB/` — Next.js 16 frontend (all `'use client'`), React 19
- `openapi/openapi.json` — shared OpenAPI spec
- `docs/` — product, release, domain, engineering docs
- `design/Design-System-v2/` — design system showcase (not consumed directly)

## Developer commands

### Frontend (WEB/)

```bash
cd WEB
npm run dev              # dev server (port 3000)
npm run build            # production build (uses --webpack flag)
npm run lint             # ESLint
npm run test:unit        # Vitest run
npm run test:unit:watch  # Vitest watch
npm run test:e2e         # Playwright (auto-starts server on port 3100)
npm run test:e2e:ui      # Playwright UI mode
npm run generate:api     # regenerate OpenAPI client → src/generated/api/
```

### Backend (API/)

```bash
cd API
composer run dev         # Laravel serve + queue + logs + WEB dev (concurrently)
composer run test        # php artisan test
```

## Architecture facts an agent would miss

### API client — two paths, not one

- **Primary**: `WEB/src/lib/api.ts` — custom Axios instance with JWT Bearer injection, auto-refresh on 401, idempotency-key headers for RFQ/quote/project/task POSTs
- **Generated**: `@hey-api/openapi-ts` generates into `WEB/src/generated/api/` — used only by some hooks (vendors, users, create-rfq-line-item)
- Most hooks bypass the generated SDK and call Axios directly via `fetchLiveOrFail()` in `WEB/src/lib/api-live.ts`
- API base URL from `NEXT_PUBLIC_API_URL` (must end with `/api/v1`, no trailing slash)

### Auth — JWT in memory + refresh in localStorage

- **Access token**: Zustand store, NOT persisted (short-lived)
- **Refresh token**: Zustand `persist` middleware → localStorage
- Login uses raw `fetch`, not the Axios instance
- Auto-refresh via Axios interceptor: on 401 → POST `/auth/refresh` → retry once → redirect to `/login` on failure
- No cross-tab sync implemented

### Design system — custom, NOT Radix or shadcn

- Components in `WEB/src/components/ds/` — all custom-built
- Only `@radix-ui/react-slot` installed (as a primitive), NOT full Radix UI
- Tokens in `WEB/src/lib/tokens.ts` — semantic aliases to Tailwind classes, NOT runtime values
- Variants via `class-variance-authority` (cva)

### Tables — custom DataTable, NOT TanStack Table

- `WEB/src/components/ds/DataTable.tsx` — supports sorting, selection, expandable rows, bulk actions
- Column definition type: `ColumnDef<T>`

### Styling — Tailwind CSS v4

- Uses new v4 syntax: `@import "tailwindcss"` + `@theme inline` in `globals.css`
- No CSS Modules, no styled-components
- Font: Avenir Next with system fallbacks

### Routing

- Route groups: `(auth)/` for public pages, `(dashboard)/` for authenticated pages
- RFQ workspace paths (`/rfqs/[rfqId]/...`) bypass the default sidebar layout — workspace layout owned by `rfqs/[rfqId]` layout file
- Feature flags guard `/projects` and `/tasks` routes
- All auth guard logic in `(dashboard)/layout.tsx`

### No charts installed

- Dashboard uses stat cards + CSS progress bars
- `/reporting` shows "Coming Soon"
- If adding charts, Recharts is the planned choice (see PLAN.md)

### Environment

- `NEXT_PUBLIC_USE_MOCKS=false` required for staging/release validation
- `NEXT_PUBLIC_AI_MODE=off` by default; `provider` = live AI, `deterministic` = diagnostic fallback
- Default API URL: `http://localhost:8001/api/v1` (dev), `http://localhost:8000/api/v1` (Playwright)
- Playwright uses port 3100 to avoid conflicting with dev on 3000
- Playwright defaults: `E2E_EMAIL=user1@example.com`, `E2E_PASSWORD=secret`

### Testing

- Unit: Vitest (`WEB/src/` co-located `*.test.ts`/`*.test.tsx`)
- E2E: Playwright (`WEB/tests/`)
- No MSW setup yet for query hook testing
- Live tests exist for some hooks (`*.live.test.ts`) — require running API backend
- `HOOK_TEST_STANDARD.md` in `WEB/docs/` defines testing expectations for hooks

### Idempotency

- POST paths requiring `Idempotency-Key` header: `rfqs`, `rfqs/bulk-action`, `rfqs/{id}/duplicate`, `rfqs/{id}/invitations`, `rfqs/{id}/invitations/{id}/remind`, `rfq-templates`, `rfq-templates/{id}/duplicate`, `rfq-templates/{id}/apply`, `quote-submissions/{id}/source-lines`, `projects`, `tasks`
- Key generated via `crypto.randomUUID()` if not already present

## Working conventions

- Server state exclusively in TanStack React Query (`hooks/`)
- Client state only in Zustand (`store/`) — currently only auth store
- Forms use React Hook Form + Zod v4, wired to custom DS inputs via `form.register()`
- Zod v4 (`zod@^4.3.6`) — import syntax differs from Zod v3
- Toasts via Sonner (`toast.success()`, `toast.error()`, `toast.info()`)
- Icons: `lucide-react` only

## Related docs

- `PLAN.md` — actual frontend stack (this repo)
- `docs/CURRENT_STATE.md` — current product posture and alpha constraints
- `API_ENDPOINTS.md` — API contract reference
- `API/IMPLEMENTATION_SUMMARY.md` — backend implementation details
- `WEB/docs/HOOK_TEST_STANDARD.md` — hook testing expectations
- `WEB/TESTING.md` — frontend testing guide
