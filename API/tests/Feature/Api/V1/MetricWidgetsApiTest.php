<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Approval;
use App\Models\Award;
use App\Models\ComparisonRun;
use App\Models\QuoteSubmission;
use App\Models\Rfq;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEvidence;
use App\Models\VendorFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

final class MetricWidgetsApiTest extends ApiTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_widgets_return_metric_engine_payload_without_breaking_kpis(): void
    {
        $tenantId = (string) Str::ulid();
        $user = User::factory()->admin()->create(['tenant_id' => $tenantId]);
        $rfq = Rfq::factory()->published()->ownedBy($user)->create([
            'estimated_value' => 10000,
            'savings_percentage' => 10,
        ]);

        Approval::factory()->pending()->forRfq($rfq)->create(['tenant_id' => $tenantId]);
        QuoteSubmission::factory()->extracting()->forRfq($rfq)->create(['tenant_id' => $tenantId]);
        Award::factory()->pending()->forRfq($rfq)->create(['tenant_id' => $tenantId]);

        $headers = $this->authHeaders($tenantId, (string) $user->id);

        $widgets = $this->getJson('/api/v1/dashboard/widgets', $headers);
        $widgets->assertOk();
        $widgets->assertJsonPath('data.widgets.0.key', 'dashboard.procurement_pipeline_widget');
        $widgets->assertJsonPath('data.widgets.0.cards.0.key', 'procurement.active_rfqs');
        $widgets->assertJsonPath('data.widgets.0.cards.0.status', 'available');
        $widgets->assertJsonPath('data.widgets.0.cards.0.formattedValue', '1');
        $this->assertIsString($widgets->json('meta.fingerprint'));
        $this->assertNotSame('', $widgets->json('meta.fingerprint'));

        $legacy = $this->getJson('/api/v1/dashboard/kpis', $headers);
        $legacy->assertOk();
        $legacy->assertJsonPath('data.active_rfqs', 1);
        $legacy->assertJsonPath('data.pending_approvals', 1);
    }

    public function test_rfq_overview_includes_widget_payload_alongside_existing_aliases(): void
    {
        $tenantId = (string) Str::ulid();
        $user = User::factory()->admin()->create(['tenant_id' => $tenantId]);
        $rfq = Rfq::factory()->published()->ownedBy($user)->create([
            'project_id' => null,
        ]);

        QuoteSubmission::factory()->ready()->forRfq($rfq)->create(['tenant_id' => $tenantId]);
        QuoteSubmission::factory()->needsReview()->forRfq($rfq)->create(['tenant_id' => $tenantId]);
        ComparisonRun::factory()->preview()->forRfq($rfq)->create(['tenant_id' => $tenantId]);
        Approval::factory()->pending()->forRfq($rfq)->create(['tenant_id' => $tenantId]);

        $response = $this->getJson(
            '/api/v1/rfqs/' . $rfq->id . '/overview',
            $this->authHeaders($tenantId, (string) $user->id),
        );

        $response->assertOk();
        $response->assertJsonPath('data.expected_quotes', 0);
        $response->assertJsonPath('data.normalization.total_quotes', 2);
        $response->assertJsonPath('data.widgets.0.key', 'rfq.overview_progress_widget');
        $response->assertJsonPath('data.widgets.0.cards.0.key', 'rfq.quotes_received');
        $response->assertJsonPath('data.widgets.0.cards.0.status', 'available');
        $response->assertJsonPath('data.widgets.0.cards.0.formattedValue', '2');
        $this->assertIsString($response->json('data.widget_meta.fingerprint'));
        $this->assertNotSame('', $response->json('data.widget_meta.fingerprint'));
    }

    public function test_reporting_widgets_return_metric_engine_payload(): void
    {
        $tenantId = (string) Str::ulid();
        $user = User::factory()->admin()->create(['tenant_id' => $tenantId]);
        $rfq = Rfq::factory()->published()->ownedBy($user)->create([
            'estimated_value' => 12000,
            'savings_percentage' => 10,
        ]);

        Award::factory()->signedOff()->forRfq($rfq)->create([
            'tenant_id' => $tenantId,
            'amount' => 9000,
        ]);

        $response = $this->getJson('/api/v1/reports/widgets', $this->authHeaders($tenantId, (string) $user->id));

        $response->assertOk();
        $response->assertJsonPath('data.widgets.0.key', 'reporting.kpi_summary_widget');
        $response->assertJsonPath('data.widgets.0.cards.0.key', 'reporting.total_spend');
        $response->assertJsonPath('data.widgets.0.cards.0.status', 'available');
        $response->assertJsonPath('data.widgets.0.cards.0.formattedValue', 'USD 9,000.00');
        $this->assertIsString($response->json('meta.fingerprint'));
        $this->assertNotSame('', $response->json('meta.fingerprint'));
    }

    public function test_vendor_widgets_return_metric_engine_scorecard_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22T00:00:00Z'));
        $tenantId = (string) Str::ulid();
        $user = User::factory()->admin()->create(['tenant_id' => $tenantId]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenantId,
            'legal_name' => 'Northwind Holdings Sdn Bhd',
            'display_name' => 'Northwind',
            'registration_number' => '202601234567',
            'tax_id' => 'TAX-202601234567',
            'country_of_registration' => 'MY',
            'primary_contact_name' => 'Nadia Rahman',
            'primary_contact_email' => 'contact@northwind.test',
            'primary_contact_phone' => '+60123456789',
            'status' => 'approved',
        ]);

        VendorEvidence::query()->create([
            'tenant_id' => $tenantId,
            'vendor_id' => (string) $vendor->id,
            'domain' => 'compliance',
            'type' => 'iso_certificate',
            'title' => 'ISO 14001 certificate',
            'source' => 'manual',
            'observed_at' => '2024-01-01T00:00:00Z',
            'expires_at' => '2026-01-01T00:00:00Z',
            'review_status' => 'pending',
            'reviewed_by' => null,
            'notes' => null,
        ]);
        VendorFinding::query()->create([
            'tenant_id' => $tenantId,
            'vendor_id' => (string) $vendor->id,
            'domain' => 'risk',
            'issue_type' => 'financial_watch',
            'severity' => 'high',
            'status' => 'open',
            'opened_at' => '2026-03-01T00:00:00Z',
            'opened_by' => 'risk-user',
            'remediation_owner' => 'compliance-user',
            'remediation_due_at' => '2026-04-01T00:00:00Z',
            'resolution_summary' => null,
        ]);

        $response = $this->getJson(
            '/api/v1/vendors/' . $vendor->id . '/widgets',
            $this->authHeaders($tenantId, (string) $user->id),
        );

        $response->assertOk();
        $response->assertJsonPath('data.widgets.0.key', 'vendor.governance_scorecard_widget');
        $response->assertJsonPath('data.widgets.0.kind', 'scorecard');
        $response->assertJsonPath('data.widgets.0.scorecard.key', 'vendor.governance_scorecard');
        $response->assertJsonPath('data.widgets.0.scorecard.metrics.0.key', 'vendor.esg_score');
        $response->assertJsonPath('data.widgets.0.scorecard.metrics.0.status', 'available');
        $response->assertJsonPath('data.widgets.0.scorecard.metrics.0.formattedValue', '55');
        $this->assertContains('open_severe_risk_finding', $response->json('data.widgets.0.scorecard.warnings'));
        $this->assertIsString($response->json('meta.fingerprint'));
        $this->assertNotSame('', $response->json('meta.fingerprint'));
    }
}
