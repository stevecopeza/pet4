import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import Advisory from '../components/Advisory';

describe('Advisory demo signal visibility', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/pet/v1',
      nonce: 'test-nonce',
    };
    vi.restoreAllMocks();
  });

  it('defaults to Signals tab and renders a legible insight from recent signals', async () => {
    vi.spyOn(globalThis, 'fetch' as any).mockImplementation(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/advisory/signals/recent')) {
        return {
          ok: true,
          json: async () => ([
            {
              id: 'sig-1',
              signal_type: 'support_pressure',
              severity: 'critical',
              status: 'ACTIVE',
              title: 'RPM Resources has active escalation pressure',
              summary: '1 open escalation currently impacting service confidence.',
              message: 'Open escalations detected.',
              source_entity_type: 'customer',
              source_entity_id: '1',
              customer_id: 1,
              created_at: '2026-03-19 20:00:00',
            },
          ]),
        } as any;
      }
      if (url.includes('/customers')) {
        return { ok: true, json: async () => [] } as any;
      }
      return { ok: true, json: async () => [] } as any;
    });

    render(<Advisory />);

    const signalsTab = screen.getByRole('button', { name: 'Signals' });
    expect(signalsTab.className).toContain('active');

    await waitFor(() => {
      expect(screen.getByText('RPM Resources has active escalation pressure')).toBeInTheDocument();
    });
  });

  it('renders deterministic escalation and delivery risk signal categories on signals landing', async () => {
    vi.spyOn(globalThis, 'fetch' as any).mockImplementation(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/advisory/signals/recent')) {
        return {
          ok: true,
          json: async () => ([
            {
              id: 'sig-1',
              signal_type: 'support_pressure',
              severity: 'critical',
              status: 'ACTIVE',
              title: 'RPM Resources (Pty) Ltd has active escalation pressure',
              summary: '1 open escalation currently impacting service confidence.',
              message: 'Open escalations detected.',
              source_entity_type: 'customer',
              source_entity_id: '1',
              customer_id: 1,
              created_at: '2026-03-19 20:00:00',
            },
            {
              id: 'sig-2',
              signal_type: 'delivery_risk',
              severity: 'warning',
              status: 'ACTIVE',
              title: 'Delivery risk flagged on Project for Quote #1',
              summary: 'Open project escalation indicates delivery pressure for RPM Resources (Pty) Ltd.',
              message: 'Project escalation is open.',
              source_entity_type: 'project',
              source_entity_id: '1',
              customer_id: 1,
              created_at: '2026-03-19 20:00:01',
            },
          ]),
        } as any;
      }
      if (url.includes('/customers')) {
        return { ok: true, json: async () => [] } as any;
      }
      return { ok: true, json: async () => [] } as any;
    });

    render(<Advisory />);

    await waitFor(() => {
      expect(screen.getByText(/active escalation pressure/i)).toBeInTheDocument();
      expect(screen.getByText(/delivery risk flagged/i)).toBeInTheDocument();
    });
  });
});

