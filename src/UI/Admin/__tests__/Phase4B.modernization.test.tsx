import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import QuoteDetails from '../components/QuoteDetails';
import ToastProvider from '../components/foundation/ToastProvider';

vi.mock('../hooks/useConversation', () => ({
  default: () => ({
    openConversation: vi.fn(),
  }),
}));

vi.mock('../hooks/useConversationStatus', () => ({
  default: () => ({
    statuses: new Map(),
  }),
}));

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

const quoteFixture = (overrides: Record<string, unknown> = {}) => ({
  id: 1,
  customerId: 10,
  title: 'Quarterly Services Quote',
  description: 'Scope and pricing',
  state: 'draft',
  version: 1,
  currency: 'USD',
  components: [],
  costAdjustments: [],
  blocks: [
    {
      id: 11,
      sectionId: null,
      type: 'HardwareBlock',
      orderIndex: 0,
      priced: true,
      payload: {
        description: 'Managed Firewall',
        quantity: 1,
        unitPrice: 100,
        totalValue: 100,
      },
    },
  ],
  sections: [],
  paymentSchedule: [
    { id: 1, title: 'Full Payment', amount: 100, dueDate: null, isPaid: false },
  ],
  ...overrides,
});

describe('Phase 4B modernization regression guards - QuoteDetails', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders standardized loading state before quote payload resolves', async () => {
    const deferredQuote = createDeferred<Response>();
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/quotes/1')) {
        return deferredQuote.promise;
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    expect(screen.getByText('Loading quote details…')).toBeInTheDocument();

    deferredQuote.resolve(new Response(JSON.stringify(quoteFixture()), { status: 200 }));
    await screen.findByText('Quarterly Services Quote');
  });

  it('renders standardized error state and retries quote fetch', async () => {
    let quoteAttempts = 0;
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.endsWith('/quotes/1') && method === 'GET') {
        quoteAttempts += 1;
        if (quoteAttempts === 1) {
          return Promise.resolve(new Response('{}', { status: 500 }));
        }
        return Promise.resolve(new Response(JSON.stringify(quoteFixture()), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    await screen.findByText('Failed to fetch quote details');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      const quoteCalls = fetchSpy.mock.calls.filter(([callInput, callInit]) => String(callInput).endsWith('/quotes/1') && ((callInit?.method ?? 'GET') === 'GET'));
      expect(quoteCalls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('renders standardized empty state when quote payload is null', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith('/quotes/1')) {
        return Promise.resolve(new Response('null', { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    await screen.findByText('Quote not found.');
  });

  it('uses ConfirmationDialog and toast for send flow without native confirm/alert', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const alertSpy = vi.spyOn(window, 'alert');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/quotes/1') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(quoteFixture()), { status: 200 }));
      }
      if (url.endsWith('/quotes/1/send') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify(quoteFixture({ state: 'sent' })), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    await screen.findByText('Quarterly Services Quote');

    fireEvent.click(screen.getByRole('button', { name: 'Send Quote' }));
    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Send' }));

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/wp-json/pet/v1/quotes/1/send',
        expect.objectContaining({ method: 'POST' })
      );
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(alertSpy).not.toHaveBeenCalled();
    await screen.findByText('Quote sent.');
  });

  it('preserves payment schedule replace sequencing and payload through confirmation dialog', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const fetchSpy = vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/quotes/1') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(quoteFixture({
          blocks: [
            {
              id: 11,
              sectionId: null,
              type: 'HardwareBlock',
              orderIndex: 0,
              priced: true,
              payload: { description: 'Managed Firewall', quantity: 1, unitPrice: 100, totalValue: 100 },
            },
            {
              id: 12,
              sectionId: null,
              type: 'HardwareBlock',
              orderIndex: 1,
              priced: true,
              payload: { description: 'Switching', quantity: 1, unitPrice: 30, totalValue: 30 },
            },
          ],
          paymentSchedule: [{ id: 91, title: 'Deposit', amount: 50, dueDate: null, isPaid: false }],
        })), { status: 200 }));
      }
      if (url.endsWith('/quotes/1/payment-schedule') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify(quoteFixture({
          paymentSchedule: [{ id: 92, title: 'Full Payment', amount: 130, dueDate: null, isPaid: false }],
          blocks: [
            {
              id: 11,
              sectionId: null,
              type: 'HardwareBlock',
              orderIndex: 0,
              priced: true,
              payload: { description: 'Managed Firewall', quantity: 1, unitPrice: 100, totalValue: 100 },
            },
            {
              id: 12,
              sectionId: null,
              type: 'HardwareBlock',
              orderIndex: 1,
              priced: true,
              payload: { description: 'Switching', quantity: 1, unitPrice: 30, totalValue: 30 },
            },
          ],
        })), { status: 200 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    await screen.findByText('Quarterly Services Quote');

    fireEvent.click(screen.getByLabelText('Add Section'));
    fireEvent.click(screen.getByRole('button', { name: 'Payment schedule (whole quote)' }));

    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Replace' }));

    await waitFor(() => {
      const paymentScheduleCall = fetchSpy.mock.calls.find(([callInput, callInit]) => String(callInput).endsWith('/quotes/1/payment-schedule') && ((callInit?.method ?? 'GET') === 'POST'));
      expect(paymentScheduleCall).toBeDefined();
      const init = paymentScheduleCall?.[1] as RequestInit;
      const parsed = JSON.parse(String(init.body));
      expect(parsed).toEqual({
        milestones: [{ title: 'Full Payment', amount: 130, dueDate: null }],
      });
    });

    expect(confirmSpy).not.toHaveBeenCalled();
    await screen.findByText('Payment schedule set.');
  });

  it('shows toast error feedback on send failure', async () => {
    vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/quotes/1') && method === 'GET') {
        return Promise.resolve(new Response(JSON.stringify(quoteFixture()), { status: 200 }));
      }
      if (url.endsWith('/quotes/1/send') && method === 'POST') {
        return Promise.resolve(new Response(JSON.stringify({ error: 'Cannot send quote' }), { status: 500 }));
      }
      return Promise.resolve(new Response(JSON.stringify([]), { status: 200 }));
    });

    renderWithToast(<QuoteDetails quoteId={1} onBack={vi.fn()} />);
    await screen.findByText('Quarterly Services Quote');

    fireEvent.click(screen.getByRole('button', { name: 'Send Quote' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Send' }));

    await screen.findByText('Cannot send quote');
  });
});
