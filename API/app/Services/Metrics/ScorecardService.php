<?php

declare(strict_types=1);

namespace App\Services\Metrics;

final readonly class ScorecardService
{
    /**
     * @param list<MetricCardData> $metrics
     * @param list<string> $warnings
     */
    public function make(string $key, string $title, array $metrics, array $warnings = [], ?string $subtitle = null): ScorecardData
    {
        return new ScorecardData(
            key: $key,
            title: $title,
            status: $this->status($metrics),
            metrics: $metrics,
            subtitle: $subtitle,
            warnings: $warnings,
        );
    }

    /**
     * @param list<MetricCardData> $metrics
     */
    private function status(array $metrics): string
    {
        foreach ($metrics as $metric) {
            if ($metric->status === 'error') {
                return 'error';
            }
        }

        foreach ($metrics as $metric) {
            if ($metric->status === 'available') {
                return 'available';
            }
        }

        return $metrics[0]->status ?? 'not_available';
    }
}
