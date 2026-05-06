# MetricEngine KPI, Scorecard, and Widget Refactor Design

Date: 2026-05-06
Status: Revised after MetricEngine package update
Scope: Atomy-Q API and WEB

Revision note: after the Nexus team refactored `azaharizaman/nexus-metric-engine` and Atomy-Q pulled the updated package through Composer, this design no longer treats batch evaluation, formula catalogs, formula serialization, dependency graphs, neutral statuses, audit traces, run fingerprints, neutral banding, rounding modes, or typed period comparison as Atomy-Q responsibilities. Atomy-Q consumes those package capabilities and keeps the application, Laravel, tenant, domain, widget, and display boundaries.

## Purpose

Atomy-Q must use `Nexus\MetricEngine` as the sole calculation engine for existing KPIs, scorecards, and dashboard-style widgets across the application. The refactor must centralize metric production so the same KPI card or scorecard can appear on the main dashboard, RFQ pages, vendor pages, reporting pages, and future workflow surfaces without copying calculation or formatting logic.

This design is part of a broader refactor that abstracts KPI, scorecard, and dashboard widget production into an application service layer. Future user-created dashboard widgets should be possible, but they are not included in this implementation slice.

## Current Problem

KPI and scorecard production is scattered across backend adapters, services, controllers, and frontend page-local mapping.

Current calculation and composition points include:

- `API/app/Adapters/InsightOperations/DashboardFactsAdapter.php`
- `API/app/Adapters/InsightOperations/ReportingFactsAdapter.php`
- `API/app/Services/VendorGovernanceScoreService.php`
- `API/app/Http/Controllers/Api/V1/DashboardController.php`
- `API/app/Http/Controllers/Api/V1/ReportController.php`
- `WEB/src/app/(dashboard)/page.tsx`
- `WEB/src/app/(dashboard)/rfqs/[rfqId]/overview/page.tsx`

This makes reuse expensive. The same KPI concept can be recalculated or remapped in multiple places, and frontend pages know too much about KPI construction.

## Scope

Included:

- Existing system-defined KPIs.
- Existing scorecards.
- Existing KPI card placements.
- Additional system-owned widgets that improve the current application look and feel.
- Main dashboard widgets.
- KPI cards and scorecards on other current screens, including RFQ overview, vendor governance/compliance surfaces, and reporting surfaces.
- A reusable backend metric/widget production layer.
- Reusable frontend payload-driven metric and scorecard components.

Excluded:

- User-created widgets.
- User-authored formulas.
- Admin UI for widget configuration.
- Widget marketplace or sharing.
- Replacing `Nexus\MetricEngine` with app-local calculation logic.

## Design Principles

- Atomy-Q owns metric production.
- `Nexus\MetricEngine` owns deterministic formula mechanics: neutral formula definitions, formula catalogs, formula references, dependency graphs, scalar and time-series evaluation, result outcomes, audit traces, run fingerprints, neutral banding, rounding behavior, and typed period comparison.
- Atomy-Q owns app-level metric identity, domain meaning, tenant scoping, source fact retrieval, Laravel wiring, widget composition, display metadata, authorization, endpoint compatibility, and frontend rendering.
- No application service except the central metric evaluator should call `Nexus\MetricEngine` directly.
- Metric keys are domain-canonical.
- Widgets are screen-specific compositions of canonical metrics.
- Backend owns calculation orchestration, metric identity, scorecard composition, availability state, and display-ready metadata.
- Frontend owns placement, layout, responsive behavior, interaction links, loading states, and rendering.
- Source facts must be fetched in batches per widget set, not one query per card.
- No-data and unavailable states must be explicit; synthetic zeros must not hide missing source domains.
- MetricEngine status labels are neutral; Atomy-Q maps them to user-facing copy, tones, and domain semantics.
- MetricEngine band labels must remain neutral if used directly. Domain labels such as "healthy", "risky", "preferred vendor", or "non-compliant" remain Atomy-Q domain language.
- MetricEngine may apply caller-supplied unit and precision rules, but Atomy-Q must not treat it as a money/currency policy engine.

## Recommended Architecture

The API should introduce a centralized metric production layer:

