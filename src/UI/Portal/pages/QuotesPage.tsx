/**
 * QuotesPage — Portal-native quote list + create + approval actions
 *
 * Refactored to the full-page detail pattern (Phase 2).
 *   - List view  : table, KPI strip, filter tabs, search, approval banner
 *   - Detail view: #quotes/:id hash route with full quote detail + actions
 */
import React, { useState, useEffect, useCallback } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Quote {
  id: number;
  customerId: number;
  leadId: number | null;
  title: string;
  description: string | null;
  state: string;
  version: number;
  totalValue: number;
  margin: number;
  currency: string;
  acceptedAt: string | null;
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
  status: string;
}

// ── Hash routing ──────────────────────────────────────────────────────────────

function parseQuoteHash(hash: string): number | null {
  const m = hash.match(/^#quotes\/(\d+)$/);
  return m ? parseInt(m[1], 10) : null;
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
    throw new Error(
      (body as any).error ?? (body as any).message ?? `API error ${res.status}`
    );
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

// ── Status helpers ────────────────────────────────────────────────────────────

function stateBadgeClass(state: string): string {
  switch (state) {
    case 'draft':            return 'portal-badge-draft';
    case 'pending_approval': return 'portal-badge-pending';
    case 'approved':         return 'portal-badge-approved';
    case 'sent':             return 'portal-badge-sent';
    case 'accepted':         return 'portal-badge-accepted';
    case 'rejected':         return 'portal-badge-rejected';
    case 'archived':         return 'portal-badge-archived';
    default:                 return 'portal-badge-draft';
  }
}

function stateLabel(state: string): string {
  if (state === 'pending_approval') return 'Pending Approval';
  return state.charAt(0).toUpperCase() + state.slice(1);
}

// ── Shared form helpers ───────────────────────────────────────────────────────

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

function FormField({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <label
        style={{
          display: 'block',
          fontSize: 12,
          fontWeight: 600,
          color: '#374151',
          marginBottom: 5,
        }}
      >
        {label}
      </label>
      {children}
    </div>
  );
}

// ── Create Quote Modal ────────────────────────────────────────────────────────

function CreateQuoteModal({
  customers,
  onSave,
  onClose,
}: {
  customers: Customer[];
  onSave: () => void;
  onClose: () => void;
}) {
  const [form, setForm] = useState({
    customerId: '',
    title: '',
    description: '',
    currency: 'GBP',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    if (!form.customerId) { setError('Please select a customer.'); return; }
    if (!form.title.trim()) { setError('Quote title is required.'); return; }
    try {
      setSaving(true);
      setError(null);
      await apiFetch('/quotes', {
        method: 'POST',
        body: JSON.stringify({
          customerId: parseInt(form.customerId),
          title: form.title,
          description: form.description || null,
          currency: form.currency,
          malleableData: {},
        }),
      });
      onSave();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const activeCustomers = customers.filter(c => c.status === 'active');

  const overlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) onClose();
  };

  return (
    <div className="portal-modal-overlay" onClick={overlayClick}>
      <div className="portal-modal">
        <div className="portal-modal-header">
          <div className="portal-modal-title">New Quote</div>
          <button
            className="portal-modal-close"
            onClick={onClose}
            aria-label="Close"
          >
            ×
          </button>
        </div>
        <div className="portal-modal-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {error && (
              <div className="portal-banner portal-banner-amber">
                <div className="portal-banner-text">{error}</div>
              </div>
            )}
            <FormField label="Customer *">
              <select
                value={form.customerId}
                onChange={e =>
                  setForm(f => ({ ...f, customerId: e.target.value }))
                }
                style={inputStyle}
              >
                <option value="">— Select customer —</option>
                {activeCustomers.map(c => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField label="Quote Title *">
              <input
                type="text"
                value={form.title}
                onChange={e => setForm(f => ({ ...f, title: e.target.value }))}
                placeholder="e.g. Managed Services Proposal 2026"
                style={inputStyle}
              />
            </FormField>
            <FormField label="Description">
              <textarea
                value={form.description}
                onChange={e =>
                  setForm(f => ({ ...f, description: e.target.value }))
                }
                rows={3}
                placeholder="Brief overview of what this quote covers…"
                style={{ ...inputStyle, resize: 'vertical' }}
              />
            </FormField>
            <FormField label="Currency">
              <select
                value={form.currency}
                onChange={e =>
                  setForm(f => ({ ...f, currency: e.target.value }))
                }
                style={inputStyle}
              >
                <option value="GBP">GBP (£)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
              </select>
            </FormField>
            <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
              <button
                className="portal-btn portal-btn-primary"
                onClick={handleSave}
                disabled={saving}
                style={{ flex: 1, justifyContent: 'center' }}
              >
                {saving ? 'Creating…' : 'Create Quote'}
              </button>
              <button
                className="portal-btn portal-btn-ghost"
                onClick={onClose}
                disabled={saving}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Quote Detail (full-page) ──────────────────────────────────────────────────

function QuoteDetail({
  quote,
  customerName,
  canApprove,
  onBack,
  onAction,
}: {
  quote: Quote;
  customerName: string;
  canApprove: boolean;
  onBack: () => void;
  onAction: () => void;
}) {
  const [actioning, setActioning]   = useState(false);
  const [rejectNote, setRejectNote] = useState('');
  const [showReject, setShowReject] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const doSubmit = async () => {
    try {
      setActioning(true);
      setActionError(null);
      await apiFetch(`/quotes/${quote.id}/submit-for-approval`, {
        method: 'POST',
        body: '{}',
      });
      onAction();
    } catch (e: any) {
      setActionError(e.message);
    } finally {
      setActioning(false);
    }
  };

  const doApprove = async () => {
    try {
      setActioning(true);
      setActionError(null);
      await apiFetch(`/quotes/${quote.id}/approve`, {
        method: 'POST',
        body: '{}',
      });
      onAction();
    } catch (e: any) {
      setActionError(e.message);
    } finally {
      setActioning(false);
    }
  };

  const doReject = async () => {
    if (!rejectNote.trim()) {
      setActionError('Please provide a rejection note.');
      return;
    }
    try {
      setActioning(true);
      setActionError(null);
      await apiFetch(`/quotes/${quote.id}/reject-approval`, {
        method: 'POST',
        body: JSON.stringify({ note: rejectNote }),
      });
      onAction();
    } catch (e: any) {
      setActionError(e.message);
    } finally {
      setActioning(false);
    }
  };

  return (
    <div>
      {/* Detail header */}
      <div className="portal-detail-header">
        <button className="portal-detail-back" onClick={onBack}>
          ← Quotes
        </button>
        <div className="portal-detail-identity">
          <div>
            <div className="portal-detail-name">{quote.title}</div>
            <div className="portal-detail-meta">{customerName}</div>
          </div>
          <span
            className={`portal-badge ${stateBadgeClass(quote.state)}`}
            style={{ marginLeft: 4 }}
          >
            {stateLabel(quote.state)}
          </span>
        </div>
        <div
          style={{
            fontWeight: 700,
            fontSize: 18,
            color: '#111827',
            whiteSpace: 'nowrap',
          }}
        >
          {fmtCurrency(quote.totalValue, quote.currency)}
        </div>
      </div>

      {/* Main info card */}
      <div className="portal-section-card">
        <div className="portal-section-card-header">
          <div className="portal-section-card-title">Quote Details</div>
          <span style={{ color: '#9ca3af', fontSize: 12 }}>
            v{quote.version}
          </span>
        </div>
        <div className="portal-section-card-body">
          {/* Info grid */}
          <div className="portal-info-grid">
            <div>
              <div className="portal-info-label">Customer</div>
              <div className="portal-info-value">{customerName}</div>
            </div>
            <div>
              <div className="portal-info-label">Status</div>
              <div className="portal-info-value">
                <span className={`portal-badge ${stateBadgeClass(quote.state)}`}>
                  {stateLabel(quote.state)}
                </span>
              </div>
            </div>
            <div>
              <div className="portal-info-label">Total Value</div>
              <div className="portal-info-value" style={{ fontWeight: 600 }}>
                {fmtCurrency(quote.totalValue, quote.currency)}
              </div>
            </div>
            <div>
              <div className="portal-info-label">Margin</div>
              <div className="portal-info-value">
                <span
                  style={{
                    fontWeight: 600,
                    color:
                      quote.margin >= 40
                        ? '#16a34a'
                        : quote.margin >= 20
                        ? '#d97706'
                        : '#e11d48',
                  }}
                >
                  {quote.margin?.toFixed(1)}%
                </span>
              </div>
            </div>
            <div>
              <div className="portal-info-label">Version</div>
              <div className="portal-info-value">v{quote.version}</div>
            </div>
            {quote.acceptedAt && (
              <div>
                <div className="portal-info-label">Accepted</div>
                <div className="portal-info-value">
                  {fmtDate(quote.acceptedAt)}
                </div>
              </div>
            )}
          </div>

          {/* Description */}
          {quote.description && (
            <div
              style={{
                marginTop: 16,
                paddingTop: 16,
                borderTop: '1px solid #f3f4f6',
                fontSize: 13,
                color: '#374151',
                lineHeight: 1.6,
              }}
            >
              {quote.description}
            </div>
          )}

          {/* Rejection banner */}
          {quote.approvalState.rejectionNote && (
            <div
              className="portal-banner portal-banner-amber"
              style={{ marginTop: 16 }}
            >
              <div className="portal-banner-text">
                <strong>Rejected:</strong> {quote.approvalState.rejectionNote}
              </div>
            </div>
          )}

          {/* Builder + PDF links */}
          <div
            style={{
              marginTop: 20,
              paddingTop: 16,
              borderTop: '1px solid #f3f4f6',
              display: 'flex',
              gap: 8,
            }}
          >
            <a
              href={`#quote-builder-${quote.id}`}
              className="portal-btn portal-btn-ghost"
              style={{ flex: 1, justifyContent: 'center', textAlign: 'center' }}
            >
              ✏️ Open Quote Builder
            </a>
            <a
              href={`${apiBase()}/quotes/${quote.id}/pdf?_wpnonce=${
                (window as any).petSettings?.nonce ?? ''
              }`}
              target="_blank"
              rel="noreferrer"
              className="portal-btn portal-btn-ghost portal-btn-sm"
              title="Open print-ready PDF"
            >
              🖨 PDF
            </a>
          </div>

          {/* Action error */}
          {actionError && (
            <div
              className="portal-banner portal-banner-amber"
              style={{ marginTop: 16 }}
            >
              <div className="portal-banner-text">{actionError}</div>
            </div>
          )}

          {/* Submit for approval (draft) */}
          {quote.state === 'draft' && (
            <div style={{ marginTop: 16 }}>
              <button
                className="portal-btn portal-btn-primary"
                onClick={doSubmit}
                disabled={actioning}
                style={{ justifyContent: 'center', width: '100%' }}
              >
                {actioning ? 'Submitting…' : 'Submit for Approval'}
              </button>
            </div>
          )}

          {/* Manager: approve / reject */}
          {quote.state === 'pending_approval' && canApprove && !showReject && (
            <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
              <button
                className="portal-btn portal-btn-primary"
                onClick={doApprove}
                disabled={actioning}
                style={{
                  flex: 1,
                  justifyContent: 'center',
                  background: '#16a34a',
                  borderColor: '#16a34a',
                }}
              >
                {actioning ? '…' : '✓ Approve'}
              </button>
              <button
                className="portal-btn portal-btn-ghost"
                onClick={() => setShowReject(true)}
                style={{ color: '#dc2626', borderColor: '#fecaca' }}
              >
                Reject
              </button>
            </div>
          )}

          {/* Rejection form */}
          {showReject && (
            <div
              style={{
                marginTop: 16,
                display: 'flex',
                flexDirection: 'column',
                gap: 10,
              }}
            >
              <FormField label="Rejection Note *">
                <textarea
                  value={rejectNote}
                  onChange={e => setRejectNote(e.target.value)}
                  placeholder="Explain why the quote is being rejected…"
                  rows={3}
                  style={{ ...inputStyle, resize: 'vertical' }}
                />
              </FormField>
              <div style={{ display: 'flex', gap: 8 }}>
                <button
                  className="portal-btn portal-btn-ghost"
                  onClick={doReject}
                  disabled={actioning}
                  style={{
                    color: '#dc2626',
                    borderColor: '#fecaca',
                    flex: 1,
                    justifyContent: 'center',
                  }}
                >
                  {actioning ? '…' : 'Confirm Reject'}
                </button>
                <button
                  className="portal-btn portal-btn-ghost"
                  onClick={() => setShowReject(false)}
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

type FilterMode =
  | 'all'
  | 'draft'
  | 'pending_approval'
  | 'approved'
  | 'sent'
  | 'accepted';

const FILTER_TABS: { key: FilterMode; label: string }[] = [
  { key: 'all',              label: 'All' },
  { key: 'draft',            label: 'Draft' },
  { key: 'pending_approval', label: 'Pending' },
  { key: 'approved',         label: 'Approved' },
  { key: 'sent',             label: 'Sent' },
  { key: 'accepted',         label: 'Accepted' },
];

const QuotesPage: React.FC = () => {
  const user = usePortalUser();
  const canApprove = user.isManager || user.isAdmin;

  // Hash routing
  const [hash, setHash] = useState(() => window.location.hash);
  useEffect(() => {
    const handler = () => setHash(window.location.hash);
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const detailId = parseQuoteHash(hash);

  // Data
  const [quotes, setQuotes]       = useState<Quote[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);

  // List controls
  const [filter, setFilter] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');

  // Create modal
  const [showCreate, setShowCreate] = useState(false);

  const customerMap = Object.fromEntries(customers.map(c => [c.id, c.name]));

  // ── Data loading ──────────────────────────────────────────────

  const loadQuotes = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<Quote[]>('/quotes');
      setQuotes(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadCustomers = useCallback(async () => {
    try {
      const data = await apiFetch<Customer[]>('/customers');
      setCustomers(data);
    } catch {
      setCustomers([]);
    }
  }, []);

  useEffect(() => {
    loadQuotes();
    loadCustomers();
  }, [loadQuotes, loadCustomers]);

  // ── Actions ───────────────────────────────────────────────────

  const handleCreated = () => { loadQuotes(); setShowCreate(false); };

  // After an approval action, reload quotes and stay on detail (UI updates)
  const handleAction = () => { loadQuotes(); };

  // ── KPIs ──────────────────────────────────────────────────────

  const kpiPending  = quotes.filter(q => q.state === 'pending_approval').length;
  const kpiDraft    = quotes.filter(q => q.state === 'draft').length;
  const kpiAccepted = quotes.filter(q => q.state === 'accepted').length;
  const totalPipeline = quotes
    .filter(q =>
      ['draft', 'pending_approval', 'approved', 'sent'].includes(q.state)
    )
    .reduce((s, q) => s + q.totalValue, 0);

  // ── Filtering ─────────────────────────────────────────────────

  const filtered = quotes.filter(q => {
    if (filter !== 'all' && q.state !== filter) return false;
    if (search) {
      const qu = search.toLowerCase();
      const cName = (customerMap[q.customerId] ?? '').toLowerCase();
      return q.title.toLowerCase().includes(qu) || cName.includes(qu);
    }
    return true;
  });

  // ── Detail view ───────────────────────────────────────────────

  if (detailId !== null) {
    const quote = quotes.find(q => q.id === detailId);

    if (loading) {
      return (
        <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>
          Loading…
        </div>
      );
    }

    if (!quote) {
      return (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Quote not found</div>
            <div className="portal-empty-subtitle">
              This quote may have been archived or deleted.
            </div>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={() => { window.location.hash = '#quotes'; }}
            >
              ← Back to Quotes
            </button>
          </div>
        </div>
      );
    }

    return (
      <QuoteDetail
        quote={quote}
        customerName={
          customerMap[quote.customerId] ?? `Customer #${quote.customerId}`
        }
        canApprove={canApprove}
        onBack={() => { window.location.hash = '#quotes'; }}
        onAction={handleAction}
      />
    );
  }

  // ── List view ─────────────────────────────────────────────────

  return (
    <div>
      {/* Page header */}
      <div className="portal-page-header">
        <div>
          <div className="portal-page-title">Quotes</div>
          <div className="portal-page-subtitle">
            Manage and track all customer quotations
          </div>
        </div>
        <button
          className="portal-btn portal-btn-primary"
          onClick={() => setShowCreate(true)}
        >
          + New Quote
        </button>
      </div>

      {/* KPI strip */}
      <div className="portal-kpi-strip">
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Pipeline</div>
          <div className="portal-kpi-value" style={{ fontSize: 22 }}>
            {fmtCurrency(totalPipeline)}
          </div>
          <div className="portal-kpi-sub">draft + in-flight</div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Draft</div>
          <div className="portal-kpi-value" style={{ color: '#9ca3af' }}>
            {kpiDraft}
          </div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Pending Approval</div>
          <div className="portal-kpi-value" style={{ color: '#d97706' }}>
            {kpiPending}
          </div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Accepted</div>
          <div className="portal-kpi-value" style={{ color: '#16a34a' }}>
            {kpiAccepted}
          </div>
        </div>
      </div>

      {/* Approval banner */}
      {canApprove && kpiPending > 0 && (
        <div className="portal-banner portal-banner-amber">
          <span>⏳</span>
          <div className="portal-banner-text">
            <strong>
              {kpiPending} quote{kpiPending > 1 ? 's' : ''}
            </strong>{' '}
            awaiting your approval
          </div>
          <button
            className="portal-btn portal-btn-ghost portal-btn-sm"
            onClick={() => setFilter('pending_approval')}
          >
            Review
          </button>
        </div>
      )}

      {/* Filter row */}
      <div className="portal-filters-row">
        {FILTER_TABS.map(t => (
          <button
            key={t.key}
            className={`portal-filter-tab${filter === t.key ? ' active' : ''}`}
            onClick={() => setFilter(t.key)}
          >
            {t.label}
            {t.key === 'pending_approval' && kpiPending > 0 && (
              <span className="portal-nav-badge" style={{ marginLeft: 6 }}>
                {kpiPending}
              </span>
            )}
          </button>
        ))}
        <div className="portal-filter-spacer" />
        <input
          type="search"
          placeholder="Search quotes…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{
            padding: '6px 12px',
            borderRadius: 8,
            border: '1px solid #e5e7eb',
            fontSize: 13,
            outline: 'none',
            width: 200,
            fontFamily: 'inherit',
          }}
        />
      </div>

      {/* Table */}
      {loading ? (
        <div className="portal-card">
          {[1, 2, 3, 4, 5].map(i => (
            <div
              key={i}
              style={{
                padding: '14px 16px',
                borderBottom: '1px solid #e5e7eb',
                display: 'flex',
                gap: 12,
              }}
            >
              <div style={{ flex: 1 }}>
                <div
                  className="portal-skeleton"
                  style={{ height: 14, width: '40%', marginBottom: 6, borderRadius: 3 }}
                />
                <div
                  className="portal-skeleton"
                  style={{ height: 12, width: '25%', borderRadius: 3 }}
                />
              </div>
              <div
                className="portal-skeleton"
                style={{ height: 22, width: 80, borderRadius: 20 }}
              />
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Failed to load quotes</div>
            <div className="portal-empty-subtitle">{error}</div>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={loadQuotes}
            >
              Retry
            </button>
          </div>
        </div>
      ) : filtered.length === 0 ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">
              {search ? 'No matching quotes' : 'No quotes yet'}
            </div>
            <div className="portal-empty-subtitle">
              {search
                ? 'Try different search terms.'
                : 'Create your first quote to get started.'}
            </div>
            {!search && (
              <button
                className="portal-btn portal-btn-primary"
                onClick={() => setShowCreate(true)}
              >
                + New Quote
              </button>
            )}
          </div>
        </div>
      ) : (
        <div className="portal-card">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Customer</th>
                <th>Value</th>
                <th>Margin</th>
                <th>Status</th>
                <th>Version</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map(q => (
                <tr
                  key={q.id}
                  onClick={() => {
                    window.location.hash = `#quotes/${q.id}`;
                  }}
                  style={{ cursor: 'pointer' }}
                >
                  <td>
                    <div style={{ fontWeight: 600 }}>{q.title}</div>
                    {q.approvalState.rejectionNote && (
                      <div style={{ fontSize: 11, color: '#dc2626' }}>
                        ↳ Rejected
                      </div>
                    )}
                  </td>
                  <td style={{ color: '#6b7280' }}>
                    {customerMap[q.customerId] ?? `Customer #${q.customerId}`}
                  </td>
                  <td style={{ fontWeight: 600 }}>
                    {fmtCurrency(q.totalValue, q.currency)}
                  </td>
                  <td>
                    {q.margin != null ? (
                      <span
                        style={{
                          display: 'inline-block',
                          padding: '2px 8px',
                          borderRadius: 10,
                          fontSize: 11.5,
                          fontWeight: 600,
                          background:
                            q.margin >= 40
                              ? '#f0fdf4'
                              : q.margin >= 20
                              ? '#fffbeb'
                              : '#fff1f2',
                          color:
                            q.margin >= 40
                              ? '#16a34a'
                              : q.margin >= 20
                              ? '#d97706'
                              : '#e11d48',
                        }}
                      >
                        {q.margin.toFixed(1)}%
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td>
                    <span
                      className={`portal-badge ${stateBadgeClass(q.state)}`}
                    >
                      {stateLabel(q.state)}
                    </span>
                  </td>
                  <td style={{ color: '#9ca3af' }}>v{q.version}</td>
                  <td
                    style={{ textAlign: 'right' }}
                    onClick={e => e.stopPropagation()}
                  >
                    <button
                      className="portal-btn portal-btn-ghost portal-btn-sm"
                      onClick={() => {
                        window.location.hash = `#quotes/${q.id}`;
                      }}
                    >
                      View →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create modal */}
      {showCreate && (
        <CreateQuoteModal
          customers={customers}
          onSave={handleCreated}
          onClose={() => setShowCreate(false)}
        />
      )}
    </div>
  );
};

export default QuotesPage;
