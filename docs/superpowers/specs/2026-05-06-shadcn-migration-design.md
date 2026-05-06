# Shadcn UI Migration Design Spec

**Date:** 2026-05-06
**Status:** Draft — pending user review
**Scope:** Full migration from custom design system (`components/ds/`) to shadcn/ui exclusively

---

## Overview

Replace all custom UI components in `WEB/src/components/ds/` with shadcn/ui components, adopt shadcn theming system, layout blocks, and eliminate all custom design system code. This is a **big bang migration** affecting ~147 files.

**Current state:**
- 14 custom DS components in `components/ds/` (~2122 lines)
- Tailwind v4 with `@theme inline` (no `tailwind.config.js`)
- Only `@radix-ui/react-slot` installed
- One shadcn-style component exists: `components/ui/status-badge.tsx`
- Color scheme: indigo/slate with Tailwind utility classes
- Font: Avenir Next

**Target state:**
- All UI components from shadcn/ui
- shadcn CSS variable theming system
- shadcn layout blocks (sidebar, dashboard, page)
- Font: Inter (shadcn default)
- Color scheme: shadcn defaults via preset `b6G4q5Rof`

---

## Section 1: Theming System

### 1.1 shadcn Initialization

```bash
cd WEB
npx shadcn@latest init --preset b6G4q5Rof
```

This generates:
- Updated `globals.css` with shadcn CSS variable structure
- `components.json` (shadcn config)
- `lib/utils.ts` (cn() helper using clsx + tailwind-merge)

### 1.2 globals.css Transformation

**Remove:**
```css
@import "tailwindcss";

:root {
  --background: #ffffff;
  --foreground: #171717;
  --font-geist-sans: "Avenir Next", ...;
  --font-geist-mono: "SFMono-Regular", ...;
}

@theme inline {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --font-sans: var(--font-geist-sans);
  --font-mono: var(--font-geist-mono);
}
```

**Replace with shadcn structure:**
```css
@import "tailwindcss";

@layer base {
  :root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    --card: 0 0% 100%;
    --card-foreground: 222.2 84% 4.9%;
    --popover: 0 0% 100%;
    --popover-foreground: 222.2 84% 4.9%;
    --primary: 221.2 83.2% 53.3%;
    --primary-foreground: 210 40% 98%;
    --secondary: 210 40% 96.1%;
    --secondary-foreground: 222.2 47.4% 11.2%;
    --muted: 210 40% 96.1%;
    --muted-foreground: 215.4 16.3% 46.9%;
    --accent: 210 40% 96.1%;
    --accent-foreground: 222.2 47.4% 11.2%;
    --destructive: 0 84.2% 60.2%;
    --destructive-foreground: 210 40% 98%;
    --border: 214.3 31.8% 91.4%;
    --input: 214.3 31.8% 91.4%;
    --ring: 221.2 83.2% 53.3%;
    --radius: 0.5rem;
  }
}

@layer base {
  body {
    @apply bg-background text-foreground;
    font-family: var(--font-sans), system-ui, sans-serif;
  }
}
```

### 1.3 Font Migration

**Remove:** Avenir Next from globals.css and `layout/header.tsx`

**Add using next/font/google:**
```tsx
// app/layout.tsx
import { Inter } from 'next/font/google'

const inter = Inter({ subsets: ['latin'] })

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body className={inter.className}>{children}</body>
    </html>
  )
}
```

### 1.4 Token File Updates

**File:** `lib/tokens.ts`

**Keep:**
- Type exports: `StatusVariant`, `SLAVariant`, `ConfidenceVariant`, `ButtonVariant`, `ButtonSize`, `SlideOverWidth`, `AvatarSize`
- These are used by domain logic (status maps, etc.)

**Remove:**
- `DS_TOKENS` const (replaced by CSS variables)
- All color utility strings (`'bg-indigo-600'`, etc.)

### 1.5 tailwind.config.ts

shadcn generates `tailwind.config.ts`:
```ts
import type { Config } from "tailwindcss"

const config: Config = {
  darkMode: ["class"],
  content: [
    "./components/**/*.{ts,tsx}",
    "./app/**/*.{ts,tsx}",
    "./src/**/*.{ts,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // shadcn CSS variable colors
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
}

export default config
```

