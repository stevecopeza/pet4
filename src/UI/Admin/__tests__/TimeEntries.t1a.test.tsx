import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import TimeEntries from '../components/TimeEntries';
import ToastProvider from '../components/foundation/ToastProvider';
const openConversationMock = vi.fn();
let conversationStatuses = new Map<string, any>();

vi.mock('../hooks/useConversation', () => ({
  default: () => ({
    openConversation: openConversationMock,
  }),
}));

vi.mock('../hooks/useConversationStatus', () => ({
  default: () => ({
    statuses: conversationStatuses,
    refresh: vi.fn(),
  }),
}));

const renderWithToast = (node: ReactNode) => {
  return render(
    <ToastProvider>
      {node}
    </ToastProvider>
  );
};

describe('TimeEntries T1-A operational surface', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
    openConversationMock.mockReset();
    conversationStatuses = new Map();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('computes summary strip from currently loaded entries only', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            employeeId: 11,
            ticketId: 101,
            start: '2026-03-10T12:00:00Z',
            end: '2026-03-10T13:00:00Z',
            duration: 60,
            description: 'First task',
            billable: true,
            status: 'approved',
            isCorrection: false,
            correctsEntryId: null,
          },
          {
            id: 2,
            employeeId: 12,
            ticketId: 102,
            start: '2026-03-10T13:00:00Z',
            end: '2026-03-10T13:30:00Z',
            duration: 30,
            description: 'Correction task',
            billable: false,
            status: 'draft',
            isCorrection: true,
            correctsEntryId: 1,
          },
        ]), { status: 200 }));
      }

      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { id: 11, firstName: 'Sam', lastName: 'Tech', displayName: 'Sam Tech', email: 'sam@example.com', createdAt: '2026-01-01T00:00:00Z', archivedAt: null },
        ]), { status: 200 }));
      }

      if (url.endsWith('/tickets') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/sites') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('First task');
    const summaryPanel = screen.getByTestId('time-entries-context-panel');
    expect(summaryPanel).toHaveTextContent(/Entries\s*2/);
    expect(summaryPanel).toHaveTextContent(/Total Logged\s*1h 30m/);
    expect(summaryPanel).toHaveTextContent(/Billable\s*1h 0m \(67%\)/);
    expect(summaryPanel).toHaveTextContent(/Non-billable\s*30m/);
    expect(summaryPanel).toHaveTextContent(/Distinct Staff\s*2/);
    expect(summaryPanel).toHaveTextContent(/Corrections\s*1/);
  });

  it('sends authoritative employee_id and ticket_id query params from dropdown and ticket filter', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            employeeId: 11,
            ticketId: 101,
            start: '2026-03-10T12:00:00Z',
            end: '2026-03-10T13:00:00Z',
            duration: 60,
            description: 'Entry one',
            billable: true,
            status: 'approved',
            isCorrection: false,
            correctsEntryId: null,
          },
        ]), { status: 200 }));
      }

      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/tickets') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      if (url.endsWith('/sites') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByTestId('time-entries-filters-panel');
    const employeeSelect = screen.getByLabelText('Employee') as HTMLSelectElement;
    await waitFor(() => {
      expect(employeeSelect.options.length).toBeGreaterThan(1);
    });

    fireEvent.change(employeeSelect, { target: { value: '11' } });
    await waitFor(() => {
      const calls = fetchSpy.mock.calls.map(([input]) => String(input));
      expect(calls.some((url) => url.includes('/time-entries?employee_id=11'))).toBe(true);
    });

    fireEvent.change(screen.getByLabelText('Ticket ID'), { target: { value: '101' } });
    await waitFor(() => {
      const calls = fetchSpy.mock.calls.map(([input]) => String(input));
      expect(calls.some((url) => url.includes('/time-entries?employee_id=11&ticket_id=101'))).toBe(true);
    });
  });

  it('renders semantic employee/ticket/customer-site fields plus compact indicators', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            employeeId: 11,
            ticketId: 101,
            start: '2026-03-10T12:00:00Z',
            end: '2026-03-10T13:00:00Z',
            duration: 60,
            description: 'Named row',
            billable: true,
            status: 'approved',
            isCorrection: true,
            correctsEntryId: 8,
          },
          {
            id: 2,
            employeeId: 99,
            ticketId: 999,
            start: '2026-03-10T14:00:00Z',
            end: '2026-03-10T15:00:00Z',
            duration: 60,
            description: 'Fallback row',
            billable: false,
            status: 'draft',
            isCorrection: false,
            correctsEntryId: null,
          },
        ]), { status: 200 }));
      }

      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { id: 11, firstName: 'Sam', lastName: 'Tech', displayName: 'Sam Tech', email: 'sam@example.com', createdAt: '2026-01-01T00:00:00Z', archivedAt: null },
        ]), { status: 200 }));
      }

      if (url.endsWith('/tickets') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { id: 101, customerId: 1, siteId: 50, subject: 'Server Repair', description: '', status: 'in_progress', priority: 'high', createdAt: '2026-01-01T00:00:00Z', resolvedAt: null },
        ]), { status: 200 }));
      }

      if (url.endsWith('/customers') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { id: 1, name: 'Acme Industries', contactEmail: 'ops@acme.test', status: 'active', createdAt: '2026-01-01T00:00:00Z', archivedAt: null },
        ]), { status: 200 }));
      }

      if (url.endsWith('/sites') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { id: 50, customerId: 1, name: 'HQ North', addressLines: null, city: null, state: null, postalCode: null, country: null, status: 'active', archivedAt: null },
        ]), { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Named row');
    const employeeSelect = screen.getByLabelText('Employee') as HTMLSelectElement;
    expect(employeeSelect.textContent || '').toContain('Sam Tech');

    expect(screen.getAllByText('Sam Tech').length).toBeGreaterThan(0);
    expect(screen.getByText('#101 · Server Repair')).toBeInTheDocument();
    expect(screen.getByText('Acme Industries · HQ North')).toBeInTheDocument();
    expect(screen.getAllByText('99').length).toBeGreaterThan(0);
    expect(screen.getByText('999')).toBeInTheDocument();
    expect(screen.getByLabelText('Billable')).toBeInTheDocument();
    expect(screen.getByLabelText('Non-billable')).toBeInTheDocument();
    expect(screen.getByLabelText('Billable')).toHaveAttribute('title', 'Billable: Billable');
    expect(screen.getByLabelText('Non-billable')).toHaveAttribute('title', 'Billable: Non-billable');
    expect(screen.getByLabelText('Billable')).toHaveAttribute('data-tooltip', 'Billable: Billable');
    expect(screen.getByLabelText('Non-billable')).toHaveAttribute('data-tooltip', 'Billable: Non-billable');
    expect(screen.getByLabelText('Status: approved')).toBeInTheDocument();
    expect(screen.getByLabelText('Status: draft')).toBeInTheDocument();
    expect(screen.getByLabelText('Status: approved')).toHaveAttribute('title', 'Status: approved');
    expect(screen.getByLabelText('Status: approved')).toHaveAttribute('data-tooltip', 'Status: approved');
    expect(screen.getByLabelText('Correction entry')).toBeInTheDocument();
    expect(screen.getByLabelText('Original entry')).toBeInTheDocument();
    expect(screen.getByLabelText('Correction entry')).toHaveAttribute('title', 'Correction: Correction entry');
    expect(screen.getByLabelText('Original entry')).toHaveAttribute('title', 'Correction: Original entry');
    expect(screen.getByLabelText('Correction entry')).toHaveAttribute('data-tooltip', 'Correction: Correction entry');
    expect(screen.getByLabelText('Original entry')).toHaveAttribute('data-tooltip', 'Correction: Original entry');
    expect(screen.queryByText(/2026/)).not.toBeInTheDocument();
    expect(screen.queryByText(/\d{1,2}:\d{2}:\d{2}/)).not.toBeInTheDocument();
  });

  it('keeps rows usable when lookup enrichment fails and exposes discuss action', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            employeeId: 44,
            ticketId: 555,
            start: '2026-03-10T12:00:00Z',
            end: '2026-03-10T13:00:00Z',
            duration: 60,
            description: 'Fallback lookup row',
            billable: true,
            status: 'approved',
            isCorrection: false,
            correctsEntryId: null,
          },
        ]), { status: 200 }));
      }

      if ((url.endsWith('/employees') || url.endsWith('/tickets') || url.endsWith('/customers') || url.endsWith('/sites')) && method === 'GET') {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Fallback lookup row');

    expect(screen.getAllByText('44').length).toBeGreaterThan(0);
    expect(screen.getByText('555')).toBeInTheDocument();
    expect(screen.getByText('—')).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText('Actions'));
    expect(screen.getByText('Discuss')).toBeInTheDocument();
  });

  it('renders a clickable row conversation status dot for active status', async () => {
    conversationStatuses = new Map([
      ['1', {
        status: 'red',
        unread_count: 2,
        last_message_at: null,
        last_message_actor_id: null,
        conversation_state: 'open',
        child_discussion_count: 0,
        child_worst_status: 'none',
      }],
      ['2', {
        status: 'none',
        unread_count: 0,
        last_message_at: null,
        last_message_actor_id: null,
        conversation_state: 'open',
        child_discussion_count: 0,
        child_worst_status: 'none',
      }],
    ]);

    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/time-entries') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            employeeId: 11,
            ticketId: 101,
            start: '2026-03-10T12:00:00Z',
            end: '2026-03-10T13:00:00Z',
            duration: 60,
            description: 'Entry with red conversation',
            billable: true,
            status: 'approved',
            isCorrection: false,
            correctsEntryId: null,
          },
          {
            id: 2,
            employeeId: 12,
            ticketId: 102,
            start: '2026-03-10T13:00:00Z',
            end: '2026-03-10T13:30:00Z',
            duration: 30,
            description: 'Entry with none conversation',
            billable: false,
            status: 'draft',
            isCorrection: false,
            correctsEntryId: null,
          },
        ]), { status: 200 }));
      }

      if ((url.endsWith('/employees') || url.endsWith('/tickets') || url.endsWith('/customers') || url.endsWith('/sites')) && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Entry with red conversation');

    const redDot = screen.getByRole('button', { name: 'Conversation: red' });
    expect(redDot).toBeInTheDocument();
    expect(redDot).toHaveStyle({ background: '#dc3545' });
    expect(screen.queryByRole('button', { name: 'Conversation: none' })).not.toBeInTheDocument();

    fireEvent.click(redDot);
    expect(openConversationMock).toHaveBeenCalledWith({
      contextType: 'time_entry',
      contextId: '1',
      subject: 'Time Entry #1',
      subjectKey: 'time_entry:1',
    });
  });
});
