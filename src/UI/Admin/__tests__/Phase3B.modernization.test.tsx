import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import Activity from '../components/Activity';
import Conversations from '../components/Conversations';
import Escalations from '../components/Escalations';
import ToastProvider from '../components/foundation/ToastProvider';

vi.mock('../components/Feed', () => ({
  default: () => <div>Feed</div>,
}));

vi.mock('../components/EventStreamViewer', () => ({
  default: () => <div>Event Stream Viewer</div>,
}));

const renderWithToast = (node: ReactNode) => {
  return render(
    <ToastProvider>
      {node}
    </ToastProvider>
  );
};

const createDeferred = <T,>() => {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

describe('Phase 3B modernization regression guards - Activity', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('does not invoke native confirm/alert in read-only flow', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/activity')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Activity />);
    await screen.findByText('No activity recorded yet.');

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
  });

  it('renders standardized loading then empty state', async () => {
    const deferredActivity = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/activity')) {
        return deferredActivity.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Activity />);
    expect(screen.getByText('Loading activity feed…')).toBeInTheDocument();

    deferredActivity.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No activity recorded yet.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/activity')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Activity />);
    await screen.findByText('Failed to fetch activity logs');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const activityCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).includes('/activity'));
      expect(activityCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 3B modernization regression guards - Conversations', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('does not invoke native confirm/alert while opening a conversation', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/conversations/me')) {
        return Promise.resolve(new Response(JSON.stringify([
          {
            uuid: 'c-1',
            context_type: 'project',
            context_id: '1',
            subject: 'Project Conversation',
            state: 'open',
            created_at: '2026-03-01T12:00:00Z',
          },
        ]), { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Conversations />);
    const subjectButton = await screen.findByRole('button', { name: 'Project Conversation' });
    fireEvent.click(subjectButton);

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
  });

  it('renders standardized loading then empty state', async () => {
    const deferredConversations = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/conversations/me')) {
        return deferredConversations.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Conversations />);
    expect(screen.getByText('Loading conversations…')).toBeInTheDocument();

    deferredConversations.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No conversations found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/conversations/me')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Conversations />);
    await screen.findByText('Failed to fetch conversations');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const conversationCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).includes('/conversations/me'));
      expect(conversationCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 3B modernization regression guards - Escalations', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('acknowledges escalation via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const promptSpy = vi.spyOn(window, 'prompt').mockReturnValue('');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/escalations?') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify({
          items: [
            {
              id: 1,
              escalation_id: 'es-1',
              source_entity_type: 'ticket',
              source_entity_id: 10,
              severity: 'HIGH',
              status: 'OPEN',
              reason: 'SLA breach',
              summary: 'Customer impact',
              metadata: {},
              created_by: null,
              acknowledged_by: null,
              resolved_by: null,
              created_at: '2026-03-01T00:00:00Z',
              opened_at: '2026-03-01T00:00:00Z',
              acknowledged_at: null,
              resolved_at: null,
              resolution_note: null,
            },
          ],
          total: 1,
          page: 1,
          per_page: 20,
        }), { status: 200 }));
      }

      if (url.endsWith('/escalations/1/acknowledge') && method === 'POST') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }

      if (url.endsWith('/escalations/1') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify({
          id: 1,
          escalation_id: 'es-1',
          source_entity_type: 'ticket',
          source_entity_id: 10,
          severity: 'HIGH',
          status: 'OPEN',
          reason: 'SLA breach',
          summary: 'Customer impact',
          metadata: {},
          created_by: null,
          acknowledged_by: null,
          resolved_by: null,
          created_at: '2026-03-01T00:00:00Z',
          opened_at: '2026-03-01T00:00:00Z',
          acknowledged_at: null,
          resolved_at: null,
          resolution_note: null,
          transitions: [],
        }), { status: 200 }));
      }

      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Escalations />);
    await screen.findByText('SLA breach');

    fireEvent.click(screen.getByRole('button', { name: 'Acknowledge' }));
    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Acknowledge' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/escalations/1/acknowledge',
        expect.objectContaining({ method: 'POST' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    expect(promptSpy).not.toHaveBeenCalled();
    await screen.findByText('Escalation acknowledged.');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredEscalations = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.includes('/escalations?') && method === 'GET') {
        return deferredEscalations.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Escalations />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredEscalations.resolve(new Response(JSON.stringify({
      items: [],
      total: 0,
      page: 1,
      per_page: 20,
    }), { status: 200 }));
    await screen.findByText('No escalations found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.includes('/escalations?') && method === 'GET') {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Escalations />);
    await screen.findByText('Failed to fetch escalations');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const escalationCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).includes('/escalations?'));
      expect(escalationCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
