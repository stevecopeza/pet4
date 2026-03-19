import fs from 'node:fs';
import path from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import StaffTimeCapture from '../components/StaffTimeCapture';
import App from '../App';
import ToastProvider from '../components/foundation/ToastProvider';

const renderWithToast = (node: React.ReactNode) => {
  return render(
    <ToastProvider>
      {node}
    </ToastProvider>
  );
};

const contextPayload = {
  employee: {
    id: 11,
    wpUserId: 7,
    firstName: 'Sam',
    lastName: 'Tech',
    displayName: 'Sam Tech',
    status: 'active',
  },
  ticketSuggestions: [
    {
      id: 101,
      subject: 'Resolve outage',
      status: 'in_progress',
      lifecycleOwner: 'support',
      isBillableDefault: true,
      isRollup: false,
    },
  ],
  recentEntrySuggestions: [],
};

describe('Staff Time Capture MVP', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('sends unchanged create payload keys and reconciles via authoritative refetch', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/staff/time-capture/context') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(contextPayload), { status: 200 }));
      }

      if (url.endsWith('/staff/time-capture/entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/staff/time-capture/entries') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({ message: 'Time logged', id: 999 }), { status: 201 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<StaffTimeCapture />);
    await screen.findByTestId('staff-time-capture-shell');

    fireEvent.click(screen.getByRole('button', { name: 'Add Entry' }));
    const ticketButton = await screen.findByRole('button', { name: /#101/i });
    fireEvent.click(ticketButton);
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: '30m' }));
    fireEvent.click(screen.getByRole('button', { name: 'Save Entry' }));

    await waitFor(() => {
      const postCall = fetchSpy.mock.calls.find(([input, init]) => String(input).endsWith('/staff/time-capture/entries') && (init?.method ?? 'GET') === 'POST');
      expect(postCall).toBeTruthy();
    });

    const postCall = fetchSpy.mock.calls.find(([input, init]) => String(input).endsWith('/staff/time-capture/entries') && (init?.method ?? 'GET') === 'POST')!;
    const postBody = JSON.parse(String(postCall[1]?.body ?? '{}'));
    expect(Object.keys(postBody).sort()).toEqual(['description', 'end', 'isBillable', 'start', 'ticketId']);

    await waitFor(() => {
      const entriesFetchCalls = fetchSpy.mock.calls.filter(([input, init]) => String(input).endsWith('/staff/time-capture/entries') && (init?.method ?? 'GET') === 'GET');
      expect(entriesFetchCalls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('surfaces failed create server error cleanly', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/staff/time-capture/context') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(contextPayload), { status: 200 }));
      }

      if (url.endsWith('/staff/time-capture/entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/staff/time-capture/entries') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({
          error: 'Ticket 101 cannot accept time entries. Time may only be logged against assigned, active tickets.',
        }), { status: 400 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<StaffTimeCapture />);
    await screen.findByTestId('staff-time-capture-shell');

    fireEvent.click(screen.getByRole('button', { name: 'Add Entry' }));
    fireEvent.click(await screen.findByRole('button', { name: /#101/i }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Save Entry' }));

    await waitFor(() => {
      const sheetError = document.querySelector('.pet-staff-time-capture-sheet-error');
      expect(sheetError).toBeTruthy();
      expect(sheetError?.textContent || '').toMatch(/cannot accept time entries/i);
    });
  });

  it('keeps existing admin TimeEntries surface unaffected', async () => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
      currentPage: 'pet-time',
      currentUserId: 7,
    };

    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.endsWith('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    render(<App />);
    await screen.findByTestId('time-entries-shell');
    expect(screen.queryByTestId('staff-time-capture-shell')).not.toBeInTheDocument();
  });

  it('does not introduce native alert()/confirm() in Staff Time Capture component', () => {
    const componentPath = path.resolve(__dirname, '../components/StaffTimeCapture.tsx');
    const source = fs.readFileSync(componentPath, 'utf8');
    expect(source).not.toMatch(/\balert\(/);
    expect(source).not.toMatch(/\bconfirm\(/);
  });
});
