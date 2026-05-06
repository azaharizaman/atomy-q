# MetricEngine KPI Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the MetricEngine KPI, scorecard, and widget design into motion with a working first slice that evaluates system metrics through `Nexus\MetricEngine` and exposes reusable widget payloads without breaking current endpoints.

**Architecture:** Add an Atomy-Q metric application layer that owns metric identity, source facts, and display metadata, then delegates formula mechanics to `Nexus\MetricEngine\BatchFormulaEvaluatorService`. Keep existing KPI endpoints compatible and add widget payloads through backend services/controllers plus minimal frontend rendering support.

**Tech Stack:** Laravel 12, PHP 8.3, Nexus MetricEngine, PHPUnit, Next.js 16, React 19, Vitest.

---

## File Structure

- Create `API/app/Services/Metrics/MetricCardData.php`: immutable DTO for app metric card payloads.
- Create `API/app/Services/Metrics/WidgetData.php`: immutable DTO for widget payloads.
- Create `API/app/Services/Metrics/AppMetricDefinition.php`: app-owned metric definition with formula payload and display metadata.
- Create `API/app/Services/Metrics/AppMetricDefinitionCatalog.php`: system metric definitions and widget membership.
- Create `API/app/Services/Metrics/MetricEvaluationService.php`: only app service that calls MetricEngine.
- Create `API/app/Services/Metrics/MetricInputProvider.php`: tenant/context prepared input provider.
- Create `API/app/Services/Metrics/MetricInputRegistry.php`: domain/context input routing abstraction.
- Create `API/app/Services/Metrics/ScorecardData.php`: immutable DTO for scorecard payloads.
- Create `API/app/Services/Metrics/ScorecardService.php`: app-owned scorecard composition service.
- Create `API/app/Services/Metrics/WidgetCompositionService.php`: screen-ready widget composition.
- Create `API/tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php`: MetricEngine integration and status mapping coverage.
- Create `API/tests/Feature/Api/V1/MetricWidgetsApiTest.php`: dashboard, RFQ overview, and reporting widget contract coverage.
- Modify `API/app/Providers/AppServiceProvider.php`: register MetricEngine and Atomy-Q metric services.
- Modify `API/app/Http/Controllers/Api/V1/DashboardController.php`: inject widget service and add `widgets()`.
- Modify `API/app/Http/Controllers/Api/V1/RfqController.php`: inject widget service and include RFQ overview widgets.
- Modify `API/app/Http/Controllers/Api/V1/ReportController.php`: inject widget service and add `widgets()`.
- Modify `API/app/Http/Controllers/Api/V1/VendorGovernanceController.php`: inject widget service and add `widgets()`.
- Modify `API/routes/api.php`: add `GET /dashboard/widgets` and `GET /reports/widgets`.
- Create `WEB/src/types/metrics.ts`: shared metric/widget payload types.
- Create `WEB/src/components/metrics/MetricCard.tsx`: payload-driven wrapper over existing KPI card.
- Create `WEB/src/components/metrics/MetricCardGrid.tsx`: reusable metric-grid wrapper.
- Create `WEB/src/components/metrics/MetricScorecard.tsx`: reusable scorecard renderer.
- Create `WEB/src/components/metrics/WidgetSection.tsx`: common widget section shell.
- Create `WEB/src/components/metrics/DashboardWidgetRenderer.tsx`: render metric-grid widgets.
- Create `WEB/src/components/metrics/MetricCard.test.tsx`: renderer state coverage.
- Modify `WEB/src/hooks/use-rfq-overview.ts`: type and preserve `widgets` from the API payload.
- Modify `WEB/src/app/(dashboard)/rfqs/[rfqId]/overview/page.tsx`: render backend-provided RFQ metric cards when present.
- Modify `WEB/src/app/(dashboard)/page.tsx`: render dashboard widget payloads when present.
- Modify `WEB/src/app/(dashboard)/reporting/page.tsx`: render reporting widget payloads when present.

---

### Task 1: MetricEngine-Backed Evaluation Foundation

**Files:**
- Create: `API/app/Services/Metrics/MetricCardData.php`
- Create: `API/app/Services/Metrics/WidgetData.php`
- Create: `API/app/Services/Metrics/AppMetricDefinition.php`
- Create: `API/app/Services/Metrics/AppMetricDefinitionCatalog.php`
- Create: `API/app/Services/Metrics/MetricEvaluationService.php`
- Create: `API/tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php`
- Modify: `API/app/Providers/AppServiceProvider.php`

- [x] **Step 1: Write failing unit tests**

Create `API/tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php` with tests that instantiate the catalog and evaluator, assert dashboard metrics are evaluated through MetricEngine outcomes, assert missing inputs map to `not_available`, and assert the result includes a fingerprint.