```text
Controller
  -> WidgetCompositionService
    -> MetricEvaluationService
      -> AppMetricDefinitionCatalog
      -> Nexus FormulaCatalog
      -> MetricInputRegistry
        -> Domain Input Providers
      -> Nexus\MetricEngine BatchFormulaEvaluatorService
```

### MetricEngine Capability Baseline

The installed package now exposes the following capabilities that this design can rely on:

- `FormulaCatalog` and `FormulaCatalogBuilderService` for immutable in-memory formula sets.
- `FormulaDefinitionSerializerService` for array-backed formula definitions.
- `FormulaReference` and `FormulaGraphService` for explicit formula dependencies, topological ordering, cycle detection, and missing-reference validation.
- `BatchFormulaEvaluatorService` for evaluating many formulas into keyed outcomes.
- `MetricEvaluationOutcome` and `MetricResultStatus` for `available`, `no_data`, `not_available`, and `error`.
- `MetricAuditTrace` and `MetricEvaluationOptions::withAuditTrace()` for deterministic explanation payloads.
- `MetricRunFingerprintService` for stable hashes over formulas, dependency graph, prepared inputs, and caller metadata.
- `BandedScoreService`, `BandDefinition`, and `BandedScore` for neutral banding.
- `PeriodKey`, `PeriodComparatorService`, and period granularity enums for typed period handling.
- `PrecisionPolicy` and `RoundingMode` for caller-controlled rounding behavior.

The package remains framework-agnostic. Laravel service providers, cache stores, repository bindings, route concerns, and application policy remain outside MetricEngine.

### AppMetricDefinitionCatalog

`AppMetricDefinitionCatalog` owns Atomy-Q's system metric definitions and converts their formula portion into a Nexus `FormulaCatalog`.

It defines:

- metric key
- label
- value type
- Nexus formula definition or serialized formula payload
- required inputs
- domain/context requirements
- display hints
- precision and unit rules
- availability rules
- domain status mapping rules
- optional neutral band definitions
- widget reuse metadata

Example canonical metric keys:

- `procurement.active_rfqs`
- `procurement.pending_approvals`
- `procurement.quote_intake_count`
- `procurement.awards_in_flight`
- `procurement.total_savings`
- `procurement.total_spend`
- `rfq.quotes_received`
- `rfq.expected_quotes`
- `rfq.normalization_progress_pct`
- `rfq.comparison_runs_count`
- `rfq.pending_approvals`
- `vendor.esg_score`
- `vendor.compliance_health_score`
- `vendor.risk_watch_score`
- `vendor.evidence_freshness_score`
- `vendor.open_severe_findings`
- `reporting.spend_by_category`
- `reporting.monthly_spend`

### MetricInputRegistry

`MetricInputRegistry` maps metric inputs to domain input providers.

Example mappings:

- `rfq.total_quotes` -> `RfqMetricInputProvider`
- `rfq.accepted_quotes` -> `RfqMetricInputProvider`
- `vendor.expired_evidence_count` -> `VendorMetricInputProvider`
- `vendor.open_findings_by_severity` -> `VendorMetricInputProvider`
- `procurement.signed_off_award_amounts` -> `ProcurementMetricInputProvider`
- `procurement.rfq_estimated_values` -> `ProcurementMetricInputProvider`

### MetricInputProvider

Input providers fetch raw facts for one domain and one context.

Initial providers:

- `DashboardMetricInputProvider`
- `ProcurementMetricInputProvider`
- `RfqMetricInputProvider`
- `VendorMetricInputProvider`
- `ReportingMetricInputProvider`

Providers must batch their queries so a widget set can evaluate many metrics from shared inputs.

Example contexts:

- Dashboard context: tenant-wide procurement, approvals, awards, vendor risk, and activity facts.
- RFQ context: one RFQ plus quote, normalization, comparison, schedule, and approval facts.
- Vendor context: one vendor plus evidence, findings, sanctions, and compliance facts.
- Reporting context: tenant-wide award and spend facts.

### MetricEvaluationService

`MetricEvaluationService` is the only Atomy-Q service that calls `Nexus\MetricEngine`.

It handles:

- building `MetricInput` and `MetricSeries`
- building or loading the Nexus `FormulaCatalog`
- calling `BatchFormulaEvaluatorService`
- mapping `MetricEvaluationOutcome` and `MetricResultStatus` into stable application DTO statuses
- formatting raw results into application metric DTOs
- attaching app display metadata and user-facing reason text
- attaching package audit traces when requested by debug/admin workflows
- attaching package run fingerprints when reproducibility matters
- request-level memoization
- Laravel service construction and dependency wiring

Metric status values:

- `available`
- `not_available`
- `no_data`
- `error`

### ScorecardService

`ScorecardService` groups metric results into scorecards.

Numeric scores should be produced through `MetricEvaluationService`. Neutral numeric bands may use MetricEngine's `BandedScoreService`, but domain meaning and scorecard copy remain Atomy-Q rules. Domain warning flags still belong in domain code when they are not pure numeric formulas.

Initial scorecards:

- `dashboard.procurement_pipeline_scorecard`
- `rfq.overview_progress_scorecard`
- `vendor.governance_scorecard`
- `reporting.kpi_summary_scorecard`

### WidgetCompositionService

`WidgetCompositionService` builds screen-ready widget payloads.

It answers:

- dashboard widgets for a tenant
- RFQ overview widgets for a tenant and RFQ
- vendor governance widgets for a tenant and vendor
- reporting widgets for a tenant

Widgets are system-owned bundles of canonical metrics and scorecards.

## System Widget Catalog

### Main Dashboard

`dashboard.procurement_pipeline_widget`

- `procurement.active_rfqs`
- `procurement.pending_approvals`
- `procurement.quote_intake_count`
- `procurement.awards_in_flight`

`dashboard.savings_performance_widget`

- `procurement.total_savings`
- `procurement.savings_percentage`
- `procurement.awarded_spend`
- `procurement.estimated_vs_awarded_delta`

`dashboard.cycle_time_widget`

- `procurement.average_cycle_time_days`
- `procurement.aging_rfqs`
- `procurement.rfqs_past_deadline`

`dashboard.risk_alerts_widget`

- `vendor.open_high_critical_findings`
- `vendor.overdue_remediation_count`
- `vendor.expired_compliance_evidence_count`

`dashboard.activity_freshness_widget`

- latest RFQ activity
- `procurement.stale_rfqs`
- `rfq.normalization_backlog`

`dashboard.vendor_health_widget`

- `vendor.approved_vendors`
- `vendor.pending_review_vendors`
- `vendor.vendors_with_severe_findings`

### RFQ Overview

`rfq.overview_progress_widget`

- `rfq.quotes_received`
- `rfq.expected_quotes`
- `rfq.quote_receipt_pct`
- `rfq.normalization_progress_pct`

`rfq.comparison_readiness_widget`

- `rfq.accepted_quotes`
- `rfq.needs_review_quote_lines`
- `rfq.comparison_runs_count`
- `rfq.latest_comparison_status`

`rfq.approval_status_widget`

- `rfq.pending_approvals`
- `rfq.approval_status`
- `rfq.overdue_approval_count`
- `rfq.current_approval_gate`

`rfq.schedule_health_widget`

- `rfq.days_until_submission_deadline`
- `rfq.days_until_closing_date`
- `rfq.overdue_milestone_count`
- `rfq.current_stage_aging_days`

### Vendor Governance

`vendor.governance_scorecard_widget`

- `vendor.esg_score`
- `vendor.compliance_health_score`
- `vendor.risk_watch_score`
- `vendor.evidence_freshness_score`

`vendor.compliance_evidence_widget`

- `vendor.accepted_evidence_count`
- `vendor.expired_evidence_count`
- `vendor.stale_evidence_count`
- `vendor.missing_sanctions_check`

`vendor.finding_risk_widget`

- `vendor.open_findings`
- `vendor.open_severe_findings`
- `vendor.overdue_remediation_count`
- `vendor.finding_severity_weighted_score`

### Reporting

`reporting.kpi_summary_widget`

- `procurement.total_spend`
- `procurement.active_rfqs`
- `procurement.total_savings`
- `procurement.awarded_spend`

`reporting.spend_trend_widget`

- `reporting.monthly_spend`
- `reporting.current_period_spend`
- `reporting.previous_period_spend`
- `reporting.period_over_period_delta`

`reporting.category_spend_widget`

