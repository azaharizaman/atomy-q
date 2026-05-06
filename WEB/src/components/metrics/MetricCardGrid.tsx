'use client';

import React from 'react';

import { MetricCard } from '@/components/metrics/MetricCard';
import type { MetricCardPayload } from '@/types/metrics';

type MetricCardGridProps = {
  cards: MetricCardPayload[];
};

export function MetricCardGrid({ cards }: MetricCardGridProps) {
  return (
    <div className="grid items-stretch gap-4 xl:grid-cols-4 md:grid-cols-2">
      {cards.map((card) => (
        <MetricCard key={card.key} card={card} />
      ))}
    </div>
  );
}
