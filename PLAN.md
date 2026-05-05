# Atomy-Q Frontend Stack Plan — Actual

This document describes the **actual** frontend stack and implementation in the Atomy-Q application. It reflects what is currently in use, not what was originally proposed. The backend is a **Laravel API** (203 endpoints, JWT auth, multi-tenant) served from the sibling `API/` directory.

---

## 1. Stack Overview (Actual)

| Layer | Choice | Package / Version | Notes |
|-------|--------|-------------------|-------|
| **Framework** | Next.js 16 (App Router) | `next@16.2.3` | React 19.2.3; all pages are `'use client'` |
| **API client** | Axios (primary) + Hey API (partial) | `axios@^1.15.0` | Custom Axios instance at `lib/api.ts`; OpenAPI-generated SDK in `generated/api/` used by some hooks |
| **Server state** | TanStack React Query | `@tanstack/react-query@^5.90.21` | All data fetching, caching, mutations |
| **Auth** | JWT Bearer + refresh token | Zustand persist middleware | Access token in memory; refresh via `/auth/refresh`; auto-retry on 401 via Axios interceptor |
| **Forms** | React Hook Form + Zod | `react-hook-form@^7.71.2`, `zod@^4.3.6` | `zodResolver` bridge; custom DS inputs wired via `form.register()` |
| **UI** | Custom design system (`ds/`) | — | NOT Radix; custom-built Button, Input, DataTable, Card, Badge, Tabs, etc. |
| **Styling** | Tailwind CSS v4 | `tailwindcss@^4` | `@theme inline` tokens in `globals.css`; semantic tokens in `lib/tokens.ts` |
| **Style utils** | CVA + clsx + tailwind-merge | `class-variance-authority@^0.7.1` | Component variant composition |
| **Icons** | Lucide React | `lucide-react@^0.577.0` | Single icon library |
| **Client state** | Zustand | `zustand@^5.0.11` | Auth state only (`use-auth-store.ts`) with localStorage persistence |
| **Tables** | Custom DataTable | — | NOT TanStack Table; custom `ds/DataTable.tsx` with sorting, selection, expansion, bulk actions |
| **Toasts** | Sonner | `sonner@^2.0.7` | `<Toaster />` in root layout |
| **Charts** | None installed | — | Dashboard uses stat cards + CSS progress bars; reporting page shows "Coming Soon" |
| **Testing** | Vitest + Playwright | `vitest@^3.2.4`, `@playwright/test@^1.58.2` | Unit + E2E |

---

## 2. Architecture Details

### Framework: Next.js 16 App Router

- Route groups: `(auth)/` for login/register/forgot-password/reset-password, `(dashboard)/` for authenticated routes
- Dynamic routes: `[rfqId]`, `[projectId]`, `[vendorId]`, `[approvalId]`, `[quoteId]`, `[runId]`, `[section]`
- All pages use `'use client'` directive (client-side rendering throughout)
- `Suspense` boundaries wrap navigation hooks in dashboard layout
- Client-side route guards in `(dashboard)/layout.tsx` redirect to `/login` when unauthenticated

### API Layer: Axios Primary + Hey API Generated (Partial)

- **Primary client**: Custom Axios instance (`lib/api.ts`)
  - Bearer token injection via request interceptor
  - Automatic JWT refresh on 401 (single retry, then redirect to login)
  - `withCredentials: true` (cookies sent alongside Bearer tokens)
  - Idempotency key support for specific POST paths
- **OpenAPI-generated SDK**: `@hey-api/openapi-ts@^0.94.3` generates into `generated/api/` using `@hey-api/client-fetch`
  - Used selectively by some hooks (`use-vendors.ts`, `use-users.ts`, `use-create-rfq-line-item.ts`, etc.)
  - Most hooks bypass the generated SDK and use direct Axios via `fetchLiveOrFail` helper (`lib/api-live.ts`)
- OpenAPI spec source: `openapi/openapi.json`

### Auth: JWT Bearer + Refresh Token

- **Access token**: kept in memory (Zustand store, NOT persisted)
- **Refresh token**: stored via Zustand `persist` middleware (localStorage)
- **Login flow**: native `fetch` POST to `/auth/login`, then `GET /me` for user data
- **Session restoration**: `AuthProvider` in root layout calls `/auth/refresh` on page load
- **Auto-retry**: Axios interceptor catches 401, attempts refresh once, retries original request
- **SSO**: `/auth/sso` endpoint also available
- **Multi-tenant**: `tenantId` stored in auth state; route guards verify both auth and tenant presence

### Forms: React Hook Form + Zod

- Pattern: `useForm<T>()` with `zodResolver(schema)`
- Custom DS inputs (`TextInput`, `TextAreaInput`, `SelectInput`, `Checkbox`, etc.) wired via `form.register()`
- Used in: login, register-company, forgot-password, reset-password, RFQ details pages
- Zod v4 schemas align with API DTOs for client-server validation parity

### UI: Custom Design System (`ds/`)

