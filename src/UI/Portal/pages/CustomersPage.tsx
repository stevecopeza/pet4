/**
 * CustomersPage — Portal-native customer management
 *
 * Refactored to the full-page detail pattern (Phase 2).
 *   - List view  : table, KPI strip, filter tabs, search, Create/Edit modals
 *   - Detail view: #customers/:id hash route, tabs (Overview | Contacts)
 */
import React, { useState, useEffect, useCallback } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Customer {
  id: number;
  name: string;
  legalName: string | null;
  contactEmail: string;
  status: string;
  logoUrl: string | null;
  brandColor: string | null;
  malleableData: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

interface ContactAffiliation {
  customerId: number;
  siteId: number | null;
  role: string | null;
  isPrimary: boolean;
}

interface Contact {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  phone: string | null;
  affiliations: ContactAffiliation[];
  createdAt: string;
  archivedAt: string | null;
}

interface FormData {
  name: string;
  legalName: string;
  contactEmail: string;
  status: string;
}

// ── Hash routing ──────────────────────────────────────────────────────────────

function parseCustomerHash(hash: string): number | null {
  const m = hash.match(/^#customers\/(\d+)$/);
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
    throw new Error((body as any).message ?? `API error ${res.status}`);
  }
  return res.json() as Promise<T>;
}

// ── Shared helpers ────────────────────────────────────────────────────────────

function CustomerAvatar({ name, color }: { name: string; color?: string | null }) {
  const letters = name
    .trim()
    .split(/\s+/)
    .map(w => w[0] ?? '')
    .join('')
    .slice(0, 2)
    .toUpperCase();
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: 34,
        height: 34,
        borderRadius: 8,
        background: color ?? '#6366f1',
        color: '#fff',
        fontWeight: 700,
        fontSize: 12,
        flexShrink: 0,
        fontFamily: 'inherit',
      }}
    >
      {letters}
    </span>
  );
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

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

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
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

function SkeletonTable({ rows }: { rows: number }) {
  return (
    <div className="portal-card">
      {Array.from({ length: rows }).map((_, i) => (
        <div
          key={i}
          style={{
            padding: '14px 16px',
            borderBottom: i < rows - 1 ? '1px solid #e5e7eb' : 'none',
            display: 'flex',
            gap: 14,
            alignItems: 'center',
          }}
        >
          <div
            className="portal-skeleton"
            style={{ width: 34, height: 34, borderRadius: 8, flexShrink: 0 }}
          />
          <div style={{ flex: 1 }}>
            <div
              className="portal-skeleton"
              style={{ height: 14, width: '38%', marginBottom: 6, borderRadius: 3 }}
            />
            <div
              className="portal-skeleton"
              style={{ height: 12, width: '22%', borderRadius: 3 }}
            />
          </div>
          <div
            className="portal-skeleton"
            style={{ height: 22, width: 60, borderRadius: 20 }}
          />
        </div>
      ))}
    </div>
  );
}

// ── Customer Modal (Create / Edit) ────────────────────────────────────────────

