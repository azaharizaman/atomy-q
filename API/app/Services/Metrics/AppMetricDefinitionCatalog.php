<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Nexus\MetricEngine\Services\FormulaDefinitionSerializerService;
use Nexus\MetricEngine\ValueObjects\FormulaCatalog;

final readonly class AppMetricDefinitionCatalog
{
    public function __construct(
        private FormulaDefinitionSerializerService $serializer,
    ) {}

    /**
     * @return list<string>
     */
    public function widgetMetricKeys(string $widgetKey): array
    {
        return match ($widgetKey) {
            'dashboard.procurement_pipeline_widget' => [
                'procurement.active_rfqs',
                'procurement.pending_approvals',
                'procurement.quote_intake_count',
                'procurement.awards_in_flight',
            ],
            'dashboard.savings_performance_widget' => [
                'procurement.total_savings',
                'procurement.savings_percentage',
                'procurement.awarded_spend',
                'procurement.estimated_vs_awarded_delta',
            ],
            'dashboard.cycle_time_widget' => [
                'procurement.average_cycle_time_days',
                'procurement.aging_rfqs',
                'procurement.rfqs_past_deadline',
            ],
            'dashboard.risk_alerts_widget' => [
                'vendor.open_high_critical_findings',
                'vendor.overdue_remediation_count',
                'vendor.expired_compliance_evidence_count',
            ],
            'dashboard.activity_freshness_widget' => [
                'procurement.stale_rfqs',
                'rfq.normalization_backlog',
            ],
            'dashboard.vendor_health_widget' => [
                'vendor.approved_vendors',
                'vendor.pending_review_vendors',
                'vendor.vendors_with_severe_findings',
            ],
            'rfq.overview_progress_widget' => [
                'rfq.quotes_received',
                'rfq.expected_quotes',
                'rfq.quote_receipt_pct',
                'rfq.normalization_progress_pct',
            ],
            'rfq.comparison_readiness_widget' => [
                'rfq.accepted_quotes',
                'rfq.needs_review_quote_lines',
                'rfq.comparison_runs_count',
                'rfq.latest_comparison_status',
            ],
            'rfq.approval_status_widget' => [
                'rfq.pending_approvals',
                'rfq.approval_status',
                'rfq.overdue_approval_count',
                'rfq.current_approval_gate',
            ],
            'rfq.schedule_health_widget' => [
                'rfq.days_until_submission_deadline',
                'rfq.days_until_closing_date',
                'rfq.overdue_milestone_count',
                'rfq.current_stage_aging_days',
            ],
            'reporting.kpi_summary_widget' => [
                'reporting.total_spend',
                'reporting.active_rfqs',
                'reporting.total_savings',
                'procurement.awarded_spend',
            ],
            'reporting.spend_trend_widget' => [
                'reporting.monthly_spend',
                'reporting.current_period_spend',
                'reporting.previous_period_spend',
                'reporting.period_over_period_delta',
            ],
            'reporting.category_spend_widget' => [
                'reporting.spend_by_category',
                'reporting.category_count',
                'reporting.largest_spend_category',
                'reporting.uncategorized_spend',
            ],
            'vendor.governance_scorecard_widget' => [
                'vendor.esg_score',
                'vendor.compliance_health_score',
                'vendor.risk_watch_score',
                'vendor.evidence_freshness_score',
            ],
            'vendor.compliance_evidence_widget' => [
                'vendor.accepted_evidence_count',
                'vendor.expired_evidence_count',
                'vendor.stale_evidence_count',
                'vendor.missing_sanctions_check',
            ],
            'vendor.finding_risk_widget' => [
                'vendor.open_findings',
                'vendor.open_severe_findings',
                'vendor.overdue_remediation_count',
                'vendor.finding_severity_weighted_score',
            ],
            default => [],
        };
    }

    public function get(string $key): AppMetricDefinition
    {
        return $this->definitions()[$key] ?? throw new \InvalidArgumentException("Unknown app metric [{$key}].");
    }

    /**
     * @param list<string> $keys
     */
    public function formulaCatalogFor(array $keys): FormulaCatalog
    {
        $formulas = [];

        foreach ($keys as $key) {
            $formulas[] = $this->serializer->fromArray($this->get($key)->formulaPayload);
        }

        return new FormulaCatalog($formulas);
    }

    /**
     * @return array<string, AppMetricDefinition>
     */
    private function definitions(): array
    {
        $definitions = [
            $this->sumMetric('procurement.active_rfqs', 'Active RFQs'),
            $this->sumMetric('procurement.pending_approvals', 'Pending Approvals'),
            $this->sumMetric('procurement.quote_intake_count', 'Quotes In Intake'),
            $this->sumMetric('procurement.awards_in_flight', 'Awards In Flight'),
            $this->sumMetric('procurement.total_savings', 'Total Savings', 'USD'),
            $this->sumMetric('procurement.savings_percentage', 'Savings Percentage', 'percent'),
            $this->sumMetric('procurement.awarded_spend', 'Awarded Spend', 'USD'),
            $this->sumMetric('procurement.estimated_vs_awarded_delta', 'Estimated vs Awarded Delta', 'USD'),
            $this->sumMetric('procurement.average_cycle_time_days', 'Average Cycle Time'),
            $this->sumMetric('procurement.aging_rfqs', 'Aging RFQs'),
            $this->sumMetric('procurement.rfqs_past_deadline', 'RFQs Past Deadline'),
            $this->sumMetric('procurement.stale_rfqs', 'Stale RFQs'),
            $this->sumMetric('rfq.quotes_received', 'Quotes Received'),
            $this->sumMetric('rfq.expected_quotes', 'Expected Quotes'),
            $this->sumMetric('rfq.quote_receipt_pct', 'Quote Receipt', 'percent', progress: ['value_from' => 'rfq.quote_receipt_pct', 'type' => 'bar']),
            $this->sumMetric('rfq.normalization_progress_pct', 'Normalization Progress', 'percent', progress: ['value_from' => 'rfq.normalization_progress_pct', 'type' => 'bar']),
            $this->sumMetric('rfq.normalization_backlog', 'Normalization Backlog'),
            $this->sumMetric('rfq.accepted_quotes', 'Accepted Quotes'),
            $this->sumMetric('rfq.needs_review_quote_lines', 'Needs Review'),
            $this->sumMetric('rfq.comparison_runs_count', 'Comparison Runs'),
            $this->sumMetric('rfq.latest_comparison_status', 'Latest Comparison Status'),
            $this->sumMetric('rfq.pending_approvals', 'Pending Approvals'),
            $this->sumMetric('rfq.approved_approvals', 'Approved Approvals'),
            $this->sumMetric('rfq.rejected_approvals', 'Rejected Approvals'),
            $this->sumMetric('rfq.approval_status', 'Approval Status'),
            $this->sumMetric('rfq.overdue_approval_count', 'Overdue Approvals'),
            $this->sumMetric('rfq.current_approval_gate', 'Current Approval Gate'),
            $this->sumMetric('rfq.days_until_submission_deadline', 'Submission Deadline'),
            $this->sumMetric('rfq.days_until_closing_date', 'Closing Date'),
            $this->sumMetric('rfq.overdue_milestone_count', 'Overdue Milestones'),
            $this->sumMetric('rfq.current_stage_aging_days', 'Current Stage Aging'),
            $this->sumMetric('reporting.total_spend', 'Total Spend', 'USD'),
            $this->sumMetric('reporting.active_rfqs', 'Active RFQs'),
            $this->sumMetric('reporting.total_savings', 'Total Savings', 'USD'),
            $this->sumMetric('reporting.monthly_spend', 'Monthly Spend', 'USD'),
            $this->sumMetric('reporting.current_period_spend', 'Current Period Spend', 'USD'),
            $this->sumMetric('reporting.previous_period_spend', 'Previous Period Spend', 'USD'),
            $this->sumMetric('reporting.period_over_period_delta', 'Period Delta', 'USD'),
            $this->sumMetric('reporting.spend_by_category', 'Spend By Category', 'USD'),
            $this->sumMetric('reporting.category_count', 'Category Count'),
            $this->sumMetric('reporting.largest_spend_category', 'Largest Spend Category', 'USD'),
            $this->sumMetric('reporting.uncategorized_spend', 'Uncategorized Spend', 'USD'),
            $this->sumMetric('vendor.esg_score', 'ESG'),
            $this->sumMetric('vendor.compliance_health_score', 'Compliance'),
            $this->sumMetric('vendor.risk_watch_score', 'Risk Watch'),
            $this->sumMetric('vendor.evidence_freshness_score', 'Evidence Freshness'),
            $this->sumMetric('vendor.open_high_critical_findings', 'High/Critical Findings'),
            $this->sumMetric('vendor.overdue_remediation_count', 'Overdue Remediation'),
            $this->sumMetric('vendor.expired_compliance_evidence_count', 'Expired Compliance Evidence'),
            $this->sumMetric('vendor.approved_vendors', 'Approved Vendors'),
            $this->sumMetric('vendor.pending_review_vendors', 'Pending Review Vendors'),
            $this->sumMetric('vendor.vendors_with_severe_findings', 'Vendors With Severe Findings'),
            $this->sumMetric('vendor.accepted_evidence_count', 'Accepted Evidence'),
            $this->sumMetric('vendor.expired_evidence_count', 'Expired Evidence'),
            $this->sumMetric('vendor.stale_evidence_count', 'Stale Evidence'),
            $this->sumMetric('vendor.missing_sanctions_check', 'Missing Sanctions Check'),
            $this->sumMetric('vendor.open_findings', 'Open Findings'),
            $this->sumMetric('vendor.open_severe_findings', 'Severe Findings'),
            $this->sumMetric('vendor.overdue_remediation_count', 'Overdue Remediation'),
            $this->sumMetric('vendor.finding_severity_weighted_score', 'Finding Severity Weight'),
        ];

        return array_combine(
            array_map(static fn (AppMetricDefinition $definition): string => $definition->key, $definitions),
            $definitions,
        );
    }

    /**
     * @param array<string, mixed>|null $progress
     */
    private function sumMetric(string $key, string $label, ?string $unit = null, ?array $progress = null): AppMetricDefinition
    {
        return new AppMetricDefinition(
            key: $key,
            label: $label,
            formulaPayload: [
                'identifier' => $key,
                'operation' => 'sum',
                'operands' => [$key . '.input'],
                'precision' => [
                    'scale' => $unit === null ? 0 : 2,
                    'rounding_mode' => 'half_up',
                ],
                'unit' => $unit,
            ],
            unit: $unit,
            progress: $progress,
        );
    }
}
