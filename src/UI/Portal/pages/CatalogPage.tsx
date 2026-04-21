/**
 * CatalogPage — Portal-native catalog management
 *
 * Day 4 implementation. Two tabs: Services (catalog-items) and Products (catalog-products).
 * pet_sales → read-only view only.
 * pet_hr / pet_manager → full CRUD.
 */
import React, { useState, useEffect, useCallback } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// ── Types ─────────────────────────────────────────────────────────────────────

interface CatalogItem {
  id: number;
  sku: string | null;
  name: string;
  type?: string;
  description: string | null;
  category: string | null;
  unit_price: number;
  unit_cost: number;
}

interface CatalogProduct {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  category: string | null;
  unit_price: number;
  unit_cost: number;
  status: string;
  created_at: string;
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

function fmtCurrency(n: number): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(n);
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

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
        {label}
      </label>
      {children}
    </div>
  );
}

function SkeletonRows({ rows }: { rows: number }) {
  return (
    <>
      {Array.from({ length: rows }).map((_, i) => (
        <tr key={i}>
          <td colSpan={6}>
            <div className="portal-skeleton" style={{ height: 14, borderRadius: 3, margin: '10px 0' }} />
          </td>
        </tr>
      ))}
    </>
  );
}

// ── Services tab ─────────────────────────────────────────────────────────────

interface ServicePanelProps {
  item: CatalogItem | null;
  onSave: (data: Partial<CatalogItem>) => Promise<void>;
  onClose: () => void;
  saving: boolean;
  error: string | null;
}

function ServiceForm({ item, onSave, onClose, saving, error }: ServicePanelProps) {
  const [form, setForm] = useState({
    name: item?.name ?? '',
    sku: item?.sku ?? '',
    type: item?.type ?? 'service',
    description: item?.description ?? '',
    category: item?.category ?? '',
    unit_price: item?.unit_price ?? 0,
    unit_cost: item?.unit_cost ?? 0,
  });

  useEffect(() => {
    setForm({
      name: item?.name ?? '',
      sku: item?.sku ?? '',
      type: item?.type ?? 'service',
      description: item?.description ?? '',
      category: item?.category ?? '',
      unit_price: item?.unit_price ?? 0,
      unit_cost: item?.unit_cost ?? 0,
    });
  }, [item]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && (
        <div className="portal-banner portal-banner-amber">
          <div className="portal-banner-text">{error}</div>
        </div>
      )}
      <FormField label="Name *">
        <input
          type="text"
          value={form.name}
          onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
          placeholder="e.g. Managed Desktop Support"
          style={inputStyle}
        />
      </FormField>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <FormField label="SKU">
          <input
            type="text"
            value={form.sku}
            onChange={e => setForm(f => ({ ...f, sku: e.target.value }))}
            placeholder="SVC-001"
            style={inputStyle}
          />
        </FormField>
        <FormField label="Type">
          <select
            value={form.type}
            onChange={e => setForm(f => ({ ...f, type: e.target.value }))}
            style={inputStyle}
          >
            <option value="service">Service</option>
            <option value="product">Product</option>
            <option value="labour">Labour</option>
            <option value="expense">Expense</option>
          </select>
        </FormField>
      </div>
      <FormField label="Category">
        <input
          type="text"
          value={form.category}
          onChange={e => setForm(f => ({ ...f, category: e.target.value }))}
          placeholder="e.g. Managed Services"
          style={inputStyle}
        />
      </FormField>
      <FormField label="Description">
        <textarea
          value={form.description}
          onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
          placeholder="Brief description shown on quotes…"
          rows={3}
          style={{ ...inputStyle, resize: 'vertical' }}
        />
      </FormField>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <FormField label="Unit Price (£)">
          <input
            type="number"
            step="0.01"
            min="0"
            value={form.unit_price}
            onChange={e => setForm(f => ({ ...f, unit_price: parseFloat(e.target.value) || 0 }))}
            style={inputStyle}
          />
        </FormField>
        <FormField label="Unit Cost (£)">
          <input
            type="number"
            step="0.01"
            min="0"
            value={form.unit_cost}
            onChange={e => setForm(f => ({ ...f, unit_cost: parseFloat(e.target.value) || 0 }))}
            style={inputStyle}
          />
        </FormField>
      </div>
      <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
        <button
          className="portal-btn portal-btn-primary"
          disabled={saving}
          onClick={() => onSave(form)}
          style={{ flex: 1, justifyContent: 'center' }}
        >
          {saving ? 'Saving…' : item ? 'Save Changes' : 'Create Item'}
        </button>
        <button className="portal-btn portal-btn-ghost" onClick={onClose} disabled={saving}>
          Cancel
        </button>
      </div>
    </div>
  );
}

