export type MetricStatus = 'available' | 'not_available' | 'no_data' | 'error';

export type MetricCardPayload = {
  key: string;
  label: string;
  value: number | string | null;
  formattedValue: string;
  unit?: string;
  status: MetricStatus;
  reason?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
  href?: string;
  progress?: {
    value: number;
    max?: number;
    type: 'bar' | 'ring';
  };
  trend?: {
    direction: 'up' | 'down' | 'flat';
    label: string;
    value?: number;
  };
};

export type ScorecardPayload = {
  key: string;
  title: string;
  subtitle?: string;
  status: MetricStatus;
  metrics: MetricCardPayload[];
  warnings?: string[];
};

export type WidgetPayload = {
  key: string;
  title: string;
  subtitle?: string;
  kind: 'metric_grid' | 'scorecard' | 'trend' | 'breakdown' | 'activity' | 'risk_list';
  status: MetricStatus;
  cards?: MetricCardPayload[];
  scorecard?: ScorecardPayload;
  rows?: Record<string, unknown>[];
  series?: Record<string, unknown>[];
};
