import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import Customers from '../components/Customers';
import ToastProvider from '../components/foundation/ToastProvider';

const customersPayload = [
  {
    id: 1,
    name: 'Acme Corp',
    legalName: 'Acme Corporation',
    contactEmail: 'ops@acme.example',
    status: 'active',
    malleableData: {},
    createdAt: '2026-03-01T00:00:00Z',
    archivedAt: null,
  },
];

describe('Customers modernization regression guards', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('archives a single customer without calling window.confirm or window.alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/schemas/customer')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(customersPayload), { status: 200 }));
      }

      if (url.includes('/customers/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    render(
      <ToastProvider>
        <Customers />
      </ToastProvider>
    );

    await screen.findByText('Acme Corp');

    fireEvent.click(screen.getByLabelText('Actions'));
    fireEvent.click(screen.getByText('Archive'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/customers/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Customer archived');
  });

  it('archives selected customers in bulk without calling window.confirm or window.alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/schemas/customer')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(customersPayload), { status: 200 }));
      }

      if (url.includes('/customers/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    render(
      <ToastProvider>
        <Customers />
      </ToastProvider>
    );

    await screen.findByText('Acme Corp');

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[1]);
    fireEvent.click(screen.getByRole('button', { name: 'Archive Selected (1)' }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Archive selected' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/customers/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Archived 1 customers.');
  });
});
