/**
 * ApprovalsPage — Approval queue
 *
 * Day 7 implementation. Shows quotes in pending_approval state.
 * Managers/admins see all pending quotes.
 * Sales users see only quotes they created (self-approval path).
 */
import React, { useState, useEffect, useCallback } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Quote {
  id: number;
  customerId: number;
  title: string;
  description: string | null;
  state: string;
  version: number;
  totalValue: number;
  margin: number;
  currency: string;
  createdByUserId: number | null;
  approvalState: {
    submittedForApprovalAt: string | null;
    approvedAt: string | null;
    rejectionNote: string | null;
    requiresApprovalForSend: boolean;
    approvalReasons: string[];
  };
}

interface Customer {
  id: number;
  name: string;
}

// ── API helpers ───────────────────────────────────────────────────────────────

function apiBase(): string {
  return (window as any).petSettings?.apiUrl ?? '/wp-json/pet/v1';
}

function apiHeaders(): HeadersInit {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce': (window as any).petSettings?.nonce ?? '',
  };
}

async function apiFetch<T>(path: string, opts: RequestInit = {}): Promise<T> {
  const res = await fetch(`${apiBase()}${path}`, {
    ...opts,
    headers: { ...apiHeaders(), ...(opts.headers ?? {}) },
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as any).error ?? (body as any).message ?? `API error ${res.status}`);
  }
  return res.json() as Promise<T>;
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function fmtCurrency(n: number, currency = 'GBP'): string {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(n);
}

// ── Shared sub-components ────────────────────────────────────────────────────

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: '8px 12px',
  border: '1px solid #e5e7eb',
  borderRadius: 8,
  fontSize: 13,
  outline: 'none',
  boxSizing: 'border-box',
  fontFamily: 'inherit',
  background: '#fff',
};

// ── Approval card ─────────────────────────────────────────────────────────────