function ServicesTab({ canEdit }: { canEdit: boolean }) {
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');

  const [panel, setPanel] = useState<'none' | 'create' | 'edit'>('none');
  const [selected, setSelected] = useState<CatalogItem | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<CatalogItem[]>('/catalog-items');
      setItems(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => { setSelected(null); setSaveError(null); setPanel('create'); };
  const openEdit = (item: CatalogItem) => { setSelected(item); setSaveError(null); setPanel('edit'); };
  const close = () => { setPanel('none'); setSelected(null); };

  const handleSave = async (data: Partial<CatalogItem>) => {
    try {
      setSaving(true);
      setSaveError(null);
      if (panel === 'create') {
        await apiFetch('/catalog-items', { method: 'POST', body: JSON.stringify(data) });
      } else if (selected) {
        await apiFetch(`/catalog-items/${selected.id}`, { method: 'PUT', body: JSON.stringify(data) });
      }
      await load();
      close();
    } catch (e: any) {
      setSaveError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (item: CatalogItem) => {
    if (!confirm(`Delete "${item.name}"? This cannot be undone.`)) return;
    try {
      await apiFetch(`/catalog-items/${item.id}`, { method: 'DELETE' });
      await load();
      if (panel !== 'none') close();
    } catch (e: any) {
      alert(`Delete failed: ${e.message}`);
    }
  };

  const filtered = items.filter(it => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (
      it.name.toLowerCase().includes(q) ||
      (it.sku ?? '').toLowerCase().includes(q) ||
      (it.category ?? '').toLowerCase().includes(q)
    );
  });

  const margin = (it: CatalogItem) =>
    it.unit_price > 0 ? Math.round(((it.unit_price - it.unit_cost) / it.unit_price) * 100) : 0;

  return (
    <div style={{ display: 'flex', gap: 24, alignItems: 'flex-start' }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div className="portal-filters-row">
          <input
            type="search"
            placeholder="Search services…"
            value={search}
            onChange={e => setSearch(e.target.value)}
            style={{ padding: '6px 12px', borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 13, outline: 'none', width: 220, fontFamily: 'inherit' }}
          />
          <div className="portal-filter-spacer" />
          {canEdit && (
            <button className="portal-btn portal-btn-primary" onClick={openCreate}>
              + New Item
            </button>
          )}
        </div>
        <div className="portal-card">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Margin</th>
                {canEdit && <th />}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <SkeletonRows rows={5} />
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={6}>
                    <div className="portal-empty">
                      <div className="portal-empty-title">
                        {search ? 'No matching items' : 'No service items yet'}
                      </div>
                      {canEdit && !search && (
                        <button className="portal-btn portal-btn-primary" onClick={openCreate}>
                          + New Item
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ) : (
                filtered.map(it => (
                  <tr key={it.id} onClick={canEdit ? () => openEdit(it) : undefined} style={{ cursor: canEdit ? 'pointer' : 'default' }}>
                    <td>
                      <div style={{ fontWeight: 600 }}>{it.name}</div>
                      {it.type && (
                        <div style={{ fontSize: 11.5, color: '#9ca3af', textTransform: 'capitalize' }}>{it.type}</div>
                      )}
                    </td>
                    <td style={{ color: '#6b7280', fontFamily: 'monospace', fontSize: 12 }}>
                      {it.sku ?? '—'}
                    </td>
                    <td style={{ color: '#6b7280' }}>{it.category ?? '—'}</td>
                    <td style={{ fontWeight: 600 }}>{fmtCurrency(it.unit_price)}</td>
                    <td>
                      <span style={{
                        display: 'inline-block',
                        padding: '2px 8px',
                        borderRadius: 10,
                        fontSize: 11.5,
                        fontWeight: 600,
                        background: margin(it) >= 50 ? '#f0fdf4' : margin(it) >= 20 ? '#fffbeb' : '#fff1f2',
                        color: margin(it) >= 50 ? '#16a34a' : margin(it) >= 20 ? '#d97706' : '#e11d48',
                      }}>
                        {margin(it)}%
                      </span>
                    </td>
                    {canEdit && (
                      <td style={{ textAlign: 'right' }} onClick={e => e.stopPropagation()}>
                        <button
                          className="portal-btn portal-btn-ghost portal-btn-sm"
                          style={{ color: '#dc2626', borderColor: '#fecaca' }}
                          onClick={() => handleDelete(it)}
                        >
                          Delete
                        </button>
                      </td>
                    )}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {panel !== 'none' && canEdit && (
        <SidePanel
          title={panel === 'create' ? 'New Service Item' : `Edit: ${selected?.name}`}
          onClose={close}
        >
          <ServiceForm
            item={selected}
            onSave={handleSave}
            onClose={close}
            saving={saving}
            error={saveError}
          />
        </SidePanel>
      )}
    </div>
  );
}

// ── Products tab ──────────────────────────────────────────────────────────────

function ProductsTab({ canEdit }: { canEdit: boolean }) {
  const [products, setProducts] = useState<CatalogProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');

  const [panel, setPanel] = useState<'none' | 'create' | 'edit'>('none');
  const [selected, setSelected] = useState<CatalogProduct | null>(null);
  const [form, setForm] = useState({ sku: '', name: '', description: '', category: '', unit_price: 0, unit_cost: 0 });
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<CatalogProduct[]>('/catalog-products');
      setProducts(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setSelected(null);
    setForm({ sku: '', name: '', description: '', category: '', unit_price: 0, unit_cost: 0 });
    setSaveError(null);
    setPanel('create');
  };

  const openEdit = (p: CatalogProduct) => {
    setSelected(p);
    setForm({ sku: p.sku, name: p.name, description: p.description ?? '', category: p.category ?? '', unit_price: p.unit_price, unit_cost: p.unit_cost });
    setSaveError(null);
    setPanel('edit');
  };

  const close = () => { setPanel('none'); setSelected(null); };

  const handleSave = async () => {
    if (!form.name.trim()) { setSaveError('Name is required.'); return; }
    try {
      setSaving(true);
      setSaveError(null);
      if (panel === 'create') {
        await apiFetch('/catalog-products', { method: 'POST', body: JSON.stringify(form) });
      } else if (selected) {
        await apiFetch(`/catalog-products/${selected.id}`, { method: 'PUT', body: JSON.stringify(form) });
      }
      await load();
      close();
    } catch (e: any) {
      setSaveError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const handleArchive = async (p: CatalogProduct) => {
    if (!confirm(`Archive "${p.name}"?`)) return;
    try {
      await apiFetch(`/catalog-products/${p.id}/archive`, { method: 'POST' });
      await load();
      if (panel !== 'none') close();
    } catch (e: any) {
      alert(`Archive failed: ${e.message}`);
    }
  };

  const filtered = products.filter(p => {
    if (!search) return true;
    const q = search.toLowerCase();
    return p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || (p.category ?? '').toLowerCase().includes(q);
  });

  return (
    <div style={{ display: 'flex', gap: 24, alignItems: 'flex-start' }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div className="portal-filters-row">
          <input
            type="search"
            placeholder="Search products…"
            value={search}
            onChange={e => setSearch(e.target.value)}
            style={{ padding: '6px 12px', borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 13, outline: 'none', width: 220, fontFamily: 'inherit' }}
          />
          <div className="portal-filter-spacer" />
          {canEdit && (
            <button className="portal-btn portal-btn-primary" onClick={openCreate}>
              + New Product
            </button>
          )}
        </div>

        {error ? (
          <div className="portal-card">
            <div className="portal-empty">
              <div className="portal-empty-title">Failed to load products</div>
              <div className="portal-empty-subtitle">{error}</div>
              <button className="portal-btn portal-btn-ghost" onClick={load}>Retry</button>
            </div>
          </div>
        ) : (
          <div className="portal-card">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Cost</th>
                  <th>Status</th>
                  {canEdit && <th />}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <SkeletonRows rows={5} />
                ) : filtered.length === 0 ? (
                  <tr>
                    <td colSpan={7}>
                      <div className="portal-empty">
                        <div className="portal-empty-title">
                          {search ? 'No matching products' : 'No products yet'}
                        </div>
                        {canEdit && !search && (
                          <button className="portal-btn portal-btn-primary" onClick={openCreate}>
                            + New Product
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ) : (
                  filtered.map(p => (
                    <tr key={p.id} onClick={canEdit ? () => openEdit(p) : undefined} style={{ cursor: canEdit ? 'pointer' : 'default' }}>
                      <td>
                        <div style={{ fontWeight: 600 }}>{p.name}</div>
                        {p.description && (
                          <div style={{ fontSize: 11.5, color: '#9ca3af' }}>{p.description.slice(0, 60)}{p.description.length > 60 ? '…' : ''}</div>
                        )}
                      </td>
                      <td style={{ color: '#6b7280', fontFamily: 'monospace', fontSize: 12 }}>{p.sku || '—'}</td>
                      <td style={{ color: '#6b7280' }}>{p.category ?? '—'}</td>
                      <td style={{ fontWeight: 600 }}>{fmtCurrency(p.unit_price)}</td>
                      <td style={{ color: '#6b7280' }}>{fmtCurrency(p.unit_cost)}</td>
                      <td>
                        <span className={`portal-badge portal-badge-${p.status === 'active' ? 'active' : 'archived'}`}>
                          {p.status}
                        </span>
                      </td>
                      {canEdit && (
                        <td style={{ textAlign: 'right' }} onClick={e => e.stopPropagation()}>
                          <button
                            className="portal-btn portal-btn-ghost portal-btn-sm"
                            style={{ color: '#dc2626', borderColor: '#fecaca' }}
                            onClick={() => handleArchive(p)}
                          >
                            Archive
                          </button>
                        </td>
                      )}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {panel !== 'none' && canEdit && (
        <SidePanel
          title={panel === 'create' ? 'New Product' : `Edit: ${selected?.name}`}
          onClose={close}
        >
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {saveError && (
              <div className="portal-banner portal-banner-amber">
                <div className="portal-banner-text">{saveError}</div>
              </div>
            )}
            <FormField label="Name *">
              <input type="text" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="e.g. Dell Latitude 5540" style={inputStyle} />
            </FormField>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="SKU">
                <input type="text" value={form.sku} onChange={e => setForm(f => ({ ...f, sku: e.target.value }))} placeholder="PRD-001" style={inputStyle} />
              </FormField>
              <FormField label="Category">
                <input type="text" value={form.category} onChange={e => setForm(f => ({ ...f, category: e.target.value }))} placeholder="Hardware" style={inputStyle} />
              </FormField>
            </div>
            <FormField label="Description">
              <textarea value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} rows={2} style={{ ...inputStyle, resize: 'vertical' }} />
            </FormField>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="Unit Price (£)">
                <input type="number" step="0.01" min="0" value={form.unit_price} onChange={e => setForm(f => ({ ...f, unit_price: parseFloat(e.target.value) || 0 }))} style={inputStyle} />
              </FormField>
              <FormField label="Unit Cost (£)">
                <input type="number" step="0.01" min="0" value={form.unit_cost} onChange={e => setForm(f => ({ ...f, unit_cost: parseFloat(e.target.value) || 0 }))} style={inputStyle} />
              </FormField>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
              <button className="portal-btn portal-btn-primary" disabled={saving} onClick={handleSave} style={{ flex: 1, justifyContent: 'center' }}>
                {saving ? 'Saving…' : panel === 'create' ? 'Create Product' : 'Save Changes'}
              </button>
              <button className="portal-btn portal-btn-ghost" onClick={close} disabled={saving}>Cancel</button>
            </div>
            {panel === 'edit' && selected && (
              <button
                className="portal-btn portal-btn-ghost"
                onClick={() => handleArchive(selected)}
                style={{ color: '#dc2626', borderColor: '#fecaca', width: '100%', justifyContent: 'center' }}
              >
                Archive Product
              </button>
            )}
          </div>
        </SidePanel>
      )}
    </div>
  );
}

// ── Shared SidePanel wrapper ──────────────────────────────────────────────────

function SidePanel({
  title,
  onClose,
  children,
}: {
  title: string;
  onClose: () => void;
  children: React.ReactNode;
}) {
  return (
    <div style={{
      width: 380,
      flexShrink: 0,
      background: '#fff',
      border: '1px solid #e5e7eb',
      borderRadius: 12,
      overflow: 'hidden',
      position: 'sticky',
      top: 24,
    }}>
      <div style={{ padding: '14px 20px', borderBottom: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ flex: 1, fontWeight: 700, fontSize: 15, minWidth: 0 }}>{title}</div>
        <button
          onClick={onClose}
          style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#6b7280', fontSize: 20, lineHeight: 1, padding: '2px 4px', borderRadius: 4 }}
          aria-label="Close"
        >
          ×
        </button>
      </div>
      <div style={{ padding: 20, overflowY: 'auto', maxHeight: 'calc(100vh - 220px)' }}>
        {children}
      </div>
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

type CatalogTab = 'services' | 'products';

const CatalogPage: React.FC = () => {
  const user = usePortalUser();
  const canEdit = user.isHr || user.isManager || user.isAdmin;
  const [tab, setTab] = useState<CatalogTab>('services');

  return (
    <div>
      {/* Page header */}
      <div className="portal-page-header">
        <div>
          <div className="portal-page-title">Catalog</div>
          <div className="portal-page-subtitle">
            Services and products available for quoting
            {!canEdit && (
              <span style={{ marginLeft: 8, fontSize: 11, color: '#9ca3af' }}>
                (read-only — contact your manager to make changes)
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Tab bar */}
      <div className="portal-filters-row" style={{ marginBottom: 20 }}>
        <button
          className={`portal-filter-tab${tab === 'services' ? ' active' : ''}`}
          onClick={() => setTab('services')}
        >
          Services
        </button>
        <button
          className={`portal-filter-tab${tab === 'products' ? ' active' : ''}`}
          onClick={() => setTab('products')}
        >
          Products
        </button>
      </div>

      {tab === 'services' && <ServicesTab canEdit={canEdit} />}
      {tab === 'products' && <ProductsTab canEdit={canEdit} />}
    </div>
  );
};

export default CatalogPage;
