import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import Projects from '../components/Projects';
import TimeEntries from '../components/TimeEntries';
import Employees from '../components/Employees';
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

describe('Phase 2 modernization regression guards - Projects', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('archives a project via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/schemas/project')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.includes('/conversations/summary')) {
        return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
      }
      if (url.endsWith('/projects') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            name: 'Project One',
            customerId: 10,
            sourceQuoteId: null,
            soldHours: 8,
            state: 'active',
            tasks: [],
            malleableData: {},
            archivedAt: null,
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/projects/1') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify({
          id: 1,
          name: 'Project One',
          customerId: 10,
          sourceQuoteId: null,
          soldHours: 8,
          state: 'active',
          tasks: [],
          malleableData: {},
          archivedAt: null,
        }), { status: 200 }));
      }
      if (url.endsWith('/projects/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Projects />);
    await screen.findByText('Project One');

    fireEvent.click(screen.getByLabelText('Actions'));
    const kebabMenu = document.querySelector('.pet-kebab-menu');
    expect(kebabMenu).not.toBeNull();
    fireEvent.click(within(kebabMenu as HTMLElement).getByText('Archive'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/projects/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Project archived');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredProjects = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/project')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/projects')) {
        return deferredProjects.promise;
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<Projects />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredProjects.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No projects found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/project')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/projects')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<Projects />);
    await screen.findByText('Failed to fetch projects');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const projectFetchCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/projects'));
      expect(projectFetchCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});

describe('Phase 2 modernization regression guards - TimeEntries', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('archives a time entry via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
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
            description: 'Investigation',
            billable: true,
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/time-entries/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Investigation');

    fireEvent.click(screen.getByLabelText('Actions'));
    fireEvent.click(screen.getByText('Archive'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/time-entries/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Time entry archived');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredEntries = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/time-entries')) {
        return deferredEntries.promise;
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredEntries.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No time entries found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/time-entries')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Failed to fetch time entries');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const entryFetchCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/time-entries'));
      expect(entryFetchCalls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('renders transformed shell/container and bulk action strip without behavior regression', async () => {
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
            description: 'Investigation',
            billable: true,
          },
        ]), { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<TimeEntries />);
    await screen.findByText('Investigation');

    expect(screen.getByTestId('time-entries-shell')).toBeInTheDocument();
    expect(screen.getByTestId('time-entries-context-panel')).toBeInTheDocument();
    expect(screen.getByTestId('time-entries-main-panel')).toBeInTheDocument();
    expect(screen.getByText('Track and manage billable and non-billable technician effort.')).toBeInTheDocument();
    expect(screen.getByText('Entry List')).toBeInTheDocument();
    expect(screen.queryByTestId('time-entries-bulk-strip')).not.toBeInTheDocument();

    fireEvent.click(screen.getByLabelText('Select row 1'));
    expect(screen.getByTestId('time-entries-bulk-strip')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Archive Selected' }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Archive selected' })).toBeInTheDocument();
  });
});

describe('Phase 2 modernization regression guards - Employees', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
      featureFlags: { resilience_indicators_enabled: false },
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('archives an employee via ConfirmationDialog and toast without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.includes('/schemas/employee')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.includes('/leave/types')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/employees') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify([
          {
            id: 1,
            firstName: 'Alex',
            lastName: 'Smith',
            email: 'alex@example.com',
            status: 'active',
            hireDate: '2025-01-01',
            managerId: null,
            malleableData: {},
            createdAt: '2026-01-01T00:00:00Z',
            archivedAt: null,
          },
        ]), { status: 200 }));
      }
      if (url.endsWith('/employees/1') && method === 'DELETE') {
        return Promise.resolve(new Response('{}', { status: 200 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Employees />);
    await screen.findByText('Alex Smith');

    fireEvent.click(screen.getByLabelText('Actions'));
    fireEvent.click(screen.getByText('Archive'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/employees/1',
        expect.objectContaining({ method: 'DELETE' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Employee archived');
  });

  it('renders standardized loading then empty state', async () => {
    const deferredEmployees = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/employee')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.includes('/leave/types')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/employees')) {
        return deferredEmployees.promise;
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Employees />);
    expect(screen.getByText('Loading…')).toBeInTheDocument();

    deferredEmployees.resolve(new Response(JSON.stringify([]), { status: 200 }));
    await screen.findByText('No employees found.');
  });

  it('renders standardized error state with retry', async () => {
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes('/schemas/employee')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.includes('/leave/types')) {
        return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
      }
      if (url.endsWith('/employees')) {
        return Promise.resolve(new Response('{}', { status: 500 }));
      }
      return Promise.resolve(new Response('{}', { status: 200 }));
    });

    renderWithToast(<Employees />);
    await screen.findByText('Failed to fetch employees');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const employeeFetchCalls = fetchSpy.mock.calls.filter(([callInput]) => String(callInput).endsWith('/employees'));
      expect(employeeFetchCalls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