- `reporting.spend_by_category`
- `reporting.category_count`
- `reporting.largest_spend_category`
- `reporting.uncategorized_spend`

## API Contract Direction

Existing endpoints should remain compatible during the first migration:

- `GET /dashboard/kpis`
- `GET /reports/kpis`
- `GET /reports/spend-trend`
- `GET /reports/spend-by-category`
- `GET /rfqs/{id}/overview`
- vendor governance endpoints

Internally, these endpoints should consume the central metric services.

New widget-oriented endpoints may be added where they reduce frontend duplication immediately:

- `GET /dashboard/widgets`
- `GET /rfqs/{id}/widgets`
- `GET /vendors/{id}/widgets`
- `GET /reports/widgets`

The implementation plan must decide which of these routes are needed in the first slice. Existing endpoint compatibility remains mandatory even if new widget routes are added.

## Frontend Contract

The frontend should consume reusable metric and widget payloads and render them through shared components.

Metric card payload:

```ts
type MetricStatus = 'available' | 'not_available' | 'no_data' | 'error';

type MetricCardPayload = {
  key: string;
  label: string;
  value: number | string | null;
  formattedValue: string;
  unit?: string;
  status: MetricStatus;
  reason?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
  icon?: string;
  href?: string;
  progress?: {
    value: number;
    max?: number;
    type: 'bar' | 'ring';
  };
  trend?: {
    direction: 'up' | 'down' | 'flat';
    label: string;
    value?: number;
  };
};
```

Scorecard payload:

```ts
type ScorecardPayload = {
  key: string;
  title: string;
  subtitle?: string;
  status: MetricStatus;
  metrics: MetricCardPayload[];
  warnings?: string[];
};
```

Widget payload:

```ts
type WidgetPayload = {
  key: string;
  title: string;
  subtitle?: string;
  kind: 'metric_grid' | 'scorecard' | 'trend' | 'breakdown' | 'activity' | 'risk_list';
  status: MetricStatus;
  cards?: MetricCardPayload[];
  scorecard?: ScorecardPayload;
  rows?: Record<string, unknown>[];
  series?: Record<string, unknown>[];
};
```

Shared WEB components:

- `MetricCard`
- `MetricCardGrid`
- `MetricScorecard`
- `DashboardWidgetRenderer`
- `WidgetSection`

Existing components should be adapted where practical:

- `WEB/src/components/ds/KPIScorecard.tsx` becomes the base metric-card renderer.
- `WEB/src/components/dashboard/DashboardCards.tsx` becomes composition-focused.
- `WEB/src/app/(dashboard)/page.tsx` stops constructing page-local KPI definitions.
- `WEB/src/app/(dashboard)/rfqs/[rfqId]/overview/page.tsx` stops manually calculating the four KPI cards from local overview fields.

## Migration Plan

### Phase 1: Backend Foundation

- Add Atomy-Q metric DTOs for metric cards, scorecards, widgets, statuses, trends, and progress.
- Add `AppMetricDefinitionCatalog` with system-owned metric definitions and Nexus formula payloads.
- Add `MetricInputRegistry` and domain input providers.
- Add `MetricEvaluationService` as the only app service that calls `Nexus\MetricEngine`.
- Wire MetricEngine services in Atomy-Q's Laravel container or an Atomy-Q-owned adapter provider, not inside the Layer 1 package.
- Use `FormulaCatalogBuilderService` or `FormulaDefinitionSerializerService` to construct the Nexus `FormulaCatalog`.
- Use `BatchFormulaEvaluatorService` for widget-set evaluation.
- Map `MetricEvaluationOutcome` statuses into Atomy-Q DTO statuses and user-facing reasons.
- Preserve optional `MetricAuditTrace` and `MetricRunFingerprint` fields for audit/debug contexts.
- Add app-only source-domain status mapping for cases MetricEngine cannot know, such as provider-not-implemented or tenant feature unavailable.

### Phase 2: Dashboard Migration

- Replace `DashboardFactsAdapter` hand calculations with centralized metric evaluation.
- Keep `GET /dashboard/kpis` response compatible initially.
- Add richer widget payloads internally or through `GET /dashboard/widgets`.
- Replace frontend page-local dashboard KPI mapping with shared metric card payload rendering.