function CustomerModal({
  mode,
  customer,
  onSave,
  onClose,
}: {
  mode: 'create' | 'edit';
  customer: Customer | null;
  onSave: () => Promise<void>;
  onClose: () => void;
}) {
  const [form, setForm] = useState<FormData>(
    customer
      ? {
          name: customer.name,
          legalName: customer.legalName ?? '',
          contactEmail: customer.contactEmail,
          status: customer.status,
        }
      : { name: '', legalName: '', contactEmail: '', status: 'active' }
  );
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const handleSave = async () => {
    if (!form.name.trim() || !form.contactEmail.trim()) {
      setFormError('Name and contact email are required.');
      return;
    }
    try {
      setSaving(true);
      setFormError(null);
      if (mode === 'create') {
        await apiFetch('/customers', { method: 'POST', body: JSON.stringify(form) });
      } else if (customer) {
        await apiFetch(`/customers/${customer.id}`, {
          method: 'PUT',
          body: JSON.stringify(form),
        });
      }
      await onSave();
    } catch (e: any) {
      setFormError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const overlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) onClose();
  };

  return (
    <div className="portal-modal-overlay" onClick={overlayClick}>
      <div className="portal-modal">
        <div className="portal-modal-header">
          <div className="portal-modal-title">
            {mode === 'create' ? 'New Customer' : 'Edit Customer'}
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
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {formError && (
              <div className="portal-banner portal-banner-amber">
                <div className="portal-banner-text">{formError}</div>
              </div>
            )}

            <FormField label="Customer Name *">
              <input
                type="text"
                value={form.name}
                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                placeholder="Acme Corporation"
                style={inputStyle}
              />
            </FormField>

            <FormField label="Legal Name">
              <input
                type="text"
                value={form.legalName}
                onChange={e => setForm(f => ({ ...f, legalName: e.target.value }))}
                placeholder="Acme Corp Ltd."
                style={inputStyle}
              />
            </FormField>

            <FormField label="Contact Email *">
              <input
                type="email"
                value={form.contactEmail}
                onChange={e => setForm(f => ({ ...f, contactEmail: e.target.value }))}
                placeholder="hello@acme.com"
                style={inputStyle}
              />
            </FormField>

            <FormField label="Status">
              <select
                value={form.status}
                onChange={e => setForm(f => ({ ...f, status: e.target.value }))}
                style={inputStyle}
              >
                <option value="active">Active</option>
                <option value="archived">Archived</option>
              </select>
            </FormField>

            <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
              <button
                className="portal-btn portal-btn-primary"
                onClick={handleSave}
                disabled={saving}
                style={{ flex: 1, justifyContent: 'center' }}
              >
                {saving
                  ? 'Saving…'
                  : mode === 'create'
                  ? 'Create Customer'
                  : 'Save Changes'}
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

// ── Customer Detail (full-page) ───────────────────────────────────────────────

type DetailTab = 'overview' | 'contacts';

function CustomerDetail({
  customer,
  onBack,
  onEdit,
  onArchive,
}: {
  customer: Customer;
  onBack: () => void;
  onEdit: (c: Customer) => void;
  onArchive: (c: Customer) => void;
}) {
  const [tab, setTab] = useState<DetailTab>('overview');
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [contactsLoading, setContactsLoading] = useState(false);
  const [contactsLoaded, setContactsLoaded] = useState(false);

  const loadContacts = useCallback(async () => {
    try {
      setContactsLoading(true);
      const all = await apiFetch<Contact[]>('/contacts');
      setContacts(all.filter(c => c.affiliations.some(a => a.customerId === customer.id)));
      setContactsLoaded(true);
    } catch {
      setContacts([]);
      setContactsLoaded(true);
    } finally {
      setContactsLoading(false);
    }
  }, [customer.id]);

  useEffect(() => {
    if (tab === 'contacts' && !contactsLoaded) {
      loadContacts();
    }
  }, [tab, contactsLoaded, loadContacts]);

  const tabLabels: Record<DetailTab, string> = {
    overview: 'Overview',
    contacts: 'Contacts',
  };

  return (
    <div>
      {/* Detail header */}
      <div className="portal-detail-header">
        <button className="portal-detail-back" onClick={onBack}>
          ← Customers
        </button>
        <div className="portal-detail-identity">
          <CustomerAvatar name={customer.name} color={customer.brandColor} />
          <div>
            <div className="portal-detail-name">{customer.name}</div>
            {customer.legalName && (
              <div className="portal-detail-meta">{customer.legalName}</div>
            )}
          </div>
          <span
            className={`portal-badge portal-badge-${
              customer.status === 'active' ? 'active' : 'archived'
            }`}
            style={{ marginLeft: 4 }}
          >
            {customer.status}
          </span>
        </div>
        <button
          className="portal-btn portal-btn-ghost portal-btn-sm"
          onClick={() => onEdit(customer)}
        >
          Edit
        </button>
      </div>

      {/* Tab bar */}
      <div className="portal-tab-bar">
        {(Object.keys(tabLabels) as DetailTab[]).map(t => (
          <button
            key={t}
            className={`portal-tab${tab === t ? ' active' : ''}`}
            onClick={() => setTab(t)}
          >
            {tabLabels[t]}
          </button>
        ))}
      </div>

      {/* ── Overview tab ── */}
      {tab === 'overview' && (
        <div className="portal-section-card">
          <div className="portal-section-card-header">
            <div className="portal-section-card-title">Customer Information</div>
            <button
              className="portal-btn portal-btn-ghost portal-btn-sm"
              onClick={() => onEdit(customer)}
            >
              Edit
            </button>
          </div>
          <div className="portal-section-card-body">
            <div className="portal-info-grid">
              <div>
                <div className="portal-info-label">Contact Email</div>
                <div className="portal-info-value">{customer.contactEmail}</div>
              </div>
              <div>
                <div className="portal-info-label">Status</div>
                <div className="portal-info-value">
                  <span
                    className={`portal-badge portal-badge-${
                      customer.status === 'active' ? 'active' : 'archived'
                    }`}
                  >
                    {customer.status}
                  </span>
                </div>
              </div>
              <div>
                <div className="portal-info-label">Customer Since</div>
                <div className="portal-info-value">{fmtDate(customer.createdAt)}</div>
              </div>
              {customer.archivedAt && (
                <div>
                  <div className="portal-info-label">Archived</div>
                  <div className="portal-info-value">
                    {fmtDate(customer.archivedAt)}
                  </div>
                </div>
              )}
            </div>

            {customer.status === 'active' && (
              <div
                style={{
                  marginTop: 20,
                  paddingTop: 16,
                  borderTop: '1px solid #f3f4f6',
                }}
              >
                <button
                  className="portal-btn portal-btn-ghost portal-btn-sm"
                  onClick={() => onArchive(customer)}
                  style={{ color: '#dc2626', borderColor: '#fecaca' }}
                >
                  Archive Customer
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Contacts tab ── */}
      {tab === 'contacts' && (
        <div className="portal-section-card">
          <div className="portal-section-card-header">
            <div className="portal-section-card-title">Contacts</div>
          </div>
          <div className="portal-section-card-body">
            {contactsLoading ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {[1, 2].map(i => (
                  <div
                    key={i}
                    className="portal-skeleton"
                    style={{ height: 58, borderRadius: 8 }}
                  />
                ))}
              </div>
            ) : contacts.length === 0 ? (
              <div className="portal-empty" style={{ padding: '24px 0' }}>
                <div className="portal-empty-title">No contacts on record</div>
                <div className="portal-empty-subtitle">
                  Contacts will appear here once added.
                </div>
              </div>
            ) : (
              <div className="portal-data-list">
                {contacts.map(ct => {
                  const aff = ct.affiliations.find(
                    a => a.customerId === customer.id
                  );
                  return (
                    <div key={ct.id} className="portal-data-list-row">
                      <div style={{ flex: 1 }}>
                        <div
                          style={{
                            fontWeight: 600,
                            fontSize: 13,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                          }}
                        >
                          {ct.firstName} {ct.lastName}
                          {aff?.isPrimary && (
                            <span
                              style={{
                                background: '#eff6ff',
                                color: '#2563eb',
                                fontSize: 9,
                                fontWeight: 700,
                                padding: '1px 6px',
                                borderRadius: 10,
                                textTransform: 'uppercase',
                                letterSpacing: '0.4px',
                              }}
                            >
                              Primary
                            </span>
                          )}
                        </div>
                        <div
                          style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}
                        >
                          {ct.email}
                        </div>
                        {ct.phone && (
                          <div style={{ fontSize: 12, color: '#6b7280' }}>
                            {ct.phone}
                          </div>
                        )}
                        {aff?.role && (
                          <div
                            style={{
                              fontSize: 11,
                              color: '#9ca3af',
                              marginTop: 2,
                            }}
                          >
                            {aff.role}
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

type ModalMode = 'none' | 'create' | 'edit';
type FilterMode = 'all' | 'active' | 'archived';

const CustomersPage: React.FC = () => {
  // Hash routing
  const [hash, setHash] = useState(() => window.location.hash);
  useEffect(() => {
    const handler = () => setHash(window.location.hash);
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const detailId = parseCustomerHash(hash);

  // Data
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // List controls
  const [filter, setFilter] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');

  // Modal (create / edit)
  const [modal, setModal] = useState<ModalMode>('none');
  const [editTarget, setEditTarget] = useState<Customer | null>(null);

  // ── Data loading ──────────────────────────────────────────────

  const loadCustomers = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<Customer[]>('/customers');
      setCustomers(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCustomers();
  }, [loadCustomers]);

  // ── Actions ───────────────────────────────────────────────────

  const openCreate = () => {
    setEditTarget(null);
    setModal('create');
  };

  const openEdit = (c: Customer) => {
    setEditTarget(c);
    setModal('edit');
  };

  const closeModal = () => {
    setModal('none');
    setEditTarget(null);
  };

  const handleModalSave = async () => {
    await loadCustomers();
    closeModal();
  };

  const handleArchive = async (c: Customer) => {
    if (!confirm(`Archive "${c.name}"? This can be reversed later.`)) return;
    try {
      await apiFetch(`/customers/${c.id}`, { method: 'DELETE' });
      await loadCustomers();
      if (detailId === c.id) window.location.hash = '#customers';
    } catch (e: any) {
      alert(`Archive failed: ${e.message}`);
    }
  };

  // ── Filtering ─────────────────────────────────────────────────

  const filtered = customers.filter(c => {
    if (filter === 'active' && c.status !== 'active') return false;
    if (filter === 'archived' && c.status !== 'archived') return false;
    if (search) {
      const q = search.toLowerCase();
      return (
        c.name.toLowerCase().includes(q) ||
        c.contactEmail.toLowerCase().includes(q) ||
        (c.legalName ?? '').toLowerCase().includes(q)
      );
    }
    return true;
  });

  const kpiActive   = customers.filter(c => c.status === 'active').length;
  const kpiArchived = customers.filter(c => c.status === 'archived').length;

  // ── Detail view ───────────────────────────────────────────────

  if (detailId !== null) {
    const customer = customers.find(c => c.id === detailId);

    if (loading) {
      return (
        <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>
          Loading…
        </div>
      );
    }

    if (!customer) {
      return (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Customer not found</div>
            <div className="portal-empty-subtitle">
              This customer may have been archived or deleted.
            </div>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={() => {
                window.location.hash = '#customers';
              }}
            >
              ← Back to Customers
            </button>
          </div>
        </div>
      );
    }

    return (
      <>
        <CustomerDetail
          customer={customer}
          onBack={() => {
            window.location.hash = '#customers';
          }}
          onEdit={openEdit}
          onArchive={handleArchive}
        />
        {modal !== 'none' && (
          <CustomerModal
            mode={modal === 'edit' ? 'edit' : 'create'}
            customer={editTarget}
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
          <div className="portal-page-title">Customers</div>
          <div className="portal-page-subtitle">
            Manage your customer accounts and contacts
          </div>
        </div>
        <button className="portal-btn portal-btn-primary" onClick={openCreate}>
          + New Customer
        </button>
      </div>

      {/* KPI strip */}
      <div
        className="portal-kpi-strip"
        style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}
      >
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Total</div>
          <div className="portal-kpi-value">{customers.length}</div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Active</div>
          <div className="portal-kpi-value" style={{ color: '#16a34a' }}>
            {kpiActive}
          </div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Archived</div>
          <div className="portal-kpi-value" style={{ color: '#9ca3af' }}>
            {kpiArchived}
          </div>
        </div>
      </div>

      {/* Filter row */}
      <div className="portal-filters-row">
        {(['all', 'active', 'archived'] as FilterMode[]).map(f => (
          <button
            key={f}
            className={`portal-filter-tab${filter === f ? ' active' : ''}`}
            onClick={() => setFilter(f)}
          >
            {f.charAt(0).toUpperCase() + f.slice(1)}
          </button>
        ))}
        <div className="portal-filter-spacer" />
        <input
          type="search"
          placeholder="Search customers…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{
            padding: '6px 12px',
            borderRadius: 8,
            border: '1px solid #e5e7eb',
            fontSize: 13,
            outline: 'none',
            width: 220,
            fontFamily: 'inherit',
          }}
        />
      </div>

      {/* Customer table */}
      {loading ? (
        <SkeletonTable rows={5} />
      ) : error ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Failed to load customers</div>
            <div className="portal-empty-subtitle">{error}</div>
            <button
              className="portal-btn portal-btn-ghost"
              onClick={loadCustomers}
            >
              Retry
            </button>
          </div>
        </div>
      ) : filtered.length === 0 ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">
              {search ? 'No matching customers' : 'No customers yet'}
            </div>
            <div className="portal-empty-subtitle">
              {search
                ? 'Try a different search term or adjust the filter.'
                : 'Create your first customer account to get started.'}
            </div>
            {!search && (
              <button
                className="portal-btn portal-btn-primary"
                onClick={openCreate}
              >
                + New Customer
              </button>
            )}
          </div>
        </div>
      ) : (
        <div className="portal-card">
          <table>
            <thead>
              <tr>
                <th style={{ width: 50 }} />
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map(c => (
                <tr
                  key={c.id}
                  onClick={() => {
                    window.location.hash = `#customers/${c.id}`;
                  }}
                  style={{ cursor: 'pointer' }}
                >
                  <td>
                    <CustomerAvatar name={c.name} color={c.brandColor} />
                  </td>
                  <td>
                    <div style={{ fontWeight: 600 }}>{c.name}</div>
                    {c.legalName && (
                      <div style={{ fontSize: 11.5, color: '#9ca3af' }}>
                        {c.legalName}
                      </div>
                    )}
                  </td>
                  <td style={{ color: '#6b7280' }}>{c.contactEmail}</td>
                  <td>
                    <span
                      className={`portal-badge portal-badge-${
                        c.status === 'active' ? 'active' : 'archived'
                      }`}
                    >
                      {c.status}
                    </span>
                  </td>
                  <td style={{ color: '#6b7280', whiteSpace: 'nowrap' }}>
                    {fmtDate(c.createdAt)}
                  </td>
                  <td
                    style={{ textAlign: 'right' }}
                    onClick={e => e.stopPropagation()}
                  >
                    <button
                      className="portal-btn portal-btn-ghost portal-btn-sm"
                      onClick={() => openEdit(c)}
                    >
                      Edit
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
        <CustomerModal
          mode={modal === 'edit' ? 'edit' : 'create'}
          customer={editTarget}
          onSave={handleModalSave}
          onClose={closeModal}
        />
      )}
    </div>
  );
};

export default CustomersPage;
