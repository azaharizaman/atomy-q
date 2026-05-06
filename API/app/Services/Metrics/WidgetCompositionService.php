<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Rfq;
use App\Models\Vendor;

final readonly class WidgetCompositionService
{
    public function __construct(
        private AppMetricDefinitionCatalog $catalog,
        private MetricInputRegistry $inputRegistry,
        private MetricEvaluationService $evaluator,
        private ScorecardService $scorecardService,
    ) {}

    /**
     * @return array{data: array{widgets: list<array<string, mixed>>}, meta: array{fingerprint: string}}
     */
    public function dashboardWidgets(string $tenantId): array
    {
        return $this->compose(
            tenantId: $tenantId,
            widgetKeys: [
                'dashboard.procurement_pipeline_widget',
                'dashboard.savings_performance_widget',
                'dashboard.cycle_time_widget',
                'dashboard.risk_alerts_widget',
                'dashboard.activity_freshness_widget',
                'dashboard.vendor_health_widget',
            ],
            inputs: $this->inputRegistry->dashboard($tenantId),
            titles: [
                'dashboard.procurement_pipeline_widget' => 'Procurement Pipeline',
                'dashboard.savings_performance_widget' => 'Savings Performance',
                'dashboard.cycle_time_widget' => 'Cycle Time',
                'dashboard.risk_alerts_widget' => 'Risk Alerts',
                'dashboard.activity_freshness_widget' => 'Activity Freshness',
                'dashboard.vendor_health_widget' => 'Vendor Health',
            ],
            metadata: ['tenant_id' => $tenantId, 'surface' => 'dashboard'],
        );
    }

    /**
     * @return array{data: array{widgets: list<array<string, mixed>>}, meta: array{fingerprint: string}}
     */
    public function rfqOverviewWidgets(string $tenantId, Rfq $rfq): array
    {
        return $this->compose(
            tenantId: $tenantId,
            widgetKeys: [
                'rfq.overview_progress_widget',
                'rfq.comparison_readiness_widget',
                'rfq.approval_status_widget',
                'rfq.schedule_health_widget',
            ],
            inputs: $this->inputRegistry->rfqOverview($tenantId, $rfq),
            titles: [
                'rfq.overview_progress_widget' => 'RFQ Progress',
                'rfq.comparison_readiness_widget' => 'Comparison Readiness',
                'rfq.approval_status_widget' => 'Approval Status',
                'rfq.schedule_health_widget' => 'Schedule Health',
            ],
            metadata: ['tenant_id' => $tenantId, 'rfq_id' => $rfq->id, 'surface' => 'rfq.overview'],
        );
    }

    /**
     * @return array{data: array{widgets: list<array<string, mixed>>}, meta: array{fingerprint: string}}
     */
    public function reportingWidgets(string $tenantId): array
    {
        return $this->compose(
            tenantId: $tenantId,
            widgetKeys: [
                'reporting.kpi_summary_widget',
                'reporting.spend_trend_widget',
                'reporting.category_spend_widget',
            ],
            inputs: $this->inputRegistry->reporting($tenantId),
            titles: [
                'reporting.kpi_summary_widget' => 'KPI Summary',
                'reporting.spend_trend_widget' => 'Spend Trend',
                'reporting.category_spend_widget' => 'Category Spend',
            ],
            metadata: ['tenant_id' => $tenantId, 'surface' => 'reporting'],
        );
    }

    /**
     * @return array{data: array{widgets: list<array<string, mixed>>}, meta: array{fingerprint: string}}
     */
    public function vendorWidgets(string $tenantId, Vendor $vendor): array
    {
        $inputSet = $this->inputRegistry->vendorGovernance($tenantId, $vendor);

        return $this->compose(
            tenantId: $tenantId,
            widgetKeys: [
                'vendor.governance_scorecard_widget',
                'vendor.compliance_evidence_widget',
                'vendor.finding_risk_widget',
            ],
            inputs: $inputSet['inputs'],
            titles: [
                'vendor.governance_scorecard_widget' => 'Governance Scorecard',
                'vendor.compliance_evidence_widget' => 'Compliance Evidence',
                'vendor.finding_risk_widget' => 'Finding Risk',
            ],
            metadata: ['tenant_id' => $tenantId, 'vendor_id' => $vendor->id, 'surface' => 'vendor.governance'],
            warnings: $inputSet['warnings'],
        );
    }

    /**
     * @param list<string> $widgetKeys
     * @param array<string, \Nexus\MetricEngine\ValueObjects\MetricInput|\Nexus\MetricEngine\ValueObjects\MetricSeries> $inputs
     * @param array<string, string> $titles
     * @param array<string, mixed> $metadata
     * @param list<string> $warnings
     * @return array{data: array{widgets: list<array<string, mixed>>}, meta: array{fingerprint: string}}
     */
    private function compose(string $tenantId, array $widgetKeys, array $inputs, array $titles, array $metadata, array $warnings = []): array
    {
        $allMetricKeys = [];
        foreach ($widgetKeys as $widgetKey) {
            $allMetricKeys = array_merge($allMetricKeys, $this->catalog->widgetMetricKeys($widgetKey));
        }
        $allMetricKeys = array_values(array_unique($allMetricKeys));

        $evaluation = $this->evaluator->evaluate($allMetricKeys, $inputs, $metadata);
        $widgets = [];

        foreach ($widgetKeys as $widgetKey) {
            $cards = [];
            foreach ($this->catalog->widgetMetricKeys($widgetKey) as $metricKey) {
                if (isset($evaluation['cards'][$metricKey])) {
                    $cards[] = $evaluation['cards'][$metricKey];
                }
            }

            if ($widgetKey === 'vendor.governance_scorecard_widget') {
                $scorecard = $this->scorecardService->make(
                    key: 'vendor.governance_scorecard',
                    title: 'Governance Scorecard',
                    metrics: $cards,
                    warnings: $warnings,
                );
                $widgets[] = (new WidgetData(
                    key: $widgetKey,
                    title: $titles[$widgetKey] ?? $widgetKey,
                    kind: 'scorecard',
                    status: $scorecard->status,
                    scorecard: $scorecard,
                ))->toArray();

                continue;
            }

            $widgets[] = (new WidgetData(
                key: $widgetKey,
                title: $titles[$widgetKey] ?? $widgetKey,
                kind: 'metric_grid',
                status: $this->widgetStatus($cards),
                cards: $cards,
            ))->toArray();
        }

        return [
            'data' => ['widgets' => $widgets],
            'meta' => ['fingerprint' => $evaluation['fingerprint']],
        ];
    }

    /**
     * @param list<MetricCardData> $cards
     */
    private function widgetStatus(array $cards): string
    {
        foreach ($cards as $card) {
            if ($card->status === 'error') {
                return 'error';
            }
        }

        foreach ($cards as $card) {
            if ($card->status === 'available') {
                return 'available';
            }
        }

        return $cards[0]->status ?? 'not_available';
    }
}