- [x] **Step 2: Run the unit test and verify RED**

Run: `cd API && php artisan test tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php`

Expected: FAIL because `App\Services\Metrics\MetricEvaluationService` does not exist.

- [x] **Step 3: Add DTOs and catalog**

Create immutable DTOs with `toArray()` methods. Define at least these canonical metrics: `procurement.active_rfqs`, `procurement.pending_approvals`, `procurement.quote_intake_count`, `procurement.awards_in_flight`, `procurement.total_savings`, `rfq.quotes_received`, `rfq.expected_quotes`, `rfq.quote_receipt_pct`, `rfq.normalization_progress_pct`, `rfq.comparison_runs_count`, `rfq.pending_approvals`, `reporting.total_spend`, `reporting.active_rfqs`, `reporting.total_savings`.

- [x] **Step 4: Add MetricEvaluationService**

Build a Nexus `FormulaCatalog` from selected app definitions, call `BatchFormulaEvaluatorService`, map `MetricResultStatus` to app statuses, attach display metadata, and generate a `MetricRunFingerprint` with caller metadata.

- [x] **Step 5: Register MetricEngine services**

Bind `NumericValueService`, `ScalarMetricCalculatorService`, `TimeSeriesMetricCalculatorService`, `WindowResolverService`, `ComparisonService`, `FormulaEvaluatorService`, `FormulaGraphService`, `MetricStatusInferenceService`, `BatchFormulaEvaluatorService`, `FormulaDefinitionSerializerService`, `FormulaCatalogBuilderService`, `MetricRunFingerprintService`, and Atomy-Q metric services in `AppServiceProvider`.

- [x] **Step 6: Run the unit test and verify GREEN**

Run: `cd API && php artisan test tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php`

Expected: PASS.

### Task 2: Widget Composition and Backend Endpoint Contracts

