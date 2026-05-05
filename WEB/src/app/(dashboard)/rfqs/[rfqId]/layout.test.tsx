import React, { Suspense } from 'react';
import { act } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen } from '@testing-library/react';
import { renderWithProviders } from '@/test/utils';

const mockUseRfq = vi.fn();
const mockUseFeatureFlags = vi.fn();
let mockPathname = '/rfqs/rfq-1/overview';

vi.mock('next/navigation', () => ({
  usePathname: () => mockPathname,
}));

vi.mock('@/components/layout/header', () => ({
  Header: () => <div data-testid="header" />,
}));

vi.mock('@/components/layout/app-footer', () => ({
  AppFooter: () => <div data-testid="footer" />,
}));

vi.mock('@/components/layout/main-sidebar-nav', () => ({
  MainSidebarNav: () => <div data-testid="nav" />,
}));

vi.mock('@/components/workspace/active-record-menu', () => ({
  ActiveRecordMenu: () => <div data-testid="active-record" />,
}));

vi.mock('@/hooks/use-rfq', () => ({
  useRfq: (...args: unknown[]) => mockUseRfq(...args),
}));

vi.mock('@/hooks/use-feature-flags', () => ({
  useFeatureFlags: (...args: unknown[]) => mockUseFeatureFlags(...args),
}));

vi.mock('@/lib/alpha-mode', () => ({
  isAlphaMode: () => false,
}));

import RfqWorkspaceLayout from './layout';

describe('RfqWorkspaceLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockPathname = '/rfqs/rfq-1/overview';
    mockUseFeatureFlags.mockReturnValue({
      data: { projects: true },
      isLoading: false,
    });
  });

  it('renders a plain-language error when the RFQ record cannot be loaded', async () => {
    mockUseRfq.mockReturnValue({
      data: undefined,
      isLoading: false,
      isError: true,
      error: new Error('Network unavailable'),
    });

    await act(async () => {
      renderWithProviders(
        <Suspense fallback={null}>
          <RfqWorkspaceLayout params={Promise.resolve({ rfqId: 'rfq-1' })}>
            <div>Child</div>
          </RfqWorkspaceLayout>
        </Suspense>,
      );
    });

    expect(screen.getAllByText('Unable to load this RFQ').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Could not load this RFQ').length).toBeGreaterThan(0);
    expect(screen.getAllByText(/we could not load this rfq right now/i).length).toBeGreaterThan(0);
    expect(screen.getByTestId('header')).toBeInTheDocument();
    expect(screen.getByTestId('footer')).toBeInTheDocument();
  });

  it('does not render an RFQ insights sidebar on overview', async () => {
    mockUseRfq.mockReturnValue({
      data: {
        id: 'rfq-1',
        displayIdentifier: 'RFQ-1',
        title: 'RFQ 1',
        status: 'active',
      },
      isLoading: false,
      isError: false,
      error: null,
    });

    await act(async () => {
      renderWithProviders(
        <Suspense fallback={null}>
          <RfqWorkspaceLayout params={Promise.resolve({ rfqId: 'rfq-1' })}>
            <div>Child</div>
          </RfqWorkspaceLayout>
        </Suspense>,
      );
    });

    expect(screen.queryByTestId('insights-sidebar')).not.toBeInTheDocument();
  });

  it('does not render an RFQ insights sidebar outside overview either', async () => {
    mockPathname = '/rfqs/rfq-1/quote-intake/quote-1/normalize';
    mockUseRfq.mockReturnValue({
      data: {
        id: 'rfq-1',
        displayIdentifier: 'RFQ-1',
        title: 'RFQ 1',
        status: 'active',
      },
      isLoading: false,
      isError: false,
      error: null,
    });

    await act(async () => {
      renderWithProviders(
        <Suspense fallback={null}>
          <RfqWorkspaceLayout params={Promise.resolve({ rfqId: 'rfq-1' })}>
            <div>Child</div>
          </RfqWorkspaceLayout>
        </Suspense>,
      );
    });

    expect(screen.queryByTestId('insights-sidebar')).not.toBeInTheDocument();
  });
});