---

## Section 2: Component Migration (UI Primitives)

### 2.1 shadcn Components to Install

```bash
cd WEB
npx shadcn@latest add button input textarea select checkbox switch card badge tabs progress table dropdown-menu dialog popover tooltip separator scroll-area skeleton label form slider toast alert-dialog avatar command
```

Additional Radix primitives (for composite components):
```bash
npm install @radix-ui/react-avatar @radix-ui/react-command @radix-ui/react-scroll-area
```

### 2.2 Component Mapping Table

| Current `ds/` Component | Lines | shadcn Replacement | Prop Mapping |
|---|---|---|---|
| `Button.tsx` | 99 | `button` | `variant="primary"` → `"default"`, `variant="secondary"` → `"secondary"`, `variant="ghost"` → `"ghost"`, `variant="outline"` → `"outline"`, `variant="destructive"` → `"destructive"`, `size="md"` → `"default"`, `loading` prop → use `disabled` + manual `Loader2` |
| `Input.tsx` | 341 | `input` + `form` + `label` + `textarea` + `select` + `checkbox` + `switch` | `TextInput` → `input` + `form-field`, `TextAreaInput` → `textarea` + `form-field`, `SelectInput` → shadcn `select` component, `PasswordInput` → `input` + toggle button, `SearchInput` → `input` + `command` (optional), `Checkbox` → shadcn `checkbox`, `ToggleSwitch` → shadcn `switch` |
| `Card.tsx` | 260 | `card` | shadcn card has `Card`, `CardHeader`, `CardContent`, `CardFooter`, `CardTitle`, `CardDescription` — refactor current Card variants |
| `Badge.tsx` | 198 | `badge` | `variant="status"` → map to shadcn `variant="default"/"secondary"/"destructive"/"outline"` |
| `Tabs.tsx` | 49 | `tabs` | Direct replacement — both use Radix Tabs |
| `Progress.tsx` | 128 | `progress` | shadcn progress is simpler — create custom wrapper for multi-segment progress |
| `FilterBar.tsx` | 115 | Composite | Rebuild using `input` + `badge` + `button` + `command` (combobox) |
| `KPIScorecard.tsx` | 111 | Composite | Rebuild using `card` + `tooltip` |
| `RecordHeader.tsx` | 56 | Composite | Rebuild using shadcn `page` block header pattern |
| `sticky-page-actions.tsx` | 58 | Composite | Rebuild using `button` + `dropdown-menu` |
| `Timeline.tsx` | 92 | Composite | Rebuild using shadcn `separator` + custom layout |
| `HorizontalProcessTrack.tsx` | 215 | Composite | Rebuild using `progress` + custom step indicators |
| `DataTable.tsx` | 317 | shadcn DataTable | See Section 3 |
| `OwnerCell.tsx` | 25 | Composite | Rebuild using `avatar` + `badge` |
| `StatusBadge.tsx` (in `ui/`) | 231 | `badge` + custom | Keep as domain component, use shadcn `badge` internally |

### 2.3 Import Path Migration

**Patterns to find/replace across ~147 files:**

```
# Pattern 1: Named imports from ds
from '@/components/ds' → from '@/components/ui'

# Pattern 2: Direct file imports  
from '@/components/ds/Button' → from '@/components/ui/button'
from '@/components/ds/Input' → from '@/components/ui/input'
from '@/components/ds/Card' → from '@/components/ui/card'
from '@/components/ds/Badge' → from '@/components/ui/badge'
from '@/components/ds/Tabs' → from '@/components/ui/tabs'
from '@/components/ds/Progress' → from '@/components/ui/progress'
from '@/components/ds/DataTable' → from '@/components/ui/data-table'
```

---

## Section 3: DataTable Migration

### 3.1 Installation

```bash
cd WEB
npm install @tanstack/react-table
npx shadcn@latest add data-table
```

### 3.2 Current DataTable.tsx Features

