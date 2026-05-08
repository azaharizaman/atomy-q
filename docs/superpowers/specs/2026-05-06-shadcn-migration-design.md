# Shadcn UI Migration Design Spec

**Date:** 2026-05-06  
**Last updated:** 2026-05-07  
**Status:** Draft - revised for implementation readiness  
**Scope:** Migrate Atomy-Q WEB from the custom `components/ds/` primitive layer to shadcn/ui primitives while preserving Atomy-Q domain components, workflows, route contracts, accessibility, and test coverage.

---

## 0. Evaluation Summary

The previous draft captured the correct strategic intent, but it was not implementation-ready. It would likely fail or cause broad regressions because it:

- treated shadcn as a drop-in replacement for custom Atomy-Q components even though the current DS exports domain-shaped APIs such as `PageHeader`, `SectionCard`, `InfoGrid`, `UploadZone`, `StickyPageActions`, `StatusBadge`, and a custom `ColumnDef<T>`;
- proposed a Tailwind v3-style `tailwind.config.ts` even though this app is on Tailwind v4, where shadcn uses `components.json` plus the existing CSS entry file;
- assumed `npx shadcn@latest add data-table` and `add page` exist as ordinary registry components, while shadcn documents Data Table as a guide built from `table` plus `@tanstack/react-table`;
- used brittle global `sed` replacements that cannot handle prop API differences, composite components, type imports, or path-specific behavior;
- proposed deleting `components/ds/` before all consumer contracts are replaced;
- undercounted the real migration surface: current grep shows 47 source files directly import `components/ds`, while 39 app page/layout files depend directly or indirectly on the DS layer;
- omitted route-by-route acceptance criteria, visual regression checkpoints, fallback strategy, and a realistic staged execution order.

This revised spec keeps the intended output but makes the migration executable.

---

## 1. Intended Output

### 1.1 Target State

- `WEB/src/components/ui/` contains shadcn-owned primitives added by the CLI and reviewed locally after generation.
- Atomy-Q-specific components live outside `components/ds/`, primarily in:
  - `WEB/src/components/domain/` for reusable product/domain composites;
  - `WEB/src/components/layout/` for app shell and navigation;
  - `WEB/src/components/workspace/` for RFQ workspace components;
  - existing feature folders when a component is single-use.
- No production code imports from `@/components/ds/*` or `@/components/ds`.
- `WEB/src/components/ds/` is deleted only after all imports, tests, and type contracts are removed or replaced.
- The UI uses shadcn semantic tokens and Tailwind v4 CSS variables instead of raw slate/indigo utility tokens for component styling.
- Domain status semantics remain Atomy-Q-owned. Status labels, SLA colors, confidence badges, AI unavailable states, and alpha-deferred messaging do not become generic shadcn-only widgets.
- Existing user workflows stay functionally equivalent: auth forms, dashboard, RFQ workspace, quote intake, comparison runs, award, projects, vendors, approvals, tasks, settings, and error states.

### 1.2 Non-Goals

- Do not migrate backend/API behavior.
- Do not change generated OpenAPI clients.
- Do not introduce a second design system or mixed Radix/shadcn abstraction layer.
- Do not replace React Query, Zustand, React Hook Form, Zod, Sonner, or lucide-react.
- Do not redesign product IA, route hierarchy, feature flags, RFQ workspace ownership, or alpha/manual-continuity posture.
- Do not add charts as part of this migration.

### 1.3 Migration Principle

The goal is **shadcn primitives, Atomy-Q composites**.

It is acceptable, and required, to keep domain components when they encode product meaning. It is not acceptable to keep custom primitive components that duplicate shadcn primitives under a different visual system.

---

## 2. Current Repo Inventory

### 2.1 Current Frontend Facts

