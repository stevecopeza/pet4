/**
 * StaffApprovalsPage — [pet_my_approvals] shortcode component (React SPA)
 *
 * Mobile-optimised quote approval queue for managers.
 * Uses GET /pet/v1/quotes (filter to pending_approval client-side),
 *      POST /pet/v1/quotes/{id}/approve
 *      POST /pet/v1/quotes/{id}/reject-approval
 */
import React, { useState, useEffect, useCallback } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface ApprovalState {
  submittedForApprovalAt: string | null;
  approvedAt: string | null;
  rejectionNote: string | null;
}

interface Quote {
  id: number;
  title: string;
  totalValue: number;
  currency: string;
  state: string;
  approvalState: ApprovalState;
}

// ── API helpers ───────────────────────────────────────────────────────────────

declare const petStaffConfig: { apiUrl: string; nonce: string; userId: number };

function apiBase(): string {
  return (typeof petStaffConfig !== 'undefined' ? petStaffConfig.apiUrl : '/wp-json/pet/v1').replace(/\/$/, '');
}

function apiHeaders(): HeadersInit {
  const nonce = typeof petStaffConfig !== 'undefined' ? petStaffConfig.nonce : '';
  return { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce };
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

// ── Utilities ─────────────────────────────────────────────────────────────────

function fmtCurrency(value: number, currency = 'GBP'): string {
  try {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(value);
  } catch {
    return `${currency} ${value.toFixed(0)}`;
  }
}

function daysSince(iso: string | null): number {
  if (!iso) return 0;
  return Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
}

// ── Approval card ─────────────────────────────────────────────────────────────

interface ApprovalCardProps {
  quote: Quote;
  onApprove: (id: number) => Promise<void>;
  onReject: (id: number, note: string) => Promise<void>;
}

function ApprovalCard({ quote, onApprove, onReject }: ApprovalCardProps) {
  const [mode, setMode]         = useState<'idle' | 'rejecting'>('idle');
  const [rejectNote, setNote]   = useState('');
  const [acting, setActing]     = useState(false);
  const [cardError, setCardError] = useState<string | null>(null);

  const days   = daysSince(quote.approvalState.submittedForApprovalAt);
  const urgency = days >= 3 ? 'high' : days >= 1 ? 'medium' : 'low';

  const handleApprove = async () => {
    try {
      setActing(true);
      setCardError(null);
      await onApprove(quote.id);
    } catch (e: any) {
      setCardError(e.message);
      setActing(false);
    }
  };

  const handleReject = async () => {
    if (!rejectNote.trim()) { setCardError('Rejection note is required.'); return; }
    try {
      setActing(true);
      setCardError(null);
      await onReject(quote.id, rejectNote);
    } catch (e: any) {
      setCardError(e.message);
      setActing(false);
    }
  };

  return (
    <div className={`staff-approval-card staff-approval-card--${urgency}`}>
      <div className="staff-approval-card__header">
        <span className="staff-approval-card__id">Q-{String(quote.id).padStart(4, '0')}</span>
        <span className="staff-approval-card__value">
          {fmtCurrency(quote.totalValue, quote.currency)}
        </span>
      </div>

      <div className="staff-approval-card__title">{quote.title}</div>

      {quote.approvalState.submittedForApprovalAt && (
        <div className="staff-approval-card__meta">
          Submitted{' '}
          {new Date(quote.approvalState.submittedForApprovalAt).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
          })}
          {days > 0 && ` · ${days}d ago`}
        </div>
      )}

      {cardError && <div className="staff-error-box staff-error-box--inline">{cardError}</div>}

      {mode === 'idle' ? (
        <div className="staff-approval-card__actions">
          <button
            className="staff-btn-approve"
            onClick={handleApprove}
            disabled={acting}
          >
            ✓ Approve
          </button>
          <button
            className="staff-btn-reject"
            onClick={() => setMode('rejecting')}
            disabled={acting}
          >
            ✗ Reject
          </button>
        </div>
      ) : (
        <div className="staff-approval-card__reject-form">
          <textarea
            className="staff-textarea"
            placeholder="Reason for rejection…"
            rows={2}
            value={rejectNote}
            onChange={e => setNote(e.target.value)}
            autoFocus
          />
          <div className="staff-approval-card__actions">
            <button
              className="staff-btn-approve"
              onClick={handleReject}
              disabled={acting}
            >
              Confirm Reject
            </button>
            <button
              className="staff-btn-ghost"
              onClick={() => { setMode('idle'); setNote(''); setCardError(null); }}
              disabled={acting}
            >
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function StaffApprovalsPage() {
  const [quotes, setQuotes]   = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const all = await apiFetch<Quote[]>('/quotes');
      setQuotes(all.filter(q => q.state === 'pending_approval'));
    } catch (e: any) {
      setError(e.message ?? 'Failed to load');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleApprove = async (id: number) => {
    await apiFetch(`/quotes/${id}/approve`, { method: 'POST', body: '{}' });
    setQuotes(prev => prev.filter(q => q.id !== id));
  };

  const handleReject = async (id: number, note: string) => {
    await apiFetch(`/quotes/${id}/reject-approval`, {
      method: 'POST',
      body: JSON.stringify({ note }),
    });
    setQuotes(prev => prev.filter(q => q.id !== id));
  };

  if (loading) {
    return (
      <div className="staff-page">
        <div className="staff-loading">Loading…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="staff-page">
        <div className="staff-error-box">{error}</div>
      </div>
    );
  }

  return (
    <div className="staff-page">
      <div className="staff-header">
        <span className="staff-header__title">
          Approvals
          {quotes.length > 0 && (
            <span className="staff-badge">{quotes.length}</span>
          )}
        </span>
      </div>

      {quotes.length === 0 ? (
        <div className="staff-empty">No quotes awaiting your approval.</div>
      ) : (
        <div className="staff-approvals-list">
          {quotes.map(q => (
            <ApprovalCard
              key={q.id}
              quote={q}
              onApprove={handleApprove}
              onReject={handleReject}
            />
          ))}
        </div>
      )}
    </div>
  );
}
