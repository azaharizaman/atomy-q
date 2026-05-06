<?php

declare(strict_types=1);

namespace App\Services\Metrics;

final readonly class WidgetData
{
    /**
     * @param list<MetricCardData> $cards
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $series
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $kind,
        public string $status,
        public ?string $subtitle = null,
        public array $cards = [],
        public ?ScorecardData $scorecard = null,
        public array $rows = [],
        public array $series = [],
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
            'kind' => $this->kind,
            'status' => $this->status,
            'cards' => array_map(static fn (MetricCardData $card): array => $card->toArray(), $this->cards),
            'scorecard' => $this->scorecard?->toArray(),
            'rows' => $this->rows,
            'series' => $this->series,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