- App: `WEB/`, Next.js 16, React 19, all page-level UI currently client-oriented.
- Styling: Tailwind CSS v4 through `WEB/src/app/globals.css`.
- Existing helper: `WEB/src/lib/utils.ts` already exports `cn()` using `clsx` and `tailwind-merge`.
- Package manager: npm, with `package-lock.json`.
- Existing primitive dependency: `@radix-ui/react-slot`.
- Existing UI dependency: `sonner`.
- Existing icons: `lucide-react`.
- No `WEB/components.json` currently exists.
- No `WEB/tailwind.config.ts` currently exists.
- Current direct DS import count: 47 source files.

### 2.2 Current DS Files

Current `WEB/src/components/ds/` inventory:

| File | Current Role | Migration Treatment |
|---|---|---|
| `Button.tsx` | Primitive button plus loading/icon props | Replace with shadcn `button`; create a temporary adapter only if needed during staged migration |
| `Input.tsx` | Labels, hints, text input, textarea, password, search, select, checkbox, switch, chips | Replace with shadcn `input`, `textarea`, `select`, `checkbox`, `switch`, `label`, `field/form`; build Atomy form wrappers where needed |
| `Card.tsx` | Card primitive plus domain panels: `SectionCard`, `InfoGrid`, `EmptyState`, `DocPreview`, `UploadZone`, `OverflowMenuTrigger` | Replace `Card` primitive with shadcn `card`; move domain panels to Atomy composites |
| `Badge.tsx` | Status/SLA/confidence/version/count/dot domain badges | Move to domain/status components backed by shadcn `badge` |
| `Tabs.tsx` | `SecondaryTabs` wrapper | Replace with shadcn `tabs`; keep a small feature wrapper only if route state needs it |
| `Progress.tsx` | Linear and circular progress | Use shadcn `progress`; keep circular/custom multi-segment progress as Atomy composite if required |
| `DataTable.tsx` | Custom generic table with sorting, selection, expansion, bulk actions | Replace with an Atomy table wrapper built on shadcn `table` plus TanStack Table |
| `FilterBar.tsx` | Filter bar and `PageHeader` | Split into feature/domain `FilterBar` and layout `PageHeader` composites using shadcn primitives |
| `KPIScorecard.tsx` | Metric card composite | Move to metrics/domain component backed by `card` and `tooltip` |
| `RecordHeader.tsx` | Detail header composite | Move to domain/layout composite |
| `sticky-page-actions.tsx` | Sticky action footer | Move to layout/domain composite backed by shadcn `button` and responsive CSS |
| `Timeline.tsx` | Timeline event component | Move to domain composite backed by semantic tokens |
| `HorizontalProcessTrack.tsx` | RFQ workflow track | Move to workspace/domain composite; keep `horizontalProcessTrackLogic.ts` as domain logic |
| `OwnerCell.tsx` | Table owner display | Move to domain/table cell backed by `avatar` and text |
| `tokens.ts` | DS type exports | Remove duplicate source after all imports use `@/lib/tokens` or feature-local types |

### 2.3 Existing Domain Component Already Outside DS

`WEB/src/components/ui/status-badge.tsx` duplicates much of `ds/Badge.tsx`. Migration must consolidate to one canonical status component, preferably outside generic `ui/` because status mapping is domain-specific.

---

## 3. Target Directory Contract

```
WEB/src/components/
  ui/                         # shadcn CLI-owned primitives only
    button.tsx
    input.tsx
    textarea.tsx
    select.tsx
    checkbox.tsx
    switch.tsx
    card.tsx
    badge.tsx
    tabs.tsx
    progress.tsx
    table.tsx
    dropdown-menu.tsx
    dialog.tsx
    sheet.tsx
    popover.tsx
    tooltip.tsx
    separator.tsx
    scroll-area.tsx
    skeleton.tsx
    label.tsx
    form.tsx
    slider.tsx
    alert.tsx
    alert-dialog.tsx
    avatar.tsx
    command.tsx
    breadcrumb.tsx
    sidebar.tsx
  domain/                     # Atomy-Q reusable product composites
    status-badge.tsx
    data-table.tsx
    page-header.tsx
    section-card.tsx
    empty-state.tsx
    info-grid.tsx
    document-preview.tsx
    upload-zone.tsx
    sticky-page-actions.tsx
    timeline.tsx
    owner-cell.tsx
  layout/                     # app shell
  workspace/                  # RFQ workspace composites
  metrics/                    # dashboard/metric composites
```

