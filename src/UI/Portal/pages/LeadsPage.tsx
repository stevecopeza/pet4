/**
 * LeadsPage — Portal-native lead management
 *
 * Refactored to the full-page detail pattern (Phase 2).
 *   - List view  : table, KPI strip, filter tabs, search, Create/Edit modals
 *   - Detail view: #leads/:id hash route with lead info + Convert / Delete actions
 */
import React, { useState, useEffect, useCallback } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Lead {
  id: number;
  customerId: number | null;
  customerName: string | null;
  subject: string;
  description: string;
  status: string;
  source: string | null;
  estimatedValue: number | null;
  opportunityId: string | null;
  createdAt: string;
  updatedAt: string | null;
  convertedAt: string | null;
}

interface Customer {
  id: number;
  name: string;
  status: string;
}

// ── Hash routing ──────────────────────────────────────────────────────────────

function parseLeadHash(hash: string): number | null {
  const m = hash.match(/^#leads\/(\d+)$/);
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

function fmtCurrency(n: number): string {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    maximumFractionDigits: 0,
  }).format(n);
}

// ── Status helpers ────────────────────────────────────────────────────────────

const STATUS_OPTIONS = ['new', 'qualified', 'converted', 'lost'];

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'new':       return 'portal-badge-pending';
    case 'qualified': return 'portal-badge-sent';
    case 'converted': return 'portal-badge-accepted';
    case 'lost':      return 'portal-badge-rejected';
    default:          return 'portal-badge-draft';
  }
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

// ── Lead form (used inside the modal) ─────────────────────────────────────────

interface LeadFormProps {
  lead: Lead | null;
  customers: Customer[];
  onSave: () => void;
  onClose: () => void;
}

