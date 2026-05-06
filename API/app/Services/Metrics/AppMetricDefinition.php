<?php

declare(strict_types=1);

namespace App\Services\Metrics;

final readonly class AppMetricDefinition
{
    /**
     * @param array<string, mixed> $formulaPayload
     * @param array<string, mixed>|null $progress
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $formulaPayload,
        public ?string $unit = null,
        public ?string $tone = null,
        public ?string $href = null,
        public ?array $progress = null,
    ) {}
}
