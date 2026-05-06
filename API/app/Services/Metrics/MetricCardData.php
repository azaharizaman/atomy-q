<?php

declare(strict_types=1);

namespace App\Services\Metrics;

final readonly class MetricCardData
{
    public function __construct(
        public string $key,
        public string $label,
        public int|float|string|null $value,
        public string $formattedValue,
        public string $status,
        public ?string $reason = null,
        public ?string $unit = null,
        public ?string $tone = null,
        public ?string $href = null,
        public ?array $progress = null,
        public ?array $trend = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'formattedValue' => $this->formattedValue,
            'unit' => $this->unit,
            'status' => $this->status,
            'reason' => $this->reason,
            'tone' => $this->tone,
            'href' => $this->href,
            'progress' => $this->progress,
            'trend' => $this->trend,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