**Files:**
- Create: `API/app/Services/Metrics/MetricInputProvider.php`
- Create: `API/app/Services/Metrics/WidgetCompositionService.php`
- Create: `API/tests/Feature/Api/V1/MetricWidgetsApiTest.php`
- Modify: `API/app/Http/Controllers/Api/V1/DashboardController.php`
- Modify: `API/app/Http/Controllers/Api/V1/RfqController.php`
- Modify: `API/app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `API/routes/api.php`

- [x] **Step 1: Write failing feature tests**

Create `MetricWidgetsApiTest` with:
- `test_dashboard_widgets_return_metric_engine_payload_without_breaking_kpis`
- `test_rfq_overview_includes_widget_payload_alongside_existing_aliases`
- `test_reporting_widgets_return_metric_engine_payload`

Each test should assert `widgets.*.cards.*.key`, `status`, `formattedValue`, and `meta.fingerprint`.

- [x] **Step 2: Run feature tests and verify RED**

Run: `cd API && php artisan test tests/Feature/Api/V1/MetricWidgetsApiTest.php`

Expected: FAIL because widget routes/payloads do not exist.

- [x] **Step 3: Add MetricInputProvider**

Add tenant-scoped prepared inputs for dashboard/reporting and RFQ context. Batch queries by context and return `MetricInput` values keyed by canonical input names.

- [x] **Step 4: Add WidgetCompositionService**

Compose:
- `dashboard.procurement_pipeline_widget`
- `rfq.overview_progress_widget`
- `rfq.comparison_readiness_widget`
- `rfq.approval_status_widget`
- `reporting.kpi_summary_widget`

Return `['data' => ['widgets' => [...]], 'meta' => ['fingerprint' => ...]]` for standalone widget endpoints.

- [x] **Step 5: Wire controllers and routes**

Add `GET /dashboard/widgets` and `GET /reports/widgets`. Add `widgets` and `widget_meta` to RFQ overview response while preserving all existing RFQ overview fields. Keep existing `/dashboard/kpis`, `/reports/kpis`, `/reports/spend-trend`, and `/reports/spend-by-category` behavior compatible.

- [x] **Step 6: Run feature tests and verify GREEN**

Run: `cd API && php artisan test tests/Feature/Api/V1/MetricWidgetsApiTest.php`

Expected: PASS.

### Task 3: Minimal Frontend Payload Renderer

**Files:**
- Create: `WEB/src/types/metrics.ts`
- Create: `WEB/src/components/metrics/MetricCard.tsx`
- Create: `WEB/src/components/metrics/DashboardWidgetRenderer.tsx`
- Create: `WEB/src/components/metrics/MetricCard.test.tsx`
- Modify: `WEB/src/hooks/use-rfq-overview.ts`
- Modify: `WEB/src/app/(dashboard)/rfqs/[rfqId]/overview/page.tsx`

- [x] **Step 1: Write failing renderer tests**

Create `MetricCard.test.tsx` asserting available cards render formatted values, `no_data` cards render a clear unavailable state, and metric-grid widgets render each card.

- [x] **Step 2: Run frontend unit test and verify RED**

Run: `cd WEB && npm run test:unit -- MetricCard.test.tsx`

Expected: FAIL because metric renderer files do not exist.

- [x] **Step 3: Add metric payload types and renderer components**

Use existing `KPIScorecard` for visual consistency. Map backend progress type `ring` to `circular`, and display `formattedValue` when present. For non-available statuses, render `formattedValue` or `--` plus the reason text.

- [x] **Step 4: Preserve RFQ overview widgets in the hook**

Extend `RfqOverviewData` with `widgets?: WidgetPayload[]` and `widget_meta?: { fingerprint?: string }`, and normalize those fields from the API response.

- [x] **Step 5: Render RFQ overview metric widgets when present**

In the RFQ overview page, render `rfq.overview_progress_widget` through `DashboardWidgetRenderer` when available. Fall back to the existing page-local KPI cards when the API has no widgets.

- [x] **Step 6: Run frontend unit test and verify GREEN**

Run: `cd WEB && npm run test:unit -- MetricCard.test.tsx`

Expected: PASS.

### Task 4: Focused Regression Verification

**Files:**
- Verify only.

- [x] **Step 1: Run focused API tests**

Run:

```bash
cd API && php artisan test tests/Unit/Services/Metrics/MetricEvaluationServiceTest.php tests/Feature/Api/V1/MetricWidgetsApiTest.php tests/Feature/Api/V1/DashboardReportAiSummaryApiTest.php
cd API && php artisan test tests/Feature/RfqOverviewActivityTest.php
```

`RfqOverviewActivityTest` is intentionally run separately because it boots a fresh in-memory sqlite application; combining it after another `RefreshDatabase` feature class can reuse Laravel's migrated flag against a new empty in-memory database.

Expected: PASS.

- [x] **Step 2: Run focused WEB tests**

Run: `cd WEB && npm run test:unit -- MetricCard.test.tsx`

Expected: PASS.

- [x] **Step 3: Run final diff audit**

Run: `git diff --stat` and inspect the changed files against this plan.

Expected: changes are limited to the metric/widget implementation slice, the existing spec amendment, and the implementation plan.

### Task 5: Design Completion Extension

**Files:**
- Create: `API/app/Services/Metrics/MetricInputRegistry.php`
- Create: `API/app/Services/Metrics/ScorecardData.php`
- Create: `API/app/Services/Metrics/ScorecardService.php`
- Modify: `API/app/Adapters/InsightOperations/DashboardFactsAdapter.php`
- Modify: `API/app/Adapters/InsightOperations/GovernanceFactsAdapter.php`
- Modify: `API/app/Adapters/InsightOperations/ReportingFactsAdapter.php`
- Modify: `API/app/Http/Controllers/Api/V1/VendorGovernanceController.php`
- Modify: `API/app/Services/Metrics/AppMetricDefinitionCatalog.php`
- Modify: `API/app/Services/Metrics/MetricInputProvider.php`
- Modify: `API/app/Services/Metrics/WidgetCompositionService.php`
- Modify: `API/routes/api.php`
- Create: `WEB/src/components/metrics/MetricCardGrid.tsx`
- Create: `WEB/src/components/metrics/MetricScorecard.tsx`
- Create: `WEB/src/components/metrics/WidgetSection.tsx`
- Modify: `WEB/src/components/metrics/DashboardWidgetRenderer.tsx`
- Modify: `WEB/src/types/metrics.ts`
- Modify: `WEB/src/app/(dashboard)/page.tsx`
- Modify: `WEB/src/app/(dashboard)/reporting/page.tsx`

- [x] **Step 1: Add missing registry and scorecard abstractions**

Add `MetricInputRegistry`, `ScorecardData`, and `ScorecardService` so domain inputs and scorecards are not screen-local.

- [x] **Step 2: Add vendor governance widget payloads**

Add `GET /vendors/{id}/widgets` and compose `vendor.governance_scorecard_widget`, `vendor.compliance_evidence_widget`, and `vendor.finding_risk_widget`.

- [x] **Step 3: Render shared frontend scorecard/widget wrappers**

Add `MetricCardGrid`, `MetricScorecard`, and `WidgetSection`, and update `DashboardWidgetRenderer` to support scorecard widgets.

- [x] **Step 4: Move existing KPI and governance score facts onto the central evaluator**

Keep existing `/dashboard/kpis`, `/reports/kpis`, and vendor governance contracts compatible while sourcing overlapping KPI and score facts through `MetricEvaluationService`.

- [x] **Step 5: Verify extension coverage**

Run focused API and WEB tests covering dashboard, reporting, RFQ overview, vendor widgets, and shared scorecard rendering.