### Phase 3: RFQ Overview Migration

- Move RFQ overview KPI calculations into the metric/widget service.
- Keep the existing RFQ overview screen layout.
- Feed quotes received, normalization progress, comparison runs, and pending approvals from reusable metric cards.

### Phase 4: Vendor Governance Migration

- Convert numeric score production in `VendorGovernanceScoreService` to centralized scorecard metrics.
- Use MetricEngine neutral banding only for numeric ranges where useful.
- Keep vendor governance labels, warnings, eligibility language, and risk/compliance meaning in Atomy-Q domain code.
- Return the same governance scorecard anywhere it is needed.

### Phase 5: Reporting Migration

- Replace reporting KPI and spend calculations with shared metric definitions.
- Reuse dashboard/procurement metrics where concepts are identical.
- Use MetricEngine time-series primitives and typed period handling for trend and period-over-period metrics.
- Keep money/currency interpretation, FX rules, and accounting semantics outside MetricEngine.

## Testing Strategy

Backend tests:

- Unit test each Atomy-Q metric definition and formula serialization payload.
- Unit test Atomy-Q mapping from `MetricEvaluationOutcome` to application DTO statuses and reason text.
- Feature test dashboard KPI endpoints still return compatible responses.
- Feature test widget payloads for dashboard, RFQ overview, vendor governance, and reporting.
- Regression test tenant scoping for every input provider.
- Test no-data cases explicitly.
- Test audit trace and fingerprint presence only where the app explicitly requests them.
- Add package integration smoke tests around `BatchFormulaEvaluatorService`, `FormulaReference`, neutral bands, rounding mode, and typed period inputs rather than duplicating MetricEngine's own unit suite.

Frontend tests:

- Unit test `MetricCard`, `MetricCardGrid`, and scorecard renderer states.
- Update dashboard page tests to assert rendered metric payloads.
- Update RFQ overview tests so the page renders API-provided card payloads.
- Add unavailable and no-data visual state tests.
- Keep Playwright smoke focused on dashboard and RFQ overview rendering.

## Risks

- Atomy-Q may accidentally duplicate package mechanics now provided by MetricEngine. The implementation plan must consume package services directly through the central evaluator.
- MetricEngine remains domain-neutral, so Atomy-Q must still own persistence, domain fetching, tenant scoping, widget composition, display metadata, and user-facing semantics.
- If metric definitions are screen-specific instead of domain-canonical, reuse will fail.
- If each card triggers its own query, dashboard performance will degrade.
- Existing frontend tests may assume page-local fallback/mock calculations.
- Scorecards with warning flags may not fit pure numeric formulas and should not be forced into MetricEngine bands.
- Existing endpoint compatibility matters during migration.
- Package version drift can change exact class signatures. The implementation plan should verify the installed `API/vendor/azaharizaman/nexus-metric-engine` API before coding.

## MetricEngine Boundary Decision Log

The earlier maintainer feedback pack has been superseded by the Nexus response and installed package surface.

Delivered in MetricEngine and consumed by this design:

- Batch formula evaluation.
- Formula registry/catalog support.
- Array/config serialization for `FormulaDefinition`.
- Dependency graph support through explicit `FormulaReference`.
- Neutral result statuses through `MetricEvaluationOutcome`.
- Optional deterministic audit/explanation output.
- Neutral score banding helpers.
- Run fingerprint helpers.
- `RoundingMode` support in `PrecisionPolicy`.
- Stronger period handling through typed period keys and comparators.

Still outside MetricEngine and owned by Atomy-Q or another package:

- Laravel service provider bindings for this application.
- Domain scorecard meaning, health/risk/compliance labels, and user-facing remediation language.
- Cache storage and invalidation.
- Money/currency semantics, FX rules, accounting policy, and finance-specific value objects.
- YAML/JSON file loading. MetricEngine handles array serialization; Atomy-Q owns config file loading if it later chooses to store formula definitions outside code.

## Approval

The original design direction was approved in chat on 2026-05-06. This revision incorporates the Nexus MetricEngine package update pulled into Atomy-Q on 2026-05-06. Implementation planning should proceed from this revised document and keep user-created widgets out of scope for the first slice.
