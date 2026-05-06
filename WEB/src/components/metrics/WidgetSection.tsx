'use client';

import React from 'react';

type WidgetSectionProps = {
  title: string;
  subtitle?: string;
  children: React.ReactNode;
  className?: string;
};

export function WidgetSection({ title, subtitle, children, className = '' }: WidgetSectionProps) {
  return (
    <section className={['space-y-3', className].join(' ')}>
      <div>
        <h3 className="text-sm font-semibold text-slate-800">{title}</h3>
        {subtitle ? <p className="text-xs text-slate-500">{subtitle}</p> : null}
      </div>
      {children}
    </section>
  );
}