Rules:

- `components/ui/*` is generated or generated-derived shadcn primitive code. Keep product logic out.
- `components/domain/*` may compose primitives and encode Atomy-Q workflow vocabulary.
- Domain components must not export generic primitive names that shadow shadcn names.
- New production imports must not target `components/ds`.

---

## 4. Shadcn Initialization

### 4.1 Preflight

Run from `WEB/`:

```bash
npm run lint
npx tsc --noEmit
npm run test:unit
```

Record any existing failures before migration. Do not mix pre-existing test failures with migration regressions.

### 4.2 Initialize Components Config

Use npm because this project has `package-lock.json`:

```bash
cd WEB
npx shadcn@latest init
```

Initialization choices:

- style: `new-york` unless a reviewed preset is deliberately chosen;
- base color: neutral/zinc family unless product design explicitly selects another base;
- CSS variables: true;
- TypeScript: true;
- React Server Components setting: align with Next App Router output, but every generated client primitive must remain compatible with the current client-rendered pages;
- aliases:
  - components: `@/components`
  - ui: `@/components/ui`
  - lib: `@/lib`
  - hooks: `@/hooks`

Expected `WEB/components.json` shape:

```json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": true,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "src/app/globals.css",
    "baseColor": "neutral",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks"
  },
  "iconLibrary": "lucide"
}
```

Do not add `tailwind.config.ts` for this app unless the shadcn CLI requires it in the current version. Tailwind v4 should keep config empty in `components.json`.

### 4.3 Preset Handling

The old draft named preset `b6G4q5Rof` without explaining its source or decoded values. That is not acceptable for a migration spec.

If a preset is still desired:

```bash
cd WEB
npx shadcn@latest preset decode b6G4q5Rof
npx shadcn@latest preset resolve --json
```

Add the decoded values to this spec before applying it. Do not apply an opaque preset code to the app without recording what it changes: style, base, theme variables, font, and icons.

---

## 5. Tailwind v4 Theming

### 5.1 Current File

Edit only:

```
WEB/src/app/globals.css
```

Do not create a second global CSS file. Do not introduce CSS Modules.

### 5.2 Required Token Direction

The final theme must expose semantic shadcn tokens through Tailwind v4 `@theme inline`, including at minimum:

- `--color-background`
- `--color-foreground`
- `--color-card`
- `--color-card-foreground`
- `--color-popover`
- `--color-popover-foreground`
- `--color-primary`
- `--color-primary-foreground`
- `--color-secondary`
- `--color-secondary-foreground`
- `--color-muted`
- `--color-muted-foreground`
- `--color-accent`
- `--color-accent-foreground`
- `--color-destructive`
- `--color-border`
- `--color-input`
- `--color-ring`
- `--radius-*` aliases used by generated components.

Use the current shadcn CLI output as the source of truth. Recent shadcn/Tailwind v4 output uses OKLCH-style variables and `data-slot`-oriented component CSS. Do not hand-write an older HSL-only v3 theme from memory.

### 5.3 Font Policy

The prior draft switched Avenir Next to Inter without justification. That is a product design decision, not an automatic shadcn requirement.

Migration options:

1. Keep Avenir Next initially and migrate UI primitives first.
2. Switch to Inter only if product/design approves the visual change.

If switching fonts, update:

- `WEB/src/app/globals.css`
- `WEB/src/app/layout.tsx`
- `WEB/src/components/layout/header.tsx` if it still contains font-specific classes or assumptions.

---

## 6. Components to Add

Run from `WEB/`:

```bash
npx shadcn@latest add button input textarea select checkbox switch card badge tabs progress table dropdown-menu dialog sheet popover tooltip separator scroll-area skeleton label form slider alert alert-dialog avatar command breadcrumb sidebar sonner
```

Notes:

- Use `sonner`, not deprecated shadcn toast patterns.
- Do not separately install Radix primitive packages unless the CLI fails to add required dependencies. The shadcn CLI should own primitive dependency additions.
- Add components incrementally by phase if the diff is too large to review safely.
- After each CLI add, read the generated files before using them. Fix import aliases only if the CLI output does not match `components.json`.

---

## 7. Domain Component Migration

### 7.1 Keep Product Meaning Outside `ui/`

Move these APIs into domain/workspace/layout modules before deleting `ds/`:

| Old API | New Location | Backing Primitives |
|---|---|---|
| `StatusBadge`, `SLATimerBadge`, `ConfidenceBadge`, `VersionChip`, `CountBadge`, `StatusDot` | `components/domain/status-badge.tsx` | `Badge`, `Progress`, semantic token classes |
| `SectionCard` | `components/domain/section-card.tsx` | `Card`, `CardHeader`, `CardContent`, optional `CardFooter` |
| `InfoGrid` | `components/domain/info-grid.tsx` | semantic layout, no nested cards |
| `EmptyState` | `components/domain/empty-state.tsx` | shadcn empty pattern if installed, otherwise `Card`/layout primitive |
| `DocPreview` | `components/domain/document-preview.tsx` | `Card`, `Badge`, document icon |
| `UploadZone` | `components/domain/upload-zone.tsx` | `Button`, `Card`, file input semantics |
| `OverflowMenuTrigger` | remove or replace inline | `DropdownMenuTrigger` and icon button |
| `FilterBar` | `components/domain/filter-bar.tsx` | `Input`, `Command`, `Badge`, `Button` |
| `PageHeader` | `components/domain/page-header.tsx` | layout primitives plus `Breadcrumb` when needed |
| `RecordHeader` | `components/domain/record-header.tsx` | `PageHeader`, `Badge`, metadata grid |
| `StickyPageActions` | `components/domain/sticky-page-actions.tsx` | `Button`, responsive sticky container |
| `Timeline` | `components/domain/timeline.tsx` | semantic list, `Separator`, status tokens |
| `HorizontalProcessTrack` | `components/workspace/horizontal-process-track.tsx` | `Progress`, step indicators, existing logic |
| `OwnerCell` | `components/domain/owner-cell.tsx` | `Avatar`, fallback initials, text |
| `KPIScorecard` | `components/metrics/kpi-scorecard.tsx` | `Card`, `Tooltip`, `Progress` |

### 7.2 Temporary Adapter Rule

If staged execution needs compatibility, create explicit temporary adapters under:

```
WEB/src/components/domain/legacy-adapters/
```

Constraints:

- adapters must be small and documented as temporary;
- adapters may preserve old props while delegating to shadcn/domain components;
- no new feature work may import adapters;
- the final migration task deletes adapters and proves no imports remain.

Do not keep compatibility adapters in `components/ui/`.

---

## 8. Data Table Design

### 8.1 Correct shadcn Position

There is no universal shadcn `data-table` primitive to install. shadcn provides the `table` component and a guide for composing TanStack Table.

Install:

```bash
cd WEB
npm install @tanstack/react-table
npx shadcn@latest add table checkbox button dropdown-menu input
```

### 8.2 Required Current Behavior

The replacement `components/domain/data-table.tsx` must preserve:

- generic row support for rows with `id: string | number`;
- current sorting behavior and nullable sort direction;
- selectable rows and bulk action toolbar;
- expandable rows with `renderExpanded`;
- optional action column;
- `loading`, `emptyState`, `stickyHeader`, `rowClassName`, and `onRowClick`;
- column `width`, `minWidth`, alignment, sortable flag, and custom renderer support;
- stable row heights and top-aligned cells.

### 8.3 Column Contract Strategy

Do not force every consumer to rewrite to TanStack columns in the first pass.

Preferred staged path:

1. Create `components/domain/data-table.tsx` that accepts the current Atomy-Q `ColumnDef<T>` contract.
2. Internally map the Atomy-Q column contract to TanStack Table.
3. Export the current `ColumnDef<T>`, `TableSort`, and `SortDirection` types from the new module.
4. Move all imports from `@/components/ds/DataTable` to `@/components/domain/data-table`.
5. Only after migration is green, decide whether page-specific tables should gradually adopt native TanStack column definitions.

This avoids rewriting every RFQ/vendor/approval table at the same time as the primitive migration.

---

## 9. Layout Migration

### 9.1 Dashboard Shell

Current files:

- `WEB/src/components/layout/sidebar.tsx`
- `WEB/src/components/layout/main-sidebar-nav.tsx`
- `WEB/src/components/layout/header.tsx`
- `WEB/src/components/layout/app-footer.tsx`
- `WEB/src/app/(dashboard)/layout.tsx`

Target:

- shadcn `sidebar` primitives in `components/ui/sidebar.tsx`;
- Atomy-Q app shell composition in `components/layout/app-sidebar.tsx`;
- route navigation semantics preserved from `main-sidebar-nav.tsx`;
- header actions preserved from `header.tsx`;
- auth guard remains in `app/(dashboard)/layout.tsx`.

Do not change feature flag routing for `/projects` and `/tasks`.

### 9.2 RFQ Workspace Shell

Current RFQ workspace layout bypasses the default sidebar layout. Preserve that boundary.

Files to migrate carefully:

- `WEB/src/app/(dashboard)/rfqs/[rfqId]/layout.tsx`
- `WEB/src/components/workspace/workspace-breadcrumbs.tsx`
- `WEB/src/components/workspace/active-record-menu.tsx`
- `WEB/src/components/workspace/rfq-insights-sidebar.tsx`
- `WEB/src/components/workspace/rfq-schedule-timeline.tsx`

Rules:

- Breadcrumbs remain only at the top of the screen.
- Workspace actions must not wrap into broken two-line buttons.
- Sticky save/actions behavior on RFQ detail/edit screens must be preserved.
- The active record menu remains a workspace concern, not a top-level global module.

---

## 10. Import Migration Strategy

### 10.1 Do Not Use Blind `sed`

Avoid global string replacement for imports. The old and new APIs are not equivalent.

Use this workflow instead:

```bash
cd WEB
rg -n "@/components/ds|components/ds" src --glob '*.{ts,tsx}'
```

Migrate by component family:

1. status badges;
2. button/icon button;
3. form inputs;
4. card/panel/empty/upload/info composites;
5. page headers/filter bars;
6. data tables;
7. workspace timeline/process track;
8. layout shell;
9. tests;
10. final DS deletion.

### 10.2 Final Import Gate

Before deleting `components/ds/`, this command must return no production imports:

```bash
cd WEB
rg -n "@/components/ds|components/ds" src --glob '*.{ts,tsx}'
```

Tests may only reference deleted DS paths if the test is itself being deleted in the same commit.

---

## 11. Execution Plan

### Phase 0 - Baseline

- Run lint, typecheck, unit tests.
- Capture current DS import list.
- Capture screenshots for key routes at desktop and mobile widths:
  - `/login`
  - `/dashboard`
  - `/rfqs`
  - `/rfqs/[rfqId]/overview`
  - `/rfqs/[rfqId]/details`
  - `/rfqs/[rfqId]/quote-intake`
  - `/rfqs/[rfqId]/comparison-runs`
  - `/vendors`
  - `/projects`
  - `/approvals`

### Phase 1 - shadcn Foundation

- Initialize `components.json`.
- Add primitives.
- Update `globals.css` using Tailwind v4-compatible shadcn output.
- Confirm `cn()` remains in `src/lib/utils.ts`.
- Run lint and typecheck.

### Phase 2 - Domain Component Extraction

- Create domain components backed by shadcn primitives.
- Consolidate status badge implementation.
- Preserve current public props where this reduces page churn.
- Add or update focused component tests for status badges, empty states, sticky actions, and table behavior.

### Phase 3 - Data Table