- Sorting (column click)
- Row selection (checkbox per row)
- Expandable rows
- Bulk actions (toolbar on selection)
- Column definitions via `ColumnDef<T>` type
- Custom rendering via `cell` function

### 3.3 shadcn DataTable Pattern

**Files generated:**
- `components/ui/data-table.tsx` — generic wrapper with TanStack Table
- `components/ui/data-table-column-header.tsx` — sortable headers
- `components/ui/data-table-pagination.tsx` — pagination controls
- `components/ui/data-table-toolbar.tsx` — search + filters + bulk actions
- `components/ui/data-table-view-options.tsx` — column visibility toggle

### 3.4 Migration Approach

1. Replace `DataTable.tsx` (317 lines) with shadcn's `data-table.tsx` files
2. Port `ColumnDef<T>` → TanStack `ColumnDef` format
3. Bulk actions → `data-table-toolbar.tsx` with selected row state
4. Expandable rows → TanStack's `getExpandedRowModel` + custom Cell renderer
5. Update all consumers (grep shows ~5-8 files in rfq workspace, vendors, approvals)

### 3.5 Files That Import DataTable

Expected files to update:
```
src/app/(dashboard)/rfqs/[rfqId]/line-items/page.tsx
src/app/(dashboard)/rfqs/[rfqId]/vendors/page.tsx
src/app/(dashboard)/approvals/page.tsx
src/app/(dashboard)/tasks/page.tsx
```

---

## Section 4: Layout Blocks Migration

### 4.1 shadcn Sidebar Block

```bash
cd WEB
npx shadcn@latest add sidebar
npx shadcn@latest add breadcrumb
```

### 4.2 Current Layout Files to Replace

| Current File | Lines | Replacement |
|---|---|---|
| `components/layout/sidebar.tsx` | ? | shadcn `sidebar.tsx` block |
| `components/layout/main-sidebar-nav.tsx` | ? | Merge into `app-sidebar.tsx` |
| `components/layout/header.tsx` | ? | shadcn `page` block header or `site-header.tsx` |
| `components/layout/app-footer.tsx` | ? | Keep simple or use shadcn `separator` |

### 4.3 New Structure

```
components/
  ui/
    sidebar.tsx          (shadcn block)
    breadcrumb.tsx       (shadcn block)
    page.tsx             (shadcn page block)
  layout/
    app-sidebar.tsx      (replaces sidebar.tsx + main-sidebar-nav.tsx)
    site-header.tsx      (replaces header.tsx, uses shadcn sheet/mobile nav)
    app-footer.tsx       (keep simple)
```

### 4.4 Dashboard Layout (`app/(dashboard)/layout.tsx`)

- Use shadcn `sidebar` block pattern
- `SidebarProvider` wraps the layout
- `SidebarInset` for main content area
- `SiteHeader` with shadcn `breadcrumb` component

### 4.5 Page Layout Pattern

```tsx
// New page structure using shadcn page block
import { PageHeader, PageHeaderDescription, PageHeaderHeading } from "@/components/ui/page"

export default function RFQPage() {
  return (
    <div>
      <PageHeader>
        <PageHeaderHeading>RFQ Details</PageHeaderHeading>
        <PageHeaderDescription>Manage your request for quotation</PageHeaderDescription>
      </PageHeader>
      <div className="container mx-auto py-6">
        {/* page content */}
      </div>
    </div>
  )
}
```

### 4.6 Workspace Layout (`app/(dashboard)/rfqs/[rfqId]/layout.tsx`)

- Keep as-is but update internal components to use shadcn
- Replace `workspace-breadcrumbs.tsx` with shadcn `breadcrumb`
- Update `active-record-menu.tsx` to use shadcn `sheet` or `popover`
- Update `rfq-insights-sidebar.tsx` to use shadcn `sheet`

---

## Section 5: Domain-Specific Components

These are composite components that use multiple primitives. They live in `components/workspace/`, `components/dashboard/`, and `components/ai/`.

### 5.1 Components to Rebuild as shadcn Composites