- Located at `WEB/src/components/ds/`
- **NOT Radix UI** (only `@radix-ui/react-slot` is installed, used as a primitive)
- Components: `Button`, `Input` family (TextInput, TextAreaInput, PasswordInput, SelectInput, Checkbox, ToggleSwitch, SearchInput), `DataTable`, `Card`, `Badge`, `Tabs`, `Progress`, `Timeline`, `FilterBar`, `KPIScorecard`, `OwnerCell`, `RecordHeader`, `HorizontalProcessTrack`, `StickyPageActions`
- Variants composed with `class-variance-authority` (cva)
- Design tokens defined in `lib/tokens.ts` and mapped to Tailwind utility classes

### Tables: Custom DataTable

- **NOT TanStack Table** — fully custom `DataTable` component (`ds/DataTable.tsx`)
- Features: `ColumnDef<T>` column definitions, sorting, row selection (checkboxes), expandable rows, bulk action toolbar, sticky header, loading/empty states, row click actions, overflow menu
- Used for: RFQ list, quote intake, approval queue, vendor lists

### Charts

- **No charting library installed** (no Recharts, Chart.js, Nivo, etc.)
- Dashboard displays stat cards and CSS-based progress bars (e.g., "Category Breakdown")
- `/reporting` route shows a "Coming Soon" placeholder

### Client State: Zustand

- Single store: `use-auth-store.ts` — manages `user`, `isAuthenticated`, `refreshToken`
- Uses `persist` middleware for localStorage (excludes short-lived access token)
- Server state lives exclusively in TanStack Query

### Styling: Tailwind CSS v4

- Uses Tailwind v4 syntax: `@import "tailwindcss"` and `@theme inline` in `globals.css`
- Custom font: Avenir Next (with system fallbacks)
- Semantic design tokens in `lib/tokens.ts` mapped to Tailwind utility classes
- Component variants via `class-variance-authority`

### Toasts: Sonner

- `<Toaster />` in root layout (`app/layout.tsx`)
- Used via `toast.success()`, `toast.error()`, `toast.info()` across mutation hooks

---

## 3. Implementation Status

| Area | Status | Details |
|------|--------|---------|
| Auth flow | ✅ Complete | Login, register, forgot/reset password, JWT refresh, route guards |
| API client | ✅ Complete | Axios wrapper + interceptors; partial Hey API SDK generation |
| Design system | ✅ Complete | `ds/` directory with all core components |
| Dashboard | ✅ Complete | Stat cards, RFQ overview, activity feed |
| RFQ management | ✅ Complete | List, create, detail, line items, vendor assignment |
| Vendor management | ✅ Complete | List, create, edit, detail |
| Quote comparison | ✅ In progress | Intake, comparison views |
| Approvals | ✅ Complete | Queue, detail, actions |
| Reporting | 🚧 Placeholder | "Coming Soon" |
| Charts | ❌ Not started | No library installed |
| OpenAPI workflow | ⚠️ Partial | Spec exists; SDK generated but only partially adopted |

---

## 3.1 OpenAPI Ownership & Update Workflow

- **Source of truth**: OpenAPI spec at `openapi/openapi.json` (generated from Laravel API)
- **Client generation**: `@hey-api/openapi-ts` generates into `generated/api/` using `@hey-api/client-fetch`
- **Adoption**: Selective — some hooks use generated SDK, most use direct Axios via `fetchLiveOrFail`
- **Gap**: No CI step enforcing spec freshness; no single `generate-client` script documented

## 3.2 JWT Storage & Refresh Strategy

- **Access token**: memory only (Zustand store, excluded from `persist`)
- **Refresh token**: Zustand `persist` middleware → localStorage
- **Refresh flow**: Axios interceptor catches 401 → POST `/auth/refresh` → retry original request; if refresh fails, clear auth state and redirect to `/login`
- **Cross-tab sync**: not implemented (no BroadcastChannel or storage events)

## 3.3 Multi-Tenant UX & Guarding

- **Tenant context**: `tenantId` stored in auth state
- **Guarding**: route guards in `(dashboard)/layout.tsx` check auth and redirect to `/login` when unauthenticated

## 3.4 API Error Normalization

- Axios instance handles 401 auto-refresh
- Error handling is distributed across individual hooks (no centralized error parser yet)

## 3.5 Frontend Testing Strategy

- **Unit**: Vitest for component tests
- **E2E**: Playwright for critical flows (login, RFQ list, create RFQ, approval flow)
- **MSW**: not yet adopted for query hook testing

## 3.6 Mock Data & Dev Fixtures

- Not yet established (no MSW setup, no JSON fixture pack)

---

## 4. Related Documents

- **API contract:** `apps/atomy-q/API_ENDPOINTS.md`
- **API implementation:** `apps/atomy-q/API/IMPLEMENTATION_SUMMARY.md`
- **Frontend blueprint:** `apps/atomy-q/QUOTE_COMPARISON_FRONTEND_BLUEPRINT_v2.md` (if present)
- **Architecture:** `docs/project/ARCHITECTURE.md`

---

*Last updated: 2026-05-05*
