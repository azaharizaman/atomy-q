'use client';

import React from 'react';

import { KPIScorecard } from '@/components/ds/KPIScorecard';
import type { MetricCardPayload } from '@/types/metrics';

type MetricCardProps = {
  card: MetricCardPayload;
  className?: string;
};

export function MetricCard({ card, className }: MetricCardProps) {
  const isAvailable = card.status === 'available';
  const subtitle = isAvailable ? undefined : card.reason;
  const trend = card.trend
    ? {
        direction: card.trend.direction === 'flat' ? 'neutral' as const : card.trend.direction,
        label: card.trend.label,
      }
    : undefined;
  const progress = card.progress
    ? {
        value: card.progress.value,
        type: card.progress.type === 'ring' ? 'circular' as const : 'bar' as const,
      }
    : undefined;

  return (
    <KPIScorecard
      title={card.label}
      value={<span>{isAvailable ? card.formattedValue : card.formattedValue || '--'}</span>}
      subtitle={subtitle}
      trend={trend}
      progress={progress}
      className={className}
    />
  );
}