| Component | Current Location | Approach |
|---|---|---|
| `FilterBar` | `ds/FilterBar.tsx` (115 lines) | Rebuild using `input` + `badge` + `button` + `command` (combobox) |
| `HorizontalProcessTrack` | `ds/HorizontalProcessTrack.tsx` (215 lines) | Rebuild using `progress` + custom step indicators with `card` |
| `Timeline` | `ds/Timeline.tsx` (92 lines) | Rebuild using shadcn `separator` + custom layout |
| `KPIScorecard` | `ds/KPIScorecard.tsx` (111 lines) | Rebuild using `card` + `tooltip` |
| `OwnerCell` | `ds/OwnerCell.tsx` (25 lines) | Rebuild using `avatar` + `badge` |
| `RecordHeader` | `ds/RecordHeader.tsx` (56 lines) | Replace with shadcn `page` block header pattern |
| `sticky-page-actions` | `ds/sticky-page-actions.tsx` (58 lines) | Rebuild using `button` + `dropdown-menu` |
| `rfq-insights-sidebar` | `workspace/rfq-insights-sidebar.tsx` | Use shadcn `sheet` or `popover` + `card` |
| `rfq-schedule-timeline` | `workspace/rfq-schedule-timeline.tsx` | Rebuild using shadcn primitives |
| `metric-chip` | `dashboard/metric-chip.tsx` | Rebuild using `badge` + `tooltip` |
| `smart-ai-insights-button` | `workspace/smart-ai-insights-button.tsx` | Use shadcn `button` + `tooltip` |
| `overview-next-step` | `workspace/overview-next-step.tsx` | Rebuild using `card` + `button` |
| `workspace-breadcrumbs` | `workspace/workspace-breadcrumbs.tsx` | Replace with shadcn `breadcrumb` |

### 5.2 Status Badge (in `ui/status-badge.tsx`)

**Keep as domain component but refactor internals:**
- Use shadcn `badge` as base component
- Keep `StatusVariant` type and `STATUS_MAP` logic
- Replace manual `className` strings with shadcn badge variants
- Map current color schemes to shadcn badge variants

### 5.3 Files to Delete After Migration

```
src/components/ds/        (entire directory - 14 files, ~2122 lines)
```

---

## Section 6: Import/Path Migration (Big Bang)

### 6.1 Find/Replace Patterns

Run across all `src/` files using `find` + `sed`:

```bash
# Find all files importing from ds/
grep -r "from.*components/ds" src/ --include="*.tsx" --include="*.ts"

# Pattern 1: Named imports from ds barrel file
find src/ -type f \( -name "*.tsx" -o -name "*.ts" \) -exec sed -i "s|from '@/components/ds'|from '@/components/ui'|g" {} +

# Pattern 2: Direct file imports  
find src/ -type f -name "*.tsx" -exec sed -i "s|from '@/components/ds/Button'|from '@/components/ui/button'|g" {} +
find src/ -type f -name "*.tsx" -exec sed -i "s|from '@/components/ds/Input'|from '@/components/ui/input'|g" {} +
find src/ -type f -name "*.tsx" -exec sed -i "s|from '@/components/ds/Card'|from '@/components/ui/card'|g" {} +
# ... repeat for all ds/ components
```

### 6.2 Prop Mapping During Replacement

| Old Prop | New Prop | Component | Notes |
|---|---|---|---|
| `variant="primary"` | `variant="default"` | Button | |
| `variant="secondary"` | `variant="secondary"` | Button | Same |
| `variant="ghost"` | `variant="ghost"` | Button | Same |
| `variant="outline"` | `variant="outline"` | Button | Same |
| `variant="destructive"` | `variant="destructive"` | Button | Same |
| `size="md"` | `size="default"` | Button | |
| `size="sm"` | `size="sm"` | Button | Same |
| `size="xs"` | `size="sm"` or remove | Button | shadcn doesn't have xs |
| `size="lg"` | `size="lg"` | Button | Same |
| `loading` prop | Use `disabled` + `Loader2` icon | Button | shadcn has no loading prop |
| `icon` prop | Use `asChild` + `lucide-react` icon | Button | shadcn button uses asChild pattern |

### 6.3 Token Imports

