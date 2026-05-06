'use client';

import React from 'react';

import { MetricCardGrid } from '@/components/metrics/MetricCardGrid';
import { MetricScorecard } from '@/components/metrics/MetricScorecard';
import { WidgetSection } from '@/components/metrics/WidgetSection';
import type { WidgetPayload } from '@/types/metrics';

type DashboardWidgetRendererProps = {
  widget: WidgetPayload;
  className?: string;
};

export function DashboardWidgetRenderer({ widget, className = '' }: DashboardWidgetRendererProps) {
  if (widget.kind === 'scorecard' && widget.scorecard) {
    return (
      <section className={className}>
        <MetricScorecard scorecard={widget.scorecard} />
      </section>
    );
  }

  if (widget.kind !== 'metric_grid') {
    return null;
  }

  return (
    <WidgetSection title={widget.title} subtitle={widget.subtitle} className={className}>
      <MetricCardGrid cards={widget.cards ?? []} />
    </WidgetSection>
  );
}
