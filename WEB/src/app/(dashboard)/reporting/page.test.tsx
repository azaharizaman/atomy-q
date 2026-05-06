import React from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';

import { renderWithProviders } from '@/test/utils';

let aiStatusData = {
  shouldHideAiControls: () => false,
  shouldShowUnavailableMessage: () => false,
  messageKeyForFeature: () => null,
  isFeatureAvailable: () => true,
};

const mockUseReportingAiSummary = vi.fn();
const mockFetchLiveOrFail = vi.fn();

vi.mock('@/hooks/use-ai-status', () => ({
  useAiStatus: () => aiStatusData,
}));

vi.mock('@/lib/api-live', () => ({
  fetchLiveOrFail: (...args: unknown[]) => mockFetchLiveOrFail(...args),
}));

vi.mock('@/hooks/use-reporting-ai-summary', () => ({
  useReportingAiSummary: (...args: unknown[]) => mockUseReportingAiSummary(...args),
}));

import ReportingPage from './page';

describe('ReportingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchLiveOrFail.mockResolvedValue({ data: { widgets: [] } });
    aiStatusData = {
      shouldHideAiControls: () => false,
      shouldShowUnavailableMessage: () => false,
      messageKeyForFeature: () => null,
      isFeatureAvailable: () => true,
    };
  });

  it('renders a truthful AI summary surface when available', () => {
    mockUseReportingAiSummary.mockReturnValue({
      summary: {
        featureKey: 'insight_intelligence',
        available: true,
        headline: 'Reporting trends are stable.',
        summary: 'The deterministic reporting surface remains usable.',
        bullets: ['Spend trend views are available.', 'Export remains disabled until the API is connected.'],
        provenance: { provider: 'openrouter' },
        raw: null,
      },
      isLoading: false,
      isError: false,
      error: null,
      isHidden: false,
      shouldShowUnavailableMessage: false,
      messageKey: null,
    });

    renderWithProviders(<ReportingPage />);

    expect(screen.getByRole('heading', { name: 'Reporting' })).toBeInTheDocument();
    expect(screen.getByText('Reporting trends are stable.')).toBeInTheDocument();
    expect(screen.getByText('Spend trend views are available.')).toBeInTheDocument();
  });

  it('hides the narrative panel when AI is unavailable', () => {
    aiStatusData = {
      shouldHideAiControls: () => true,
      shouldShowUnavailableMessage: () => false,
      messageKeyForFeature: () => null,
      isFeatureAvailable: () => false,
    };
    mockUseReportingAiSummary.mockReturnValue({
      summary: null,
      isLoading: false,
      isError: false,
      error: null,
      isHidden: true,
      shouldShowUnavailableMessage: false,
      messageKey: null,
    });

    renderWithProviders(<ReportingPage />);

    expect(screen.getByRole('heading', { name: 'Reporting' })).toBeInTheDocument();
    expect(screen.queryByText('Reporting trends are stable.')).not.toBeInTheDocument();
  });

  it('renders reporting widgets from the backend widget payload when available', async () => {
    mockUseReportingAiSummary.mockReturnValue({
      summary: null,
      isLoading: false,
      isError: false,
      error: null,
      isHidden: false,
      shouldShowUnavailableMessage: false,
      messageKey: null,
    });
    mockFetchLiveOrFail.mockResolvedValue({
      data: {
        widgets: [
          {
            key: 'reporting.kpi_summary_widget',
            title: 'KPI Summary',
            kind: 'metric_grid',
            status: 'available',
            cards: [
              {
                key: 'reporting.total_spend',
                label: 'Total Spend',
                value: 9000,
                formattedValue: 'USD 9,000.00',
                status: 'available',
              },
            ],
          },
        ],
      },
      meta: { fingerprint: 'reporting-widget-fingerprint' },
    });

    renderWithProviders(<ReportingPage />);

    expect(await screen.findByText('KPI Summary')).toBeInTheDocument();
    expect(screen.getByText('USD 9,000.00')).toBeInTheDocument();
  });
});
