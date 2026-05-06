<?php

declare(strict_types=1);

namespace App\Services\Metrics;

final readonly class ScorecardData
{
    /**
     * @param list<MetricCardData> $metrics
     * @param list<string> $warnings
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $status,
        public array $metrics,
        public ?string $subtitle = null,
        public array $warnings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'status' => $this->status,
            'metrics' => array_map(static fn (MetricCardData $metric): array => $metric->toArray(), $this->metrics),
            'warnings' => $this->warnings,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
