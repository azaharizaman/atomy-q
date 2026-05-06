<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Approval;
use App\Models\Award;
use App\Models\ComparisonRun;
use App\Models\QuoteSubmission;
use App\Models\Rfq;
use App\Models\Vendor;
use App\Models\VendorEvidence;
use App\Models\VendorFinding;
use App\Services\VendorGovernanceScoreService;
use Illuminate\Support\Carbon;
use Nexus\MetricEngine\ValueObjects\MetricInput;

final readonly class MetricInputProvider
{
    public function __construct(
        private VendorGovernanceScoreService $vendorGovernanceScoreService,
    ) {}

    /**
     * @return array<string, MetricInput>
     */
    public function dashboardInputs(string $tenantId): array
    {
        $activeRfqs = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->count();
        $pendingApprovals = Approval::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();
        $quoteIntakeCount = QuoteSubmission::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['uploaded', 'extracting', 'normalizing', 'needs_review'])
            ->count();
        $awardsInFlight = Award::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'protested'])
            ->count();
        $awardedSpend = (float) Award::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'signed_off')
            ->sum('amount');
        $estimatedValue = (float) Rfq::query()
            ->where('tenant_id', $tenantId)
            ->sum('estimated_value');
        $totalSavings = $this->totalSavings($tenantId);
        $savingsPercentage = $estimatedValue > 0 ? round(($totalSavings / $estimatedValue) * 100, 2) : 0;
        $riskFindings = VendorFinding::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('severity', ['high', 'critical'])
            ->whereIn('status', ['open', 'in_progress']);
        $expiredComplianceEvidence = VendorEvidence::query()
            ->where('tenant_id', $tenantId)
            ->where('domain', 'compliance')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now('UTC'))
            ->count();

        return [
            'procurement.active_rfqs.input' => new MetricInput('procurement.active_rfqs.input', $activeRfqs),
            'procurement.pending_approvals.input' => new MetricInput('procurement.pending_approvals.input', $pendingApprovals),
            'procurement.quote_intake_count.input' => new MetricInput('procurement.quote_intake_count.input', $quoteIntakeCount),
            'procurement.awards_in_flight.input' => new MetricInput('procurement.awards_in_flight.input', $awardsInFlight),
            'procurement.total_savings.input' => new MetricInput('procurement.total_savings.input', $totalSavings, 'USD'),
            'procurement.savings_percentage.input' => new MetricInput('procurement.savings_percentage.input', $savingsPercentage, 'percent'),
            'procurement.awarded_spend.input' => new MetricInput('procurement.awarded_spend.input', round($awardedSpend, 2), 'USD'),
            'procurement.estimated_vs_awarded_delta.input' => new MetricInput('procurement.estimated_vs_awarded_delta.input', round($estimatedValue - $awardedSpend, 2), 'USD'),
            'vendor.open_high_critical_findings.input' => new MetricInput('vendor.open_high_critical_findings.input', (clone $riskFindings)->count()),
            'vendor.overdue_remediation_count.input' => new MetricInput('vendor.overdue_remediation_count.input', (clone $riskFindings)->whereNotNull('remediation_due_at')->where('remediation_due_at', '<', Carbon::now('UTC'))->count()),
            'vendor.expired_compliance_evidence_count.input' => new MetricInput('vendor.expired_compliance_evidence_count.input', $expiredComplianceEvidence),
            'vendor.approved_vendors.input' => new MetricInput('vendor.approved_vendors.input', Vendor::query()->where('tenant_id', $tenantId)->where('status', 'approved')->count()),
            'vendor.pending_review_vendors.input' => new MetricInput('vendor.pending_review_vendors.input', Vendor::query()->where('tenant_id', $tenantId)->whereIn('status', ['draft', 'pending_review', 'under_review'])->count()),
            'vendor.vendors_with_severe_findings.input' => new MetricInput('vendor.vendors_with_severe_findings.input', VendorFinding::query()->where('tenant_id', $tenantId)->whereIn('severity', ['high', 'critical'])->whereIn('status', ['open', 'in_progress'])->distinct('vendor_id')->count('vendor_id')),
        ];
    }

    /**
     * @return array<string, MetricInput>
     */
    public function rfqInputs(string $tenantId, Rfq $rfq): array
    {
        $quotesTotal = QuoteSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->count();
        $accepted = QuoteSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->whereIn('status', ['accepted', 'ready'])
            ->count();
        $needsReview = QuoteSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->where('status', 'needs_review')
            ->count();
        $expectedQuotes = (int) ($rfq->vendors_count ?? $rfq->vendorInvitations()->count());
        $receiptPct = $expectedQuotes > 0 ? round(($quotesTotal / $expectedQuotes) * 100, 2) : 0;
        $normalizationPct = $quotesTotal > 0 ? round(($accepted / $quotesTotal) * 100, 2) : 0;
        $comparisonRuns = ComparisonRun::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->count();
        $pendingApprovals = Approval::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->where('status', 'pending')
            ->count();
        $approvedApprovals = Approval::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->where('status', 'approved')
            ->count();
        $rejectedApprovals = Approval::query()
            ->where('tenant_id', $tenantId)
            ->where('rfq_id', $rfq->id)
            ->where('status', 'rejected')
            ->count();

        return [
            'rfq.quotes_received.input' => new MetricInput('rfq.quotes_received.input', $quotesTotal),
            'rfq.expected_quotes.input' => new MetricInput('rfq.expected_quotes.input', $expectedQuotes),
            'rfq.quote_receipt_pct.input' => new MetricInput('rfq.quote_receipt_pct.input', $receiptPct, 'percent'),
            'rfq.normalization_progress_pct.input' => new MetricInput('rfq.normalization_progress_pct.input', $normalizationPct, 'percent'),
            'rfq.accepted_quotes.input' => new MetricInput('rfq.accepted_quotes.input', $accepted),
            'rfq.needs_review_quote_lines.input' => new MetricInput('rfq.needs_review_quote_lines.input', $needsReview),
            'rfq.comparison_runs_count.input' => new MetricInput('rfq.comparison_runs_count.input', $comparisonRuns),
            'rfq.latest_comparison_status.input' => new MetricInput('rfq.latest_comparison_status.input', $comparisonRuns),
            'rfq.pending_approvals.input' => new MetricInput('rfq.pending_approvals.input', $pendingApprovals),
            'rfq.approved_approvals.input' => new MetricInput('rfq.approved_approvals.input', $approvedApprovals),
            'rfq.rejected_approvals.input' => new MetricInput('rfq.rejected_approvals.input', $rejectedApprovals),
        ];
    }

    /**
     * @return array<string, MetricInput>
     */
    public function reportingInputs(string $tenantId): array
    {
        $totalSpend = Award::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'signed_off')
            ->sum('amount');
        $activeRfqs = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->count();

        return [
            'reporting.total_spend.input' => new MetricInput('reporting.total_spend.input', round((float) $totalSpend, 2), 'USD'),
            'reporting.active_rfqs.input' => new MetricInput('reporting.active_rfqs.input', $activeRfqs),
            'reporting.total_savings.input' => new MetricInput('reporting.total_savings.input', $this->totalSavings($tenantId), 'USD'),
            'procurement.awarded_spend.input' => new MetricInput('procurement.awarded_spend.input', round((float) $totalSpend, 2), 'USD'),
        ];
    }

    /**
     * @return array{inputs: array<string, MetricInput>, warnings: list<string>}
     */
    public function vendorInputs(string $tenantId, Vendor $vendor): array
    {
        $evidence = VendorEvidence::query()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', (string) $vendor->id)
            ->get();
        $findings = VendorFinding::query()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', (string) $vendor->id)
            ->get();
        $summary = $this->vendorGovernanceScoreService->summarize($evidence, $findings);
        $now = Carbon::now('UTC');

        $acceptedEvidence = $evidence
            ->filter(static fn (VendorEvidence $record): bool => in_array((string) $record->review_status, ['reviewed', 'accepted', 'approved'], true))
            ->count();
        $expiredEvidence = $evidence
            ->filter(static fn (VendorEvidence $record): bool => $record->expires_at !== null && $record->expires_at->isBefore($now))
            ->count();
        $staleEvidence = $evidence
            ->filter(static fn (VendorEvidence $record): bool => $record->observed_at === null || $record->observed_at->diffInDays($now) > 365)
            ->count();
        $hasFreshSanctions = $evidence
            ->filter(static fn (VendorEvidence $record): bool => (string) $record->type === 'sanctions_screening'
                && $record->observed_at !== null
                && $record->observed_at->diffInDays($now) <= 180)
            ->isNotEmpty();
        $openFindings = $findings
            ->filter(static fn (VendorFinding $record): bool => in_array((string) $record->status, ['open', 'in_progress'], true));
        $severeFindings = $openFindings
            ->filter(static fn (VendorFinding $record): bool => in_array((string) $record->severity, ['critical', 'high'], true))
            ->count();
        $overdueRemediation = $openFindings
            ->filter(static fn (VendorFinding $record): bool => $record->remediation_due_at !== null && $record->remediation_due_at->isBefore($now))
            ->count();
        $weightedSeverity = $openFindings->sum(static fn (VendorFinding $record): int => match ((string) $record->severity) {
            'critical' => 60,
            'high' => 45,
            'medium' => 20,
            'low' => 10,
            default => 15,
        });

        return [
            'inputs' => [
                'vendor.esg_score.input' => new MetricInput('vendor.esg_score.input', $summary['scores']['esg_score']),
                'vendor.compliance_health_score.input' => new MetricInput('vendor.compliance_health_score.input', $summary['scores']['compliance_health_score']),
                'vendor.risk_watch_score.input' => new MetricInput('vendor.risk_watch_score.input', $summary['scores']['risk_watch_score']),
                'vendor.evidence_freshness_score.input' => new MetricInput('vendor.evidence_freshness_score.input', $summary['scores']['evidence_freshness_score']),
                'vendor.accepted_evidence_count.input' => new MetricInput('vendor.accepted_evidence_count.input', $acceptedEvidence),
                'vendor.expired_evidence_count.input' => new MetricInput('vendor.expired_evidence_count.input', $expiredEvidence),
                'vendor.stale_evidence_count.input' => new MetricInput('vendor.stale_evidence_count.input', $staleEvidence),
                'vendor.missing_sanctions_check.input' => new MetricInput('vendor.missing_sanctions_check.input', $hasFreshSanctions ? 0 : 1),
                'vendor.open_findings.input' => new MetricInput('vendor.open_findings.input', $openFindings->count()),
                'vendor.open_severe_findings.input' => new MetricInput('vendor.open_severe_findings.input', $severeFindings),
                'vendor.overdue_remediation_count.input' => new MetricInput('vendor.overdue_remediation_count.input', $overdueRemediation),
                'vendor.finding_severity_weighted_score.input' => new MetricInput('vendor.finding_severity_weighted_score.input', $weightedSeverity),
            ],
            'warnings' => $summary['warning_flags'],
        ];
    }

    private function totalSavings(string $tenantId): float
    {
        $total = 0.0;

        foreach (Rfq::query()->where('tenant_id', $tenantId)->get(['estimated_value', 'savings_percentage']) as $rfq) {
            $total += ((float) $rfq->estimated_value) * (((float) $rfq->savings_percentage) / 100);
        }

        return round($total, 2);
    }
}