function LeadForm({ lead, customers, onSave, onClose }: LeadFormProps) {
  const isEdit = !!lead;
  const [form, setForm] = useState({
    customerId: lead?.customerId ? String(lead.customerId) : '',
    subject: lead?.subject ?? '',
    description: lead?.description ?? '',
    status: lead?.status ?? 'new',
    source: lead?.source ?? '',
    estimatedValue:
      lead?.estimatedValue != null ? String(lead.estimatedValue) : '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    if (!form.subject.trim()) {
      setError('Subject is required.');
      return;
    }
    try {
      setSaving(true);
      setError(null);
      const payload = {
        customerId: form.customerId ? parseInt(form.customerId) : null,
        subject: form.subject,
        description: form.description,
        status: form.status,
        source: form.source || null,
        estimatedValue: form.estimatedValue
          ? parseFloat(form.estimatedValue)
          : null,
        malleableData: {},
      };
      if (isEdit) {
        await apiFetch(`/leads/${lead!.id}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        });
      } else {
        await apiFetch('/leads', { method: 'POST', body: JSON.stringify(payload) });
      }
      onSave();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const activeCustomers = customers.filter(c => c.status === 'active');

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && (
        <div className="portal-banner portal-banner-amber">
          <div className="portal-banner-text">{error}</div>
        </div>
      )}

      <FormField label="Subject *">
        <input
          type="text"
          value={form.subject}
          onChange={e => setForm(f => ({ ...f, subject: e.target.value }))}
          placeholder="e.g. Network infrastructure refresh"
          style={inputStyle}
        />
      </FormField>

      <FormField label="Customer">
        <select
          value={form.customerId}
          onChange={e => setForm(f => ({ ...f, customerId: e.target.value }))}
          style={inputStyle}
        >
          <option value="">— Unlinked —</option>
          {activeCustomers.map(c => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </FormField>

      <FormField label="Description">
        <textarea
          value={form.description}
          onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
          placeholder="Opportunity details, background context…"
          rows={3}
          style={{ ...inputStyle, resize: 'vertical' }}
        />
      </FormField>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <FormField label="Status">
          <select
            value={form.status}
            onChange={e => setForm(f => ({ ...f, status: e.target.value }))}
            style={inputStyle}
          >
            {STATUS_OPTIONS.map(s => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </select>
        </FormField>

        <FormField label="Est. Value (£)">
          <input
            type="number"
            step="100"
            min="0"
            value={form.estimatedValue}
            onChange={e =>
              setForm(f => ({ ...f, estimatedValue: e.target.value }))
            }
            placeholder="0"
            style={inputStyle}
          />
        </FormField>
      </div>

      <FormField label="Source">
        <input
          type="text"
          value={form.source}
          onChange={e => setForm(f => ({ ...f, source: e.target.value }))}
          placeholder="e.g. Referral, Website, Cold outreach"
          style={inputStyle}
        />
      </FormField>

      <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
        <button
          className="portal-btn portal-btn-primary"
          onClick={handleSave}
          disabled={saving}
          style={{ flex: 1, justifyContent: 'center' }}
        >
          {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Lead'}
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
  );
}

// ── Lead Modal (Create / Edit) ────────────────────────────────────────────────

function LeadModal({
  lead,
  customers,
  onSave,
  onClose,
}: {
  lead: Lead | null;
  customers: Customer[];
  onSave: () => void;
  onClose: () => void;
}) {
  const overlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) onClose();
  };

  return (
    <div className="portal-modal-overlay" onClick={overlayClick}>
      <div className="portal-modal">
        <div className="portal-modal-header">
          <div className="portal-modal-title">
            {lead ? 'Edit Lead' : 'New Lead'}
          </div>
          <button
            className="portal-modal-close"
            onClick={onClose}
            aria-label="Close"
          >
            ×
          </button>
        </div>
        <div className="portal-modal-body">
          <LeadForm
            lead={lead}
            customers={customers}
            onSave={onSave}
            onClose={onClose}
          />
        </div>
      </div>
    </div>
  );
}

// ── Lead Detail (full-page) ───────────────────────────────────────────────────

function LeadDetail({
  lead,
  onBack,
  onEdit,
  onConvert,
  onDelete,
}: {
  lead: Lead;
  onBack: () => void;
  onEdit: (l: Lead) => void;
  onConvert: (id: number) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
}) {
  const [converting, setConverting] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const handleConvert = async () => {
    if (
      !confirm('Convert this lead to a quote? This cannot be undone.')
    )
      return;
    setConverting(true);
    try {
      await onConvert(lead.id);
    } finally {
      setConverting(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm('Delete this lead permanently?')) return;
    setDeleting(true);
    try {
      await onDelete(lead.id);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div>
      {/* Detail header */}
      <div className="portal-detail-header">
        <button className="portal-detail-back" onClick={onBack}>
          ← Leads
        </button>
        <div className="portal-detail-identity">
          <div>
            <div className="portal-detail-name">{lead.subject}</div>
            <div className="portal-detail-meta">
              {lead.customerName ?? 'Unlinked'}
            </div>
          </div>
          <span
            className={`portal-badge ${statusBadgeClass(lead.status)}`}
            style={{ marginLeft: 4 }}
          >
            {lead.status}
          </span>
        </div>
        <button
          className="portal-btn portal-btn-ghost portal-btn-sm"
          onClick={() => onEdit(lead)}
        >
          Edit
        </button>
      </div>

      {/* Lead info card */}
      <div className="portal-section-card">
        <div className="portal-section-card-header">
          <div className="portal-section-card-title">Lead Details</div>
          <button
            className="portal-btn portal-btn-ghost portal-btn-sm"
            onClick={() => onEdit(lead)}
          >
            Edit
          </button>
        </div>
        <div className="portal-section-card-body">
          <div className="portal-info-grid">
            <div>
              <div className="portal-info-label">Status</div>
              <div className="portal-info-value">
                <span className={`portal-badge ${statusBadgeClass(lead.status)}`}>
                  {lead.status}
                </span>
              </div>
            </div>
            <div>
              <div className="portal-info-label">Estimated Value</div>
              <div className="portal-info-value" style={{ fontWeight: 600 }}>
                {lead.estimatedValue != null
                  ? fmtCurrency(lead.estimatedValue)
                  : '—'}
              </div>
            </div>
            <div>
              <div className="portal-info-label">Customer</div>
              <div className="portal-info-value">
                {lead.customerName ?? (
                  <span style={{ color: '#9ca3af' }}>Unlinked</span>
                )}
              </div>
            </div>
            <div>
              <div className="portal-info-label">Source</div>
              <div className="portal-info-value">{lead.source ?? '—'}</div>
            </div>
            <div>
              <div className="portal-info-label">Created</div>
              <div className="portal-info-value">{fmtDate(lead.createdAt)}</div>
            </div>
            {lead.convertedAt && (
              <div>
                <div className="portal-info-label">Converted</div>
                <div className="portal-info-value">
                  {fmtDate(lead.convertedAt)}
                </div>
              </div>
            )}
          </div>

          {/* Description */}
          {lead.description && (
            <div
              style={{
                marginTop: 16,
                paddingTop: 16,
                borderTop: '1px solid #f3f4f6',
              }}
            >
              <div
                style={{
                  fontSize: 11,
                  fontWeight: 600,
                  color: '#9ca3af',
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                  marginBottom: 8,
                }}
              >
                Description
              </div>
              <div
                style={{ fontSize: 13, color: '#374151', lineHeight: 1.6 }}
              >
                {lead.description}
              </div>
            </div>
          )}

          {/* Actions */}
          <div
            style={{
              marginTop: 20,
              paddingTop: 16,
              borderTop: '1px solid #f3f4f6',
              display: 'flex',
              gap: 10,
              flexWrap: 'wrap',
            }}
          >
            {lead.status !== 'converted' && (
              <button
                className="portal-btn portal-btn-ghost portal-btn-sm"
                onClick={handleConvert}
                disabled={converting}
                style={{
                  background: '#f0fdf4',
                  color: '#16a34a',
                  borderColor: '#bbf7d0',
                }}
              >
                {converting ? 'Converting…' : '⚡ Convert to Quote'}
              </button>
            )}
            <button
              className="portal-btn portal-btn-ghost portal-btn-sm"
              onClick={handleDelete}
              disabled={deleting}
              style={{ color: '#dc2626', borderColor: '#fecaca' }}
            >
              {deleting ? 'Deleting…' : 'Delete Lead'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

type ModalMode = 'none' | 'create' | 'edit';
type FilterMode = 'all' | 'new' | 'qualified' | 'converted' | 'lost';

const FILTER_TABS: { key: FilterMode; label: string }[] = [
  { key: 'all',       label: 'All' },
  { key: 'new',       label: 'New' },
  { key: 'qualified', label: 'Qualified' },
  { key: 'converted', label: 'Converted' },
  { key: 'lost',      label: 'Lost' },
];

const LeadsPage: React.FC = () => {
  // Hash routing
  const [hash, setHash] = useState(() => window.location.hash);
  useEffect(() => {
    const handler = () => setHash(window.location.hash);
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const detailId = parseLeadHash(hash);

  // Data
  const [leads, setLeads]         = useState<Lead[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);

  // List controls
  const [filter, setFilter] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');

  // Modal
  const [modal, setModal]       = useState<ModalMode>('none');
  const [editTarget, setEditTarget] = useState<Lead | null>(null);

  // ── Data loading ──────────────────────────────────────────────

  const loadLeads = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<Lead[]>('/leads');
      setLeads(data);
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
    loadLeads();
    loadCustomers();
  }, [loadLeads, loadCustomers]);

  // ── Actions ───────────────────────────────────────────────────

  const openCreate = () => { setEditTarget(null); setModal('create'); };
  const openEdit   = (l: Lead) => { setEditTarget(l); setModal('edit'); };
  const closeModal = () => { setModal('none'); setEditTarget(null); };

  const handleModalSave = () => { loadLeads(); closeModal(); };

  const handleConvert = async (id: number) => {
    try {
      await apiFetch(`/leads/${id}/convert`, {
        method: 'POST',
        body: JSON.stringify({}),
      });
      await loadLeads();
      window.location.hash = '#leads';
    } catch (e: any) {
      alert(`Conversion failed: ${e.message}`);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await apiFetch(`/leads/${id}`, { method: 'DELETE' });
      await loadLeads();
      window.location.hash = '#leads';
    } catch (e: any) {
      alert(`Delete failed: ${e.message}`);
    }
  };

  // ── KPIs ──────────────────────────────────────────────────────

  const kpiNew       = leads.filter(l => l.status === 'new').length;
  const kpiQualified = leads.filter(l => l.status === 'qualified').length;
  const kpiConverted = leads.filter(l => l.status === 'converted').length;
  const totalValue   = leads
    .filter(l => l.status !== 'lost' && l.estimatedValue != null)
    .reduce((sum, l) => sum + (l.estimatedValue ?? 0), 0);

  // ── Filtering ─────────────────────────────────────────────────

  const filtered = leads.filter(l => {
    if (filter !== 'all' && l.status !== filter) return false;
    if (search) {
      const q = search.toLowerCase();
      return (
        l.subject.toLowerCase().includes(q) ||
        (l.customerName ?? '').toLowerCase().includes(q) ||
        (l.source ?? '').toLowerCase().includes(q)
      );
    }
    return true;
  });

  // ── Detail view ───────────────────────────────────────────────

  if (detailId !== null) {
    const lead = leads.find(l => l.id === detailId);

    if (loading) {
      return (
        <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>
          Loading…
        </div>
      );
    }

    if (!lead) {
      return (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Lead not found</div>
            <div className="portal-empty-subtitle">
              This lead may have been deleted.
            </div>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={() => { window.location.hash = '#leads'; }}
            >
              ← Back to Leads
            </button>
          </div>
        </div>
      );
    }

    return (
      <>
        <LeadDetail
          lead={lead}
          onBack={() => { window.location.hash = '#leads'; }}
          onEdit={openEdit}
          onConvert={handleConvert}
          onDelete={handleDelete}
        />
        {modal !== 'none' && (
          <LeadModal
            lead={editTarget}
            customers={customers}
            onSave={handleModalSave}
            onClose={closeModal}
          />
        )}
      </>
    );
  }

  // ── List view ─────────────────────────────────────────────────

  return (
    <div>
      {/* Page header */}
      <div className="portal-page-header">
        <div>
          <div className="portal-page-title">Leads</div>
          <div className="portal-page-subtitle">
            Track sales opportunities and new business
          </div>
        </div>
        <button className="portal-btn portal-btn-primary" onClick={openCreate}>
          + New Lead
        </button>
      </div>

      {/* KPI strip */}
      <div className="portal-kpi-strip">
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Pipeline Value</div>
          <div className="portal-kpi-value" style={{ fontSize: 22 }}>
            {fmtCurrency(totalValue)}
          </div>
          <div className="portal-kpi-sub">excl. lost leads</div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">New</div>
          <div className="portal-kpi-value" style={{ color: '#d97706' }}>
            {kpiNew}
          </div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Qualified</div>
          <div className="portal-kpi-value" style={{ color: '#2563eb' }}>
            {kpiQualified}
          </div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Converted</div>
          <div className="portal-kpi-value" style={{ color: '#16a34a' }}>
            {kpiConverted}
          </div>
        </div>
      </div>

      {/* Filter row */}
      <div className="portal-filters-row">
        {FILTER_TABS.map(t => (
          <button
            key={t.key}
            className={`portal-filter-tab${filter === t.key ? ' active' : ''}`}
            onClick={() => setFilter(t.key)}
          >
            {t.label}
          </button>
        ))}
        <div className="portal-filter-spacer" />
        <input
          type="search"
          placeholder="Search leads…"
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

      {/* Lead table */}
      {loading ? (
        <div className="portal-card">
          {[1, 2, 3, 4].map(i => (
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
                  style={{ height: 14, width: '45%', marginBottom: 6, borderRadius: 3 }}
                />
                <div
                  className="portal-skeleton"
                  style={{ height: 12, width: '28%', borderRadius: 3 }}
                />
              </div>
              <div
                className="portal-skeleton"
                style={{ height: 22, width: 70, borderRadius: 20 }}
              />
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Failed to load leads</div>
            <div className="portal-empty-subtitle">{error}</div>
            <button className="portal-btn portal-btn-ghost" onClick={loadLeads}>
              Retry
            </button>
          </div>
        </div>
      ) : filtered.length === 0 ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">
              {search ? 'No matching leads' : 'No leads yet'}
            </div>
            <div className="portal-empty-subtitle">
              {search
                ? 'Try a different search term.'
                : 'Create your first lead to start tracking opportunities.'}
            </div>
            {!search && (
              <button
                className="portal-btn portal-btn-primary"
                onClick={openCreate}
              >
                + New Lead
              </button>
            )}
          </div>
        </div>
      ) : (
        <div className="portal-card">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Customer</th>
                <th>Value</th>
                <th>Source</th>
                <th>Status</th>
                <th>Created</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map(lead => (
                <tr
                  key={lead.id}
                  onClick={() => {
                    window.location.hash = `#leads/${lead.id}`;
                  }}
                  style={{ cursor: 'pointer' }}
                >
                  <td>
                    <div style={{ fontWeight: 600 }}>
                      {lead.subject}
                      {lead.opportunityId && (
                        <span
                          title="Linked to a pipeline opportunity"
                          onClick={(e) => { e.stopPropagation(); window.location.hash = '#pipeline'; }}
                          style={{ display: 'inline-block', marginLeft: 6, padding: '1px 6px', fontSize: 11, borderRadius: 4, background: '#ede9fe', color: '#6d28d9', cursor: 'pointer', verticalAlign: 'middle', lineHeight: 1.5 }}
                        >
                          Pipeline ↗
                        </span>
                      )}
                    </div>
                    {lead.convertedAt && (
                      <div style={{ fontSize: 11, color: '#16a34a' }}>
                        Converted {fmtDate(lead.convertedAt)}
                      </div>
                    )}
                  </td>
                  <td
                    style={{
                      color: lead.customerName ? '#111827' : '#9ca3af',
                    }}
                  >
                    {lead.customerName ?? 'Unlinked'}
                  </td>
                  <td style={{ fontWeight: 600 }}>
                    {lead.estimatedValue != null
                      ? fmtCurrency(lead.estimatedValue)
                      : '—'}
                  </td>
                  <td style={{ color: '#6b7280' }}>{lead.source ?? '—'}</td>
                  <td>
                    <span
                      className={`portal-badge ${statusBadgeClass(lead.status)}`}
                    >
                      {lead.status}
                    </span>
                  </td>
                  <td style={{ color: '#6b7280', whiteSpace: 'nowrap' }}>
                    {fmtDate(lead.createdAt)}
                  </td>
                  <td
                    style={{ textAlign: 'right' }}
                    onClick={e => e.stopPropagation()}
                  >
                    <button
                      className="portal-btn portal-btn-ghost portal-btn-sm"
                      onClick={() => {
                        window.location.hash = `#leads/${lead.id}`;
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

      {/* Create / Edit modal */}
      {modal !== 'none' && (
        <LeadModal
          lead={editTarget}
          customers={customers}
          onSave={handleModalSave}
          onClose={closeModal}
        />
      )}
    </div>
  );
};

export default LeadsPage;
