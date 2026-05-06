<?php

declare(strict_types=1);

namespace App\Adapters\InsightOperations;

use App\Models\Approval;
use App\Models\Award;
use App\Models\QuoteSubmission;
use App\Models\Rfq;
use App\Models\VendorFinding;
use App\Services\Metrics\MetricCardData;
use App\Services\Metrics\MetricEvaluationService;
use App\Services\Metrics\MetricInputRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Nexus\InsightOperations\Contracts\DashboardFactsPortInterface;
use Nexus\InsightOperations\DTOs\DashboardFactsDto;
use Nexus\InsightOperations\DTOs\MetricFactDto;

/**
 * Assembles tenant-scoped dashboard facts for deterministic insight summaries.
 *
 * The adapter keeps joins and counts inside the tenant boundary, marks metrics
 * unavailable when source columns are absent, and returns compact activity/risk
 * slices suitable for AI narrative prompts or plain dashboard display.
 */
final readonly class DashboardFactsAdapter implements
    DashboardFactsPortInterface
{
    public function __construct(
        private MetricInputRegistry $inputRegistry,
        private MetricEvaluationService $metricEvaluationService,
    ) {}

    public function factsForTenant(string $tenantId): DashboardFactsDto
    {
        $tenantId = trim($tenantId);
        $evaluatedMetrics = $this->metricEvaluationService->evaluate(
            metricKeys: [
                'procurement.active_rfqs',
                'procurement.pending_approvals',
                'procurement.quote_intake_count',
                'procurement.awards_in_flight',
                'procurement.total_savings',
            ],
            inputs: $this->inputRegistry->dashboard($tenantId),
            metadata: ['tenant_id' => $tenantId, 'surface' => 'dashboard.kpis'],
        );
        $riskAlertCount = VendorFinding::query()
            ->where("tenant_id", $tenantId)
            ->whereIn("severity", ["high", "critical"])
            ->whereNotIn("status", ["closed", "resolved"])
            ->count();

        return new DashboardFactsDto(
            metrics: [
                $this->toFact("active_rfqs", $evaluatedMetrics['cards']['procurement.active_rfqs']),
                $this->toFact("pending_approvals", $evaluatedMetrics['cards']['procurement.pending_approvals']),
                $this->toFact("quote_intake_count", $evaluatedMetrics['cards']['procurement.quote_intake_count']),
                $this->toFact("awards_in_flight", $evaluatedMetrics['cards']['procurement.awards_in_flight']),
                $this->toFact("total_savings", $evaluatedMetrics['cards']['procurement.total_savings']),
                $this->averageCycleTime($tenantId),
                new MetricFactDto("risk_alert_count", $riskAlertCount),
            ],
            recentActivity: $this->recentActivity($tenantId),
            riskAlerts: $this->riskAlerts($tenantId),
        );
    }

    private function tenantRfqQuery(
        string $tenantId,
    ): \Illuminate\Database\Eloquent\Builder {
        return Rfq::query()->where("tenant_id", $tenantId);
    }

    private function totalSavings(string $tenantId): float
    {
        $rfqs = $this->tenantRfqQuery($tenantId)->get([
            "estimated_value",
            "savings_percentage",
        ]);
        $total = 0.0;

        foreach ($rfqs as $rfq) {
            $total +=
                ((float) $rfq->estimated_value) *
                (((float) $rfq->savings_percentage) / 100);
        }

        return round($total, 2);
    }

    private function toFact(string $key, MetricCardData $card): MetricFactDto
    {
        return new MetricFactDto(
            $key,
            $card->value,
            $card->status,
            $card->reason,
        );
    }

    private function averageCycleTime(string $tenantId): MetricFactDto
    {
        if (!Schema::hasColumn("awards", "signoff_at")) {
            return new MetricFactDto(
                "avg_cycle_time_days",
                null,
                "not_available",
                "source_domain_not_implemented",
            );
        }

        $awards = Award::query()
            ->where("awards.tenant_id", $tenantId)
            ->join("rfqs", function ($join): void {
                $join
                    ->on("rfqs.id", "=", "awards.rfq_id")
                    ->whereColumn("rfqs.tenant_id", "awards.tenant_id");
            })
            ->whereNotNull("awards.signoff_at")
            ->get(["rfqs.created_at as rfq_created_at", "awards.signoff_at"]);

        if ($awards->isEmpty()) {
            return new MetricFactDto(
                "avg_cycle_time_days",
                null,
                "not_available",
                "no_data",
            );
        }

        $totalDays = 0;
        $processedCount = 0;
        foreach ($awards as $award) {
            $createdAt = $this->dateOrNull($award->rfq_created_at);
            $signedAt = $this->dateOrNull($award->signoff_at);
            if ($createdAt === null || $signedAt === null) {
                continue;
            }

            $totalDays += abs($createdAt->diffInDays($signedAt));
            $processedCount++;
        }

        if ($processedCount === 0) {
            return new MetricFactDto(
                "avg_cycle_time_days",
                null,
                "not_available",
                "data_not_available",
            );
        }

        return new MetricFactDto(
            "avg_cycle_time_days",
            round($totalDays / $processedCount, 2),
        );
    }

    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === "") {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(string $tenantId): array
    {
        return Rfq::query()
            ->where("tenant_id", $tenantId)
            ->latest("updated_at")
            ->orderBy("id", "desc")
            ->limit(5)
            ->get(["id", "rfq_number", "status", "updated_at"])
            ->map(
                static fn(Rfq $rfq): array => [
                    "type" => "rfq_status",
                    "rfq_id" => (string) $rfq->id,
                    "rfq_number" => (string) $rfq->rfq_number,
                    "status" => (string) $rfq->status,
                    "occurred_at" => $rfq->updated_at?->toAtomString(),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function riskAlerts(string $tenantId): array
    {
        return VendorFinding::query()
            ->where("tenant_id", $tenantId)
            ->whereIn("severity", ["high", "critical"])
            ->whereNotIn("status", ["closed", "resolved"])
            ->latest("created_at")
            ->orderBy("id", "desc")
            ->limit(5)
            ->get(["id", "vendor_id", "severity", "status"])
            ->map(
                static fn(VendorFinding $finding): array => [
                    "finding_id" => (string) $finding->id,
                    "vendor_id" => (string) $finding->vendor_id,
                    "severity" => (string) $finding->severity,
                    "status" => (string) $finding->status,
                ],
            )
            ->values()
            ->all();
    }
}
