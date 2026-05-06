'use client';

import React from 'react';

import { MetricCardGrid } from '@/components/metrics/MetricCardGrid';
import type { ScorecardPayload } from '@/types/metrics';

type MetricScorecardProps = {
  scorecard: ScorecardPayload;
};

export function MetricScorecard({ scorecard }: MetricScorecardProps) {
  return (
    <div className="space-y-3">
      <div>
        <h3 className="text-sm font-semibold text-slate-800">{scorecard.title}</h3>
        {scorecard.subtitle ? <p className="text-xs text-slate-500">{scorecard.subtitle}</p> : null}
      </div>
      <MetricCardGrid cards={scorecard.metrics} />
      {scorecard.warnings && scorecard.warnings.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {scorecard.warnings.map((warning) => (
            <span key={warning} className="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">
              {warning}
            </span>
          ))}
        </div>
      ) : null}
    </div>
  );
}
