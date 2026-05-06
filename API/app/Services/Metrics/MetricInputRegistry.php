<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Rfq;
use App\Models\Vendor;
use Nexus\MetricEngine\ValueObjects\MetricInput;

final readonly class MetricInputRegistry
{
    public function __construct(
        private MetricInputProvider $provider,
    ) {}

    /**
     * @return array<string, MetricInput>
     */
    public function dashboard(string $tenantId): array
    {
        return $this->provider->dashboardInputs($tenantId);
    }

    /**
     * @return array<string, MetricInput>
     */
    public function rfqOverview(string $tenantId, Rfq $rfq): array
    {
        return $this->provider->rfqInputs($tenantId, $rfq);
    }

    /**
     * @return array{inputs: array<string, MetricInput>, warnings: list<string>}
     */
    public function vendorGovernance(string $tenantId, Vendor $vendor): array
    {
        return $this->provider->vendorInputs($tenantId, $vendor);
    }

    /**
     * @return array<string, MetricInput>
     */
    public function reporting(string $tenantId): array
    {
        return $this->provider->reportingInputs($tenantId);
    }
}
