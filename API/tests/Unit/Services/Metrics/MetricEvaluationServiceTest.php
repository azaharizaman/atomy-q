<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metrics;

use App\Services\Metrics\AppMetricDefinitionCatalog;
use App\Services\Metrics\MetricEvaluationService;
use Nexus\MetricEngine\Services\BatchFormulaEvaluatorService;
use Nexus\MetricEngine\Services\ComparisonService;
use Nexus\MetricEngine\Services\FormulaDefinitionSerializerService;
use Nexus\MetricEngine\Services\FormulaEvaluatorService;
use Nexus\MetricEngine\Services\FormulaGraphService;
use Nexus\MetricEngine\Services\MetricRunFingerprintService;
use Nexus\MetricEngine\Services\MetricStatusInferenceService;
use Nexus\MetricEngine\Services\NumericValueService;
use Nexus\MetricEngine\Services\PeriodComparatorService;
use Nexus\MetricEngine\Services\ScalarMetricCalculatorService;
use Nexus\MetricEngine\Services\TimeSeriesMetricCalculatorService;
use Nexus\MetricEngine\Services\WindowResolverService;
use Nexus\MetricEngine\ValueObjects\MetricInput;
use PHPUnit\Framework\TestCase;

final class MetricEvaluationServiceTest extends TestCase
{
    public function test_it_evaluates_dashboard_metrics_with_metric_engine_outcomes_and_fingerprint(): void
    {
        $service = $this->service();

        $result = $service->evaluate(
            metricKeys: [
                'procurement.active_rfqs',
                'procurement.pending_approvals',
                'procurement.total_savings',
            ],
            inputs: [
                'procurement.active_rfqs.input' => new MetricInput('procurement.active_rfqs.input', 3),
                'procurement.pending_approvals.input' => new MetricInput('procurement.pending_approvals.input', 1),
                'procurement.total_savings.input' => new MetricInput('procurement.total_savings.input', 5600.0, 'USD'),
            ],
            metadata: ['tenant_id' => 'tenant-1', 'surface' => 'dashboard'],
        );

        self::assertArrayHasKey('fingerprint', $result);
        self::assertIsString($result['fingerprint']);
        self::assertNotSame('', $result['fingerprint']);
        self::assertSame('available', $result['cards']['procurement.active_rfqs']->status);
        self::assertSame(3, $result['cards']['procurement.active_rfqs']->value);
        self::assertSame('3', $result['cards']['procurement.active_rfqs']->formattedValue);
        self::assertSame('USD 5,600.00', $result['cards']['procurement.total_savings']->formattedValue);
    }

    public function test_it_maps_missing_metric_engine_inputs_to_not_available_cards(): void
    {
        $service = $this->service();

        $result = $service->evaluate(
            metricKeys: ['procurement.pending_approvals'],
            inputs: [],
            metadata: ['tenant_id' => 'tenant-1', 'surface' => 'dashboard'],
        );

        $card = $result['cards']['procurement.pending_approvals'];

        self::assertSame('not_available', $card->status);
        self::assertNull($card->value);
        self::assertSame('--', $card->formattedValue);
        self::assertSame('missing_input', $card->reason);
    }

    private function service(): MetricEvaluationService
    {
        $numeric = new NumericValueService();
        $serializer = new FormulaDefinitionSerializerService();
        $graph = new FormulaGraphService();

        return new MetricEvaluationService(
            catalog: new AppMetricDefinitionCatalog($serializer),
            batchEvaluator: new BatchFormulaEvaluatorService(
                new FormulaEvaluatorService(
                    new ScalarMetricCalculatorService($numeric),
                    new TimeSeriesMetricCalculatorService(
                        $numeric,
                        new WindowResolverService(new PeriodComparatorService()),
                        new ComparisonService($numeric),
                    ),
                ),
                $graph,
                new MetricStatusInferenceService(),
            ),
            fingerprintService: new MetricRunFingerprintService($serializer, $graph),
        );
    }
}
