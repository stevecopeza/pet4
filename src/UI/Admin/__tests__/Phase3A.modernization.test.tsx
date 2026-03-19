import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import Settings from '../components/Settings';
import Knowledge from '../components/Knowledge';
import Approvals from '../components/Approvals';
import ToastProvider from '../components/foundation/ToastProvider';

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

describe('Phase 3A modernization regression guards - Settings', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('runs demo installer via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/settings') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          { key: 'example_setting', value: 'on', type: 'string', description: 'Example', updatedAt: '2026-03-01' },
        ]), { status: 200 }));
      }
      if (url.endsWith('/system/run-demo') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({ announcements: 2, events: 5 }), { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Settings />);
    await screen.findByText('example_setting');

    fireEvent.click(screen.getByRole('button', { name: 'Run Demo Installer' }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Run installer' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/system/run-demo',
        expect.objectContaining({ method: 'POST' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Demo data created: 2 announcements, 5 events');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredSettings = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/settings')) {
        return deferredSettings.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Settings />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredSettings.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No settings defined.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/settings')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Settings />);
    await screen.findByText('Failed to fetch settings');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const settingsCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/settings'));
      expect(settingsCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 3A modernization regression guards - Knowledge', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('archives an article via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/schemas/article')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/articles') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            title: 'Reset VPN',
            category: 'runbook',
            status: 'published',
            malleableData: {},
            createdAt: '2026-01-01',
            updatedAt: '2026-01-02',
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/articles/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Knowledge />);
    await screen.findByText('Reset VPN');

    fireEvent.click(screen.getByLabelText('Actions'));
    fireEvent.click(screen.getByText('Archive'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/articles/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Article archived');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredArticles = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/article')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/articles')) {
        return deferredArticles.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Knowledge />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredArticles.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No articles found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/article')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/articles')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Knowledge />);
    await screen.findByText('Failed to fetch articles');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const articleCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/articles'));
      expect(articleCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 3A modernization regression guards - Approvals', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('responds to approval via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/decisions/pending') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            uuid: 'abc-123',
            decision_type: 'quote_approval',
            conversation_id: 'c-1',
            state: 'pending',
            payload: { amount: 1000 },
            requested_at: '2026-03-01T12:00:00Z',
            requester_id: 99,
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/decisions/abc-123/respond') && method === 'POST') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Approvals />);
    await screen.findByText('quote_approval');

    const approveButtons = screen.getAllByRole('button', { name: 'Approve' });
    fireEvent.click(approveButtons[0]);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Approve' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/decisions/abc-123/respond',
        expect.objectContaining({ method: 'POST' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Request approved.');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredApprovals = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/decisions/pending')) {
        return deferredApprovals.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Approvals />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredApprovals.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No pending approvals found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/decisions/pending')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Approvals />);
    await screen.findByText('Failed to fetch pending approvals');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const approvalCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/decisions/pending'));
      expect(approvalCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
