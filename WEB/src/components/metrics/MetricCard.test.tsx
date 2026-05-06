import React from 'react';
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MetricCard } from './MetricCard';
import { DashboardWidgetRenderer } from './DashboardWidgetRenderer';
import type { WidgetPayload } from '@/types/metrics';

describe('MetricCard', () => {
  it('renders available metric payloads with backend formatted values', () => {
    render(
      <MetricCard
        card={{
          key: 'procurement.active_rfqs',
          label: 'Active RFQs',
          value: 3,
          formattedValue: '3',
          status: 'available',
        }}
      />,
    );

    expect(screen.getByText('Active RFQs')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('renders unavailable metric payloads with clear reason text', () => {
    render(
      <MetricCard
        card={{
          key: 'procurement.pending_approvals',
          label: 'Pending Approvals',
          value: null,
          formattedValue: '--',
          status: 'no_data',
          reason: 'no_data',
        }}
      />,
    );

    expect(screen.getByText('Pending Approvals')).toBeInTheDocument();
    expect(screen.getByText('--')).toBeInTheDocument();
    expect(screen.getByText('no_data')).toBeInTheDocument();
  });
});

describe('DashboardWidgetRenderer', () => {
  it('renders each card in a metric-grid widget', () => {
    const widget: WidgetPayload = {
      key: 'dashboard.procurement_pipeline_widget',
      title: 'Procurement Pipeline',
      kind: 'metric_grid',
      status: 'available',
      cards: [
        {
          key: 'procurement.active_rfqs',
          label: 'Active RFQs',
          value: 3,
          formattedValue: '3',
          status: 'available',
        },
        {
          key: 'procurement.pending_approvals',
          label: 'Pending Approvals',
          value: 1,
          formattedValue: '1',
          status: 'available',
        },
      ],
    };

    render(<DashboardWidgetRenderer widget={widget} />);

    expect(screen.getByText('Procurement Pipeline')).toBeInTheDocument();
    expect(screen.getByText('Active RFQs')).toBeInTheDocument();
    expect(screen.getByText('Pending Approvals')).toBeInTheDocument();
  });

  it('renders metric scorecard widgets with warnings', () => {
    const widget: WidgetPayload = {
      key: 'vendor.governance_scorecard_widget',
      title: 'Governance Scorecard',
      kind: 'scorecard',
      status: 'available',
      scorecard: {
        key: 'vendor.governance_scorecard',
        title: 'Governance Scorecard',
        status: 'available',
        metrics: [
          {
            key: 'vendor.esg_score',
            label: 'ESG',
            value: 55,
            formattedValue: '55',
            status: 'available',
          },
        ],
        warnings: ['open_severe_risk_finding'],
      },
    };

    render(<DashboardWidgetRenderer widget={widget} />);

    expect(screen.getByText('Governance Scorecard')).toBeInTheDocument();
    expect(screen.getByText('ESG')).toBeInTheDocument();
    expect(screen.getByText('55')).toBeInTheDocument();
    expect(screen.getByText('open_severe_risk_finding')).toBeInTheDocument();
  });
});
