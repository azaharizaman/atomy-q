<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Nexus\MetricEngine\Enums\MetricResultStatus;
use Nexus\MetricEngine\Services\BatchFormulaEvaluatorService;
use Nexus\MetricEngine\Services\MetricRunFingerprintService;
use Nexus\MetricEngine\ValueObjects\MetricInput;
use Nexus\MetricEngine\ValueObjects\MetricSeries;

final readonly class MetricEvaluationService
{
    public function __construct(
        private AppMetricDefinitionCatalog $catalog,
        private BatchFormulaEvaluatorService $batchEvaluator,
        private MetricRunFingerprintService $fingerprintService,
    ) {}

    /**
     * @param list<string> $metricKeys
     * @param array<string, MetricInput|MetricSeries> $inputs
     * @param array<string, mixed> $metadata
     * @return array{cards: array<string, MetricCardData>, fingerprint: string}
     */
    public function evaluate(array $metricKeys, array $inputs, array $metadata = []): array
    {
        $formulaCatalog = $this->catalog->formulaCatalogFor($metricKeys);
        $batch = $this->batchEvaluator->evaluate($formulaCatalog, $inputs);
        $fingerprint = $this->fingerprintService->fingerprint($formulaCatalog, $inputs, $metadata);

        $cards = [];
        foreach ($metricKeys as $metricKey) {
            $definition = $this->catalog->get($metricKey);
            $outcome = $batch->get($metricKey);
            $value = $outcome->result?->value();
            $normalizedValue = $this->normalizeCardValue(is_array($value) ? null : $value, $definition->unit);
            $unit = $outcome->result?->unit() ?? $definition->unit;

            $cards[$metricKey] = new MetricCardData(
                key: $metricKey,
                label: $definition->label,
                value: $normalizedValue,
                formattedValue: $outcome->status === MetricResultStatus::AVAILABLE
                    ? $this->formatValue($normalizedValue, $unit)
                    : '--',
                status: $outcome->status->value,
                reason: $this->normalizeReason($outcome->reasonCode),
                unit: $unit,
                tone: $definition->tone,
                href: $definition->href,
                progress: $this->progressPayload($definition, $value, $outcome->status),
            );
        }

        return [
            'cards' => $cards,
            'fingerprint' => $fingerprint->hash,
        ];
    }

    private function formatValue(int|float|string|null $value, ?string $unit): string
    {
        if ($value === null) {
            return '--';
        }

        if ($unit === 'USD') {
            return 'USD ' . number_format((float) $value, 2);
        }

        if ($unit === 'percent') {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%';
        }

        if (is_int($value) || (is_float($value) && floor($value) === $value)) {
            return (string) (int) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2), '0'), '.');
        }

        return (string) $value;
    }

    private function normalizeCardValue(int|float|string|null $value, ?string $unit): int|float|string|null
    {
        if (is_float($value) && $unit === null && floor($value) === $value) {
            return (int) $value;
        }

        return $value;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return str_starts_with($reason, 'metric_engine.')
            ? substr($reason, strlen('metric_engine.'))
            : $reason;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function progressPayload(AppMetricDefinition $definition, mixed $value, MetricResultStatus $status): ?array
    {
        if ($definition->progress === null || $status !== MetricResultStatus::AVAILABLE || ! is_numeric($value)) {
            return null;
        }

        return [
            'value' => max(0, min(100, (float) $value)),
            'type' => $definition->progress['type'] ?? 'bar',
        ];
    }
}