- Add `@tanstack/react-table`.
- Implement `components/domain/data-table.tsx`.
- Preserve current Atomy-Q `ColumnDef<T>` consumer contract.
- Migrate all DataTable consumers.
- Verify selection, sorting, expansion, bulk action, loading, and empty states.

### Phase 4 - Page and Layout Imports

- Migrate route pages component-family by component-family.
- Migrate dashboard shell.
- Migrate RFQ workspace shell without changing route ownership.
- Keep user-facing copy and workflow states intact.

### Phase 5 - Remove DS

- Confirm no imports remain.
- Delete `WEB/src/components/ds/`.
- Delete duplicate or obsolete tests.
- Run full verification.

---

## 12. Testing and Verification

### 12.1 Required Commands

Run from `WEB/`:

```bash
npm run lint
npx tsc --noEmit
npm run test:unit
npm run build
```

Run Playwright after the app is visually coherent:

```bash
npm run test:e2e
```

For release/staging behavior, run the relevant live-mode e2e checks with:

```bash
NEXT_PUBLIC_USE_MOCKS=false npm run test:e2e
```

### 12.2 Visual Verification

Use Playwright screenshots for the key routes listed in Phase 0.

Check:

- no blank pages;
- no hydration/runtime errors;
- no overlapping text;
- no wrapped primary action buttons;
- tables top-align header/body cells;
- mobile auth and dashboard pages remain usable;
- RFQ workspace shell still leaves enough room for the active content;
- sticky actions remain accessible when scrolling;
- popovers, dialogs, sheets, dropdowns, and tooltips have accessible titles/labels.

### 12.3 Accessibility Gate

Every migrated component must preserve or improve:

- visible label or `aria-label` for icon-only buttons;
- proper `DialogTitle`, `SheetTitle`, or accessible hidden title for overlays;
- form label/control association;
- error text connected to invalid controls where applicable;
- keyboard reachability for menus, tabs, dialogs, and table actions.

---

## 13. Acceptance Criteria

- `WEB/components.json` exists and matches the Tailwind v4 app paths.
- `WEB/src/app/globals.css` contains shadcn-compatible Tailwind v4 semantic tokens.
- `WEB/src/components/ui/` contains shadcn primitives only.
- Atomy-Q composites have been moved out of `components/ds/`.
- `WEB/src/components/ds/` no longer exists.
- `rg -n "@/components/ds|components/ds" WEB/src --glob '*.{ts,tsx}'` returns no matches.
- Existing route workflows remain available and functionally equivalent.
- Lint passes.
- TypeScript compile passes.
- Unit tests pass.
- Production build passes.
- E2E suite passes or any remaining failures are documented as pre-existing and not caused by this migration.
- Visual screenshot pass confirms no major responsive, table, shell, or RFQ workspace regressions.

---

## 14. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---:|---:|---|
| shadcn CLI output changes with current version | Medium | Medium | Inspect generated files and commit output intentionally; do not hand-code stale examples |
| Tailwind v4 theme written with stale v3/HSL assumptions | High | High | Let CLI generate theme, then adapt tokens in `globals.css` only |
| DataTable migration rewrites too many consumers at once | High | High | Preserve current Atomy-Q `ColumnDef<T>` contract initially |
| Domain meaning lost by generic badge/card replacements | Medium | High | Keep status/SLA/confidence/domain composites outside `ui/` |
| RFQ workspace shell regresses | Medium | High | Treat workspace layout as separate migration phase with screenshot checks |
| Blind import replacement breaks props/types | High | High | Migrate by component family and run typecheck after each phase |
| Opaque preset changes product visual direction | Medium | Medium | Decode and record preset values before applying |

---

## 15. External References

- shadcn components config: https://ui.shadcn.com/docs/components-json
- shadcn Tailwind v4 support: https://ui.shadcn.com/docs/tailwind-v4
- shadcn Table component: https://ui.shadcn.com/docs/components/table
- shadcn Data Table guide: https://ui.shadcn.com/docs/components/data-table

---

**End of Design Spec**