```
from './tokens' or from '@/lib/tokens' → remove or update
```

Types like `StatusVariant`, `ButtonVariant` stay in `lib/tokens.ts` (needed by domain logic).

---

## Section 7: Testing & Verification

### 7.1 Current Test Files That Need Updating

```
src/components/ds/sticky-page-actions.test.tsx
src/components/workspace/rfq-insights-sidebar.test.tsx
src/app/(dashboard)/layout.tsx (has header.test.tsx)
```

### 7.2 Test Migration Approach

- `sticky-page-actions.test.tsx` — update imports from `ds` → `ui`, verify button variants
- `rfq-insights-sidebar.test.tsx` — update to use shadcn `sheet`/`popover` queries
- `header.test.tsx` — rewrite for shadcn `sidebar` block patterns

### 7.3 New Component Tests

- Add tests for refactored composites: `FilterBar`, `RecordHeader`, `KPIScorecard`
- Use `@testing-library/react` + `vitest`
- Test shadcn prop mappings work correctly

### 7.4 E2E Tests

```bash
cd WEB
npm run test:e2e
```

- Current E2E suite (Playwright) should still pass
- shadcn components have same aria-labels/roles (button, input, etc.)
- May need to update selectors if custom `data-testid` attributes were used

### 7.5 Lint + Typecheck

```bash
cd WEB
npm run lint
npx tsc --noEmit
```

- Should catch any missed prop mappings
- shadcn components are fully typed

### 7.6 Verification Checklist

1. ✅ All `from '@/components/ds'` imports removed
2. ✅ `components/ds/` directory deleted
3. ✅ `globals.css` uses shadcn CSS variables
4. ✅ All pages render without visual regression
5. ✅ E2E tests pass
6. ✅ Lint passes
7. ✅ TypeScript compiles without errors
8. ✅ Font changed to Inter
9. ✅ shadcn preset `b6G4q5Rof` applied correctly

---

## Appendix A: Commands Summary

```bash
# 1. Initialize shadcn with preset
cd WEB
npx shadcn@latest init --preset b6G4q5Rof

# 2. Install all shadcn components
npx shadcn@latest add button input textarea select checkbox switch card badge tabs progress table dropdown-menu dialog popover tooltip separator scroll-area skeleton label form slider toast alert-dialog avatar command data-table sidebar breadcrumb page

# 3. Install TanStack Table
npm install @tanstack/react-table

# 4. Install additional Radix primitives
npm install @radix-ui/react-avatar @radix-ui/react-command @radix-ui/react-scroll-area

# 5. Verify
npm run lint
npm run test:e2e
```

---

## Appendix B: Files Affected (147+ files)

**Components (14 files to delete - NOTE: keep `lib/tokens.ts` for type exports):**
```
src/components/ds/Button.tsx
src/components/ds/Input.tsx
src/components/ds/Card.tsx
src/components/ds/Badge.tsx
src/components/ds/Tabs.tsx
src/components/ds/Progress.tsx
src/components/ds/DataTable.tsx
src/components/ds/FilterBar.tsx
src/components/ds/KPIScorecard.tsx
src/components/ds/RecordHeader.tsx
src/components/ds/sticky-page-actions.tsx
src/components/ds/Timeline.tsx
src/components/ds/HorizontalProcessTrack.tsx
src/components/ds/OwnerCell.tsx
```
**Do NOT delete:** `src/lib/tokens.ts` — keep for type exports (see Section 1.4)

**Layout files (~6 files to update):**
```
src/components/layout/sidebar.tsx
src/components/layout/main-sidebar-nav.tsx
src/components/layout/header.tsx
src/components/layout/app-footer.tsx
src/app/(dashboard)/layout.tsx
src/app/(dashboard)/rfqs/[rfqId]/layout.tsx
```

**Page files (~140 files importing ds/ components):**
```
src/app/(dashboard)/**/*.tsx
src/app/(auth)/**/*.tsx
src/components/workspace/**/*.tsx
src/components/dashboard/**/*.tsx
src/components/ai/**/*.tsx
src/hooks/**/*.ts
```

---

**End of Design Spec**
