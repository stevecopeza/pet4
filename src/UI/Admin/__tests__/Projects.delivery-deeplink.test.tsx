import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor } from '@testing-library/react';
import Projects from '../components/Projects';
import ToastProvider from '../components/foundation/ToastProvider';

const projectRecord = {
  id: 17,
  name: 'Demo Delivery Project',
  customerId: 5,
  sourceQuoteId: 101,
  soldHours: 40,
  soldValue: 100000,
  state: 'active',
  malleableData: {},
};

const customerRecords = [
  { id: 5, name: 'Acme Corp' },
];

describe('Projects deep-link delivery workspace', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
    window.history.replaceState(null, '', '/wp-admin/admin.php?page=pet-delivery#project=17');
  });

  afterEach(() => {
    vi.restoreAllMocks();
    window.history.replaceState(null, '', '/');
  });

  it('keeps hash-selected project and opens detail workspace after projects load', async () => {
    let resolveProjects: ((value: Response) => void) | null = null;
    const delayedProjects = new Promise<Response>((resolve) => {
      resolveProjects = resolve;
    });

    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);

      if (url.endsWith('/projects')) {
        return delayedProjects;
      }
      if (url.endsWith('/tickets')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.includes('/conversations/summary?')) {
        return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
      }
      if (url.endsWith('/customers')) {
        return Promise.resolve(new Response(JSON.stringify(customerRecords), { status: 200 }));
      }
      if (url.endsWith('/projects/17')) {
        return Promise.resolve(new Response(JSON.stringify(projectRecord), { status: 200 }));
      }
      if (url.includes('/tickets?project_id=17')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/employees')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    render(
      <ToastProvider>
        <Projects />
      </ToastProvider>
    );

    await waitFor(() => {
      expect(resolveProjects).not.toBeNull();
    });

    await act(async () => {
      resolveProjects?.(new Response(JSON.stringify([projectRecord]), { status: 200 }));
    });

    expect(await screen.findByRole('button', { name: /Back to Projects/i })).toBeInTheDocument();
    expect(await screen.findByText('Demo Delivery Project')).toBeInTheDocument();
  });
});