function ApprovalCard({
  quote,
  customerName,
  onApprove,
  onReject,
}: {
  quote: Quote;
  customerName: string;
  onApprove: (id: number) => Promise<void>;
  onReject: (id: number, note: string) => Promise<void>;
}) {
  const [mode, setMode] = useState<'idle' | 'rejecting'>('idle');
  const [rejectNote, setRejectNote] = useState('');
  const [acting, setActing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleApprove = async () => {
    try {
      setActing(true);
      setError(null);
      await onApprove(quote.id);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setActing(false);
    }
  };

  const handleReject = async () => {
    if (!rejectNote.trim()) { setError('A rejection note is required.'); return; }
    try {
      setActing(true);
      setError(null);
      await onReject(quote.id, rejectNote);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setActing(false);
    }
  };

  const submitted = quote.approvalState.submittedForApprovalAt;

  return (
    <div
      style={{
        background: '#fff',
        border: '1px solid #e5e7eb',
        borderRadius: 12,
        padding: 20,
        display: 'flex',
        flexDirection: 'column',
        gap: 16,
      }}
    >
      {/* Header row */}
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 2 }}>{quote.title}</div>
          <div style={{ fontSize: 13, color: '#6b7280' }}>{customerName}</div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ fontWeight: 700, fontSize: 16 }}>{fmtCurrency(quote.totalValue, quote.currency)}</div>
          <div
            style={{
              fontSize: 12,
              fontWeight: 600,
              color: quote.margin >= 40 ? '#16a34a' : quote.margin >= 20 ? '#d97706' : '#e11d48',
            }}
          >
            {quote.margin?.toFixed(1)}% margin
          </div>
        </div>
      </div>

      {/* Description */}
      {quote.description && (
        <div style={{ fontSize: 13, color: '#374151', lineHeight: 1.6, borderLeft: '3px solid #e5e7eb', paddingLeft: 12 }}>
          {quote.description}
        </div>
      )}

      {/* Approval reasons */}
      {quote.approvalState.approvalReasons.length > 0 && (
        <div
          style={{
            background: '#fffbeb',
            border: '1px solid #fcd34d',
            borderRadius: 8,
            padding: '10px 14px',
          }}
        >
          <div style={{ fontSize: 11, fontWeight: 600, color: '#d97706', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: 6 }}>
            Approval Required Because
          </div>
          <ul style={{ margin: 0, paddingLeft: 16, fontSize: 12, color: '#374151' }}>
            {quote.approvalState.approvalReasons.map((r, i) => (
              <li key={i}>{r}</li>
            ))}
          </ul>
        </div>
      )}

      {/* Meta */}
      <div style={{ display: 'flex', gap: 16, fontSize: 12, color: '#9ca3af' }}>
        <span>v{quote.version}</span>
        {submitted && <span>Submitted {fmtDate(submitted)}</span>}
      </div>

      {error && (
        <div className="portal-banner portal-banner-amber">
          <div className="portal-banner-text">{error}</div>
        </div>
      )}

      {/* Actions */}
      {mode === 'idle' && (
        <div style={{ display: 'flex', gap: 10 }}>
          <button
            className="portal-btn portal-btn-primary"
            onClick={handleApprove}
            disabled={acting}
            style={{ flex: 1, justifyContent: 'center', background: '#16a34a' }}
          >
            {acting ? '…' : '✓ Approve'}
          </button>
          <button
            className="portal-btn portal-btn-ghost"
            onClick={() => { setMode('rejecting'); setError(null); }}
            style={{ color: '#dc2626', borderColor: '#fecaca' }}
          >
            Reject
          </button>
        </div>
      )}

      {mode === 'rejecting' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <textarea
            value={rejectNote}
            onChange={e => setRejectNote(e.target.value)}
            placeholder="Explain the reason for rejection…"
            rows={3}
            style={{ ...inputStyle, resize: 'vertical' }}
          />
          <div style={{ display: 'flex', gap: 8 }}>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={handleReject}
              disabled={acting}
              style={{ color: '#dc2626', borderColor: '#fecaca', flex: 1, justifyContent: 'center' }}
            >
              {acting ? '…' : 'Confirm Reject'}
            </button>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={() => { setMode('idle'); setError(null); }}
            >
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

const ApprovalsPage: React.FC = () => {
  const user = usePortalUser();
  const [quotes, setQuotes]       = useState<Quote[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);

  // Sales-only users see just their own pending quotes (self-approval).
  // Managers and admins see all pending quotes.
  const canSeeAll = user.isManager || user.isAdmin;

  const customerMap = Object.fromEntries(customers.map(c => [c.id, c.name]));

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [allQuotes, allCustomers] = await Promise.all([
        apiFetch<Quote[]>('/quotes'),
        apiFetch<Customer[]>('/customers'),
      ]);
      const pending = allQuotes.filter(q => q.state === 'pending_approval');
      setQuotes(canSeeAll ? pending : pending.filter(q => q.createdByUserId === user.id));
      setCustomers(allCustomers);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [canSeeAll, user.id]);

  useEffect(() => { load(); }, [load]);

  const handleApprove = async (id: number) => {
    await apiFetch(`/quotes/${id}/approve`, { method: 'POST', body: '{}' });
    await load();
  };

  const handleReject = async (id: number, note: string) => {
    await apiFetch(`/quotes/${id}/reject-approval`, {
      method: 'POST',
      body: JSON.stringify({ note }),
    });
    await load();
  };

  return (
    <div>
      {/* Page header */}
      <div className="portal-page-header">
        <div>
          <div className="portal-page-title">Approvals</div>
          <div className="portal-page-subtitle">
            {canSeeAll
              ? 'Quotes waiting for your review and sign-off'
              : 'Your quotes awaiting approval — you can self-approve'}
          </div>
        </div>
        {!loading && (
          <div style={{ fontSize: 13, color: '#6b7280', alignSelf: 'center' }}>
            {quotes.length === 0
              ? 'All clear — nothing pending'
              : `${quotes.length} quote${quotes.length > 1 ? 's' : ''} pending`}
          </div>
        )}
      </div>

      {/* Content */}
      {loading ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {[1, 2].map(i => (
            <div key={i} style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 12, padding: 20 }}>
              <div className="portal-skeleton" style={{ height: 18, width: '55%', marginBottom: 10, borderRadius: 3 }} />
              <div className="portal-skeleton" style={{ height: 14, width: '30%', marginBottom: 20, borderRadius: 3 }} />
              <div className="portal-skeleton" style={{ height: 36, borderRadius: 8 }} />
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Failed to load approvals</div>
            <div className="portal-empty-subtitle">{error}</div>
            <button className="portal-btn portal-btn-ghost" onClick={load}>Retry</button>
          </div>
        </div>
      ) : quotes.length === 0 ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Nothing pending approval</div>
            <div className="portal-empty-subtitle">
              {canSeeAll
                ? 'All clear — no quotes are waiting for sign-off right now.'
                : 'None of your quotes are awaiting approval right now.'}
            </div>
          </div>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {quotes.map(q => (
            <ApprovalCard
              key={q.id}
              quote={q}
              customerName={customerMap[q.customerId] ?? `Customer #${q.customerId}`}
              onApprove={handleApprove}
              onReject={handleReject}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default ApprovalsPage;
