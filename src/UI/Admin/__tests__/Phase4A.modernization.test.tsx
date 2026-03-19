import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import Finance from '../components/Finance';
import PulsewayRmm from '../components/PulsewayRmm';
import ToastProvider from '../components/foundation/ToastProvider';

const renderWithToast = (node: ReactNode) => render(
  <ToastProvider>
    {node}
  </ToastProvider>
);

const createDeferred = <T,>() => {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

describe('Phase 4A modernization regression guards - Finance', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('queues export via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/billing/exports') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 7,
            uuid: 'exp-7',
            customerId: 1,
            periodStart: '2026-03-01',
            periodEnd: '2026-03-31',
            status: 'draft',
            createdByEmployeeId: 2,
            createdAt: '2026-03-01T00:00:00Z',
            updatedAt: '2026-03-01T00:00:00Z',
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([{ id: 1, name: 'Acme' }]), { status: 200 }));
      }
      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([{ id: 2, firstName: 'A', lastName: 'User' }]), { status: 200 }));
      }
      if (url.endsWith('/billing/exports/7/queue') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
      }
      if (url.endsWith('/finance/qb/invoices') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/finance/qb/payments') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<Finance />);
    await screen.findByText('7');

    fireEvent.click(screen.getByLabelText('Actions'));
    fireEvent.click(screen.getByRole('button', { name: 'Queue' }));

    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Queue' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/billing/exports/7/queue',
        expect.objectContaining({ method: 'POST' })
      );
    });

    const queueCall = fetchSpy.mock.calls.findIndex(([callInput, callInit]) => String(callInput).endsWith('/billing/exports/7/queue') && (callInit?.method ?? 'GET') === 'POST');
    const exportGetIndices = fetchSpy.mock.calls
      .map((call, idx) => ({ call, idx }))
      .filter(({ call }) => String(call[0]).endsWith('/billing/exports') && ((call[1]?.method ?? 'GET') === 'GET'))
      .map(({ idx }) => idx);
    expect(queueCall).toBeGreaterThan(-1);
    expect(exportGetIndices.length).toBeGreaterThanOrEqual(2);
    expect(exportGetIndices[exportGetIndices.length - 1]).toBeGreaterThan(queueCall);

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Export queued.');
  });

  it('renders standardized error state with retry for billing exports', async () => {
    let exportAttempts = 0;
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/billing/exports') && method === 'GET') {
        exportAttempts += 1;
        if (exportAttempts === 1) {
          return Promise.resolve(new Response('{}', { status: 500 }));
        }
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/finance/qb/invoices') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/finance/qb/payments') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<Finance />);
    await screen.findByText('Failed to fetch billing exports');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const exportCalls = fetchSpy.mock.calls.filter(([callInput, callInit]) => String(callInput).endsWith('/billing/exports') && ((callInit?.method ?? 'GET') === 'GET'));
      expect(exportCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 4A modernization regression guards - PulsewayRmm', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('resets circuit via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/pulseway/integrations') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            label: 'Prod',
            api_base_url: 'https://api.pulseway.com/v3',
            consecutive_failures: 6,
            is_active: 1,
            last_poll_at: null,
            last_success_at: null,
            last_error_message: 'timeout',
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/pulseway/integrations/1/reset-circuit') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({ ok: true }), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<PulsewayRmm />);
    await screen.findByText('Prod');

    fireEvent.click(screen.getByRole('button', { name: 'Reset Circuit' }));
    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Reset circuit' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/pulseway/integrations/1/reset-circuit',
        expect.objectContaining({ method: 'POST' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Circuit breaker reset');
  });

  it('renders standardized error state with retry for integrations load', async () => {
    let calls = 0;
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.endsWith('/pulseway/integrations') && method === 'GET') {
        calls += 1;
        if (calls === 1) {
          return Promise.resolve(new Response(JSON.stringify({ error: 'boom' }), { status: 500 }));
        }
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<PulsewayRmm />);
    await screen.findByText('boom');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const integrationCalls = fetchSpy.mock.calls.filter(([callInput, callInit]) => String(callInput).endsWith('/pulseway/integrations') && ((callInit?.method ?? 'GET') === 'GET'));
      expect(integrationCalls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('preserves tab rendering and standardized empty states when no integration exists', async () => {
    const deferredIntegrations = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.endsWith('/pulseway/integrations') && method === 'GET') {
        return deferredIntegrations.promise;
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<PulsewayRmm />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredIntegrations.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No integrations configured. Click "Add Integration" to get started.');

    fireEvent.click(screen.getByRole('button', { name: 'Org Mappings' }));
    await screen.findByText('No integration selected. Create one first.');
  });
});
