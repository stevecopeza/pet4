/**
 * QuoteBuilderPage — Portal-native quote block editor
 *
 * Days 8-9 implementation. Full-width builder replaces portal-main scroll.
 * Supports 4 block types: OnceOffSimpleServiceBlock, HardwareBlock,
 * PriceAdjustmentBlock, TextBlock. OnceOffProjectBlock is read-only.
 *
 * Navigation: opened via #quote-builder-{id} hash. "← Back" returns to #quotes.
 */
import React, { useState, useEffect, useCallback } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface QuoteSection {
  id: number;
  quoteId: number;
  name: string;
  orderIndex: number;
  showTotalValue: boolean;
}

interface QuoteBlock {
  id: number;
  sectionId: number | null;
  type: string;
  orderIndex: number;
  payload: Record<string, any>;
  lineSellValue: number | null;
  lineCostValue: number | null;
  marginPercentage: number | null;
  priced: boolean;
}

interface Quote {
  id: number;
  customerId: number;
  title: string;
  description: string | null;
  state: string;
  version: number;
  totalValue: number;
  totalInternalCost: number;
  margin: number;
  currency: string;
  components: any[];
  sections: QuoteSection[];
  blocks: QuoteBlock[];
}

interface CatalogItem {
  id: number;
  name: string;
  type?: string;
  unit_price: number;
  unit_cost: number;
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

function fmtCurrency(n: number, currency = 'GBP'): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
}

// ── Constants ─────────────────────────────────────────────────────────────────

const BLOCK_TYPES = [
  {
    type: 'OnceOffSimpleServiceBlock',
    label: 'Service Line',
    icon: '🔧',
    description: 'Labour, consulting, or once-off service with quantity × price',
  },
  {
    type: 'HardwareBlock',
    label: 'Hardware / Item',
    icon: '📦',
    description: 'Physical goods or catalog products with unit price',
  },
  {
    type: 'PriceAdjustmentBlock',
    label: 'Price Adjustment',
    icon: '±',
    description: 'Discount, surcharge, or cost modification',
  },
  {
    type: 'TextBlock',
    label: 'Text / Notes',
    icon: '📝',
    description: 'Free-text description, headers, or customer-facing notes',
  },
  {
    type: 'OnceOffProjectBlock',
    label: 'Project (complex)',
    icon: '🗂',
    description: 'Multi-phase project with milestones — edit in the admin panel for full control',
  },
] as const;

type KnownBlockType = (typeof BLOCK_TYPES)[number]['type'];

function blockLabel(type: string): string {
  return BLOCK_TYPES.find(b => b.type === type)?.label ?? type;
}

// ── Shared components ────────────────────────────────────────────────────────

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

// ── Block type picker ────────────────────────────────────────────────────────

function BlockTypePicker({
  onSelect,
  onCancel,
}: {
  onSelect: (type: KnownBlockType) => void;
  onCancel: () => void;
}) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 4 }}>Choose block type</div>
      {BLOCK_TYPES.map(bt => (
        <button
          key={bt.type}
          onClick={() => onSelect(bt.type as KnownBlockType)}
          style={{
            display: 'flex',
            alignItems: 'flex-start',
            gap: 12,
            padding: '12px 14px',
            background: '#f9fafb',
            border: '1px solid #e5e7eb',
            borderRadius: 8,
            cursor: 'pointer',
            textAlign: 'left',
            width: '100%',
          }}
        >
          <span style={{ fontSize: 20, flexShrink: 0 }}>{bt.icon}</span>
          <div>
            <div style={{ fontWeight: 600, fontSize: 13, color: '#111827' }}>{bt.label}</div>
            <div style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>{bt.description}</div>
          </div>
        </button>
      ))}
      <button className="portal-btn portal-btn-ghost" onClick={onCancel} style={{ marginTop: 4 }}>
        Cancel
      </button>
    </div>
  );
}

// ── Block edit forms ─────────────────────────────────────────────────────────

interface BlockFormProps {
  block: QuoteBlock;
  catalogItems: CatalogItem[];
  onSave: (payload: Record<string, any>) => Promise<void>;
  onDelete: () => Promise<void>;
  onClose: () => void;
}

function ServiceBlockForm({ block, onSave, onDelete, onClose }: BlockFormProps) {
  const p = block.payload;
  const [form, setForm] = useState({
    description: p.description ?? '',
    quantity: String(p.quantity ?? 1),
    sellValue: String(p.sellValue ?? p.unitSellPrice ?? 0),
    totalCost: String(p.totalCost ?? p.internalCost ?? 0),
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const qty = parseFloat(form.quantity) || 1;
  const sell = parseFloat(form.sellValue) || 0;
  const totalValue = qty * sell;

  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      await onSave({
        description: form.description,
        quantity: qty,
        sellValue: sell,
        totalValue,
        totalCost: parseFloat(form.totalCost) || 0,
      });
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && <div className="portal-banner portal-banner-amber"><div className="portal-banner-text">{error}</div></div>}
      <FormField label="Description">
        <input type="text" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="e.g. Senior Engineer – Network Audit" style={inputStyle} />
      </FormField>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <FormField label="Quantity">
          <input type="number" min="1" step="1" value={form.quantity} onChange={e => setForm(f => ({ ...f, quantity: e.target.value }))} style={inputStyle} />
        </FormField>
        <FormField label="Unit Sell Price (£)">
          <input type="number" min="0" step="0.01" value={form.sellValue} onChange={e => setForm(f => ({ ...f, sellValue: e.target.value }))} style={inputStyle} />
        </FormField>
      </div>
      <FormField label="Internal Cost (£) — optional">
        <input type="number" min="0" step="0.01" value={form.totalCost} onChange={e => setForm(f => ({ ...f, totalCost: e.target.value }))} style={inputStyle} />
      </FormField>
      <div style={{ background: '#f9fafb', borderRadius: 8, padding: '10px 14px', fontSize: 13, color: '#374151' }}>
        Line total: <strong>{fmtCurrency(totalValue)}</strong>
      </div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ flex: 1, justifyContent: 'center' }}>{saving ? 'Saving…' : 'Save'}</button>
        <button className="portal-btn portal-btn-ghost" onClick={onClose}>Cancel</button>
      </div>
      <button className="portal-btn portal-btn-ghost" onClick={onDelete} style={{ color: '#dc2626', borderColor: '#fecaca', justifyContent: 'center' }}>Delete Block</button>
    </div>
  );
}

function HardwareBlockForm({ block, catalogItems, onSave, onDelete, onClose }: BlockFormProps) {
  const p = block.payload;
  const [form, setForm] = useState({
    description: p.description ?? '',
    quantity: String(p.quantity ?? 1),
    unitPrice: String(p.unitSellPrice ?? p.unitPrice ?? 0),
    unitCost: String(p.unitCost ?? 0),
    catalogItemId: p.catalogItemId ? String(p.catalogItemId) : '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const qty = parseFloat(form.quantity) || 1;
  const price = parseFloat(form.unitPrice) || 0;
  const cost = parseFloat(form.unitCost) || 0;
  const totalValue = qty * price;
  const totalCost = qty * cost;

  const handleCatalogSelect = (id: string) => {
    const item = catalogItems.find(c => String(c.id) === id);
    if (item) {
      setForm(f => ({
        ...f,
        catalogItemId: id,
        description: f.description || item.name,
        unitPrice: String(item.unit_price),
        unitCost: String(item.unit_cost),
      }));
    } else {
      setForm(f => ({ ...f, catalogItemId: '' }));
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      await onSave({
        description: form.description,
        quantity: qty,
        unitSellPrice: price,
        unitCost: cost,
        sellValue: totalValue,
        internalCost: totalCost,
        catalogItemId: form.catalogItemId ? parseInt(form.catalogItemId) : null,
      });
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && <div className="portal-banner portal-banner-amber"><div className="portal-banner-text">{error}</div></div>}
      <FormField label="From Catalog (optional)">
        <select value={form.catalogItemId} onChange={e => handleCatalogSelect(e.target.value)} style={inputStyle}>
          <option value="">— Pick from catalog —</option>
          {catalogItems.map(c => (
            <option key={c.id} value={c.id}>{c.name} ({fmtCurrency(c.unit_price)})</option>
          ))}
        </select>
      </FormField>
      <FormField label="Description">
        <input type="text" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="e.g. Dell Latitude 5540" style={inputStyle} />
      </FormField>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
        <FormField label="Qty">
          <input type="number" min="1" step="1" value={form.quantity} onChange={e => setForm(f => ({ ...f, quantity: e.target.value }))} style={inputStyle} />
        </FormField>
        <FormField label="Unit Price (£)">
          <input type="number" min="0" step="0.01" value={form.unitPrice} onChange={e => setForm(f => ({ ...f, unitPrice: e.target.value }))} style={inputStyle} />
        </FormField>
        <FormField label="Unit Cost (£)">
          <input type="number" min="0" step="0.01" value={form.unitCost} onChange={e => setForm(f => ({ ...f, unitCost: e.target.value }))} style={inputStyle} />
        </FormField>
      </div>
      <div style={{ background: '#f9fafb', borderRadius: 8, padding: '10px 14px', fontSize: 13, color: '#374151' }}>
        Line total: <strong>{fmtCurrency(totalValue)}</strong>
      </div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ flex: 1, justifyContent: 'center' }}>{saving ? 'Saving…' : 'Save'}</button>
        <button className="portal-btn portal-btn-ghost" onClick={onClose}>Cancel</button>
      </div>
      <button className="portal-btn portal-btn-ghost" onClick={onDelete} style={{ color: '#dc2626', borderColor: '#fecaca', justifyContent: 'center' }}>Delete Block</button>
    </div>
  );
}

function AdjustmentBlockForm({ block, onSave, onDelete, onClose }: BlockFormProps) {
  const p = block.payload;
  const [form, setForm] = useState({
    description: p.description ?? '',
    amount: String(p.amount ?? 0),
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      await onSave({ description: form.description, amount: parseFloat(form.amount) || 0 });
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && <div className="portal-banner portal-banner-amber"><div className="portal-banner-text">{error}</div></div>}
      <FormField label="Description">
        <input type="text" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="e.g. Volume discount" style={inputStyle} />
      </FormField>
      <FormField label="Amount (£) — negative for discount">
        <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} style={inputStyle} />
      </FormField>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ flex: 1, justifyContent: 'center' }}>{saving ? 'Saving…' : 'Save'}</button>
        <button className="portal-btn portal-btn-ghost" onClick={onClose}>Cancel</button>
      </div>
      <button className="portal-btn portal-btn-ghost" onClick={onDelete} style={{ color: '#dc2626', borderColor: '#fecaca', justifyContent: 'center' }}>Delete Block</button>
    </div>
  );
}

function TextBlockForm({ block, onSave, onDelete, onClose }: BlockFormProps) {
  const [text, setText] = useState(block.payload.text ?? '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      await onSave({ text });
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {error && <div className="portal-banner portal-banner-amber"><div className="portal-banner-text">{error}</div></div>}
      <FormField label="Text / Notes">
        <textarea value={text} onChange={e => setText(e.target.value)} rows={6} placeholder="Customer-facing notes, section descriptions, or internal context…" style={{ ...inputStyle, resize: 'vertical' }} />
      </FormField>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ flex: 1, justifyContent: 'center' }}>{saving ? 'Saving…' : 'Save'}</button>
        <button className="portal-btn portal-btn-ghost" onClick={onClose}>Cancel</button>
      </div>
      <button className="portal-btn portal-btn-ghost" onClick={onDelete} style={{ color: '#dc2626', borderColor: '#fecaca', justifyContent: 'center' }}>Delete Block</button>
    </div>
  );
}

function BlockForm(props: BlockFormProps) {
  switch (props.block.type) {
    case 'OnceOffSimpleServiceBlock': return <ServiceBlockForm {...props} />;
    case 'HardwareBlock':             return <HardwareBlockForm {...props} />;
    case 'PriceAdjustmentBlock':      return <AdjustmentBlockForm {...props} />;
    case 'TextBlock':                 return <TextBlockForm {...props} />;
    default:
      return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div className="portal-banner portal-banner-amber">
            <div className="portal-banner-text">
              This block type (<strong>{props.block.type}</strong>) must be edited in the admin panel for full control.
            </div>
          </div>
          <button className="portal-btn portal-btn-ghost" onClick={props.onClose}>Close</button>
          <button className="portal-btn portal-btn-ghost" onClick={props.onDelete} style={{ color: '#dc2626', borderColor: '#fecaca', justifyContent: 'center' }}>Delete Block</button>
        </div>
      );
  }
}

// ── Block row (in the builder canvas) ────────────────────────────────────────

function BlockRow({
  block,
  isSelected,
  onClick,
}: {
  block: QuoteBlock;
  isSelected: boolean;
  onClick: () => void;
}) {
  const label = blockLabel(block.type);
  const desc =
    block.payload.description ||
    block.payload.text?.slice(0, 60) ||
    block.payload.serviceName ||
    '(no description)';

  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '12px 16px',
        cursor: 'pointer',
        background: isSelected ? '#eff6ff' : '#fff',
        borderBottom: '1px solid #f0f0f0',
        transition: 'background 0.1s',
      }}
    >
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 11, fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.4px', marginBottom: 2 }}>
          {label}
        </div>
        <div style={{ fontSize: 13, fontWeight: 500, color: '#111827', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {desc}
        </div>
      </div>
      {block.lineSellValue != null && block.priced && (
        <div style={{ fontSize: 13, fontWeight: 700, color: '#111827', flexShrink: 0 }}>
          {fmtCurrency(block.lineSellValue)}
        </div>
      )}
      {block.marginPercentage != null && block.priced && (
        <div
          style={{
            fontSize: 11,
            fontWeight: 600,
            padding: '2px 7px',
            borderRadius: 10,
            background: block.marginPercentage >= 40 ? '#f0fdf4' : block.marginPercentage >= 20 ? '#fffbeb' : '#fff1f2',
            color: block.marginPercentage >= 40 ? '#16a34a' : block.marginPercentage >= 20 ? '#d97706' : '#e11d48',
            flexShrink: 0,
          }}
        >
          {block.marginPercentage.toFixed(0)}%
        </div>
      )}
    </div>
  );
}

// ── Quote summary bar ─────────────────────────────────────────────────────────

function QuoteSummaryBar({ quote, quoteId }: { quote: Quote; quoteId: number }) {
  const margin = quote.margin ?? 0;
  const nonce  = (window as any).petSettings?.nonce ?? '';
  const pdfUrl = `${apiBase()}/quotes/${quoteId}/pdf?_wpnonce=${nonce}`;

  return (
    <div style={{
      display: 'flex',
      gap: 24,
      alignItems: 'center',
      padding: '10px 24px',
      background: '#fff',
      borderBottom: '1px solid #e5e7eb',
      fontSize: 13,
    }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontWeight: 700, fontSize: 16, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {quote.title}
        </div>
        <div style={{ fontSize: 12, color: '#6b7280' }}>v{quote.version} · {quote.state}</div>
      </div>
      <a
        href={pdfUrl}
        target="_blank"
        rel="noreferrer"
        className="portal-btn portal-btn-ghost portal-btn-sm"
      >
        🖨 PDF
      </a>
      <div style={{ textAlign: 'right', flexShrink: 0 }}>
        <div style={{ fontWeight: 700, fontSize: 20 }}>{fmtCurrency(quote.totalValue, quote.currency)}</div>
        <div style={{ fontSize: 12, color: margin >= 40 ? '#16a34a' : margin >= 20 ? '#d97706' : '#e11d48', fontWeight: 600 }}>
          {margin.toFixed(1)}% margin
        </div>
      </div>
    </div>
  );
}

// ── Add section form ──────────────────────────────────────────────────────────

function AddSectionInline({
  quoteId,
  onAdded,
  onCancel,
}: {
  quoteId: number;
  onAdded: (q: Quote) => void;
  onCancel: () => void;
}) {
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    try {
      setSaving(true);
      const q = await apiFetch<Quote>(`/quotes/${quoteId}/sections`, {
        method: 'POST',
        body: JSON.stringify({ name: name || 'New Section' }),
      });
      onAdded(q);
    } catch (e: any) {
      alert(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'flex', gap: 8, padding: '12px 16px', background: '#f9fafb', borderBottom: '1px solid #e5e7eb' }}>
      <input
        type="text"
        value={name}
        onChange={e => setName(e.target.value)}
        placeholder="Section name…"
        autoFocus
        onKeyDown={e => { if (e.key === 'Enter') handleSave(); if (e.key === 'Escape') onCancel(); }}
        style={{ ...inputStyle, flex: 1 }}
      />
      <button className="portal-btn portal-btn-primary portal-btn-sm" onClick={handleSave} disabled={saving}>
        {saving ? '…' : 'Add'}
      </button>
      <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={onCancel}>Cancel</button>
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

const QuoteBuilderPage: React.FC<{ quoteId: number }> = ({ quoteId }) => {
  const [quote, setQuote]             = useState<Quote | null>(null);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState<string | null>(null);
  const [catalogItems, setCatalogItems] = useState<CatalogItem[]>([]);

  // Editor state
  const [selectedBlock, setSelectedBlock]   = useState<QuoteBlock | null>(null);
  const [addingSection, setAddingSection]   = useState(false);
  const [addingBlock, setAddingBlock]       = useState<{ sectionId: number | null } | null>(null);

  const loadQuote = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const q = await apiFetch<Quote>(`/quotes/${quoteId}`);
      setQuote(q);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [quoteId]);

  const loadCatalog = useCallback(async () => {
    try {
      const items = await apiFetch<CatalogItem[]>('/catalog-items');
      setCatalogItems(items);
    } catch {
      setCatalogItems([]);
    }
  }, []);

  useEffect(() => {
    loadQuote();
    loadCatalog();
  }, [loadQuote, loadCatalog]);

  const handleAddBlock = async (type: KnownBlockType, sectionId: number | null) => {
    try {
      const updated = await apiFetch<Quote>(
        `/quotes/${quoteId}/sections/${sectionId}/blocks`,
        { method: 'POST', body: JSON.stringify({ type, payload: {} }) }
      );
      setQuote(updated);
      setAddingBlock(null);
      // Auto-select the new block
      const newBlock = [...updated.blocks].reverse().find(b => b.type === type);
      if (newBlock) setSelectedBlock(newBlock);
    } catch (e: any) {
      alert(`Failed to add block: ${e.message}`);
    }
  };

  const handleSaveBlock = async (blockId: number, payload: Record<string, any>) => {
    const updated = await apiFetch<Quote>(`/quotes/${quoteId}/blocks/${blockId}`, {
      method: 'PUT',
      body: JSON.stringify({ payload }),
    });
    setQuote(updated);
    // Update selectedBlock from fresh data
    const refreshed = updated.blocks.find(b => b.id === blockId);
    if (refreshed) setSelectedBlock(refreshed);
  };

  const handleDeleteBlock = async (blockId: number) => {
    if (!confirm('Delete this block?')) return;
    const updated = await apiFetch<Quote>(`/quotes/${quoteId}/blocks/${blockId}`, { method: 'DELETE' });
    setQuote(updated);
    setSelectedBlock(null);
  };

  const handleSectionAdded = (updated: Quote) => {
    setQuote(updated);
    setAddingSection(false);
  };

  const handleDeleteSection = async (sectionId: number) => {
    if (!confirm('Delete this section and all its blocks?')) return;
    try {
      const updated = await apiFetch<Quote>(`/quotes/${quoteId}/sections/${sectionId}`, { method: 'DELETE' });
      setQuote(updated);
      setSelectedBlock(null);
    } catch (e: any) {
      alert(`Failed: ${e.message}`);
    }
  };

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 60, color: '#6b7280' }}>
        Loading quote builder…
      </div>
    );
  }

  if (error || !quote) {
    return (
      <div className="portal-empty">
        <div className="portal-empty-title">Failed to load quote</div>
        <div className="portal-empty-subtitle">{error}</div>
        <a href="#quotes" className="portal-btn portal-btn-ghost">← Back to Quotes</a>
      </div>
    );
  }

  // Group blocks by section
  const unsectionedBlocks = quote.blocks.filter(b => b.sectionId === null);
  const sectionBlocks = (sectionId: number) => quote.blocks.filter(b => b.sectionId === sectionId);

  const renderBlockList = (blocks: QuoteBlock[], sectionId: number | null) => (
    <>
      {blocks
        .sort((a, b) => a.orderIndex - b.orderIndex)
        .map(block => (
          <BlockRow
            key={block.id}
            block={block}
            isSelected={selectedBlock?.id === block.id}
            onClick={() => setSelectedBlock(block.id === selectedBlock?.id ? null : block)}
          />
        ))}
      {addingBlock?.sectionId === sectionId ? (
        <div style={{ padding: '12px 16px', background: '#f9fafb', borderBottom: '1px solid #e5e7eb' }}>
          <BlockTypePicker
            onSelect={type => handleAddBlock(type, sectionId)}
            onCancel={() => setAddingBlock(null)}
          />
        </div>
      ) : (
        <button
          onClick={() => { setAddingBlock({ sectionId }); setSelectedBlock(null); }}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            padding: '10px 16px',
            background: 'none',
            border: 'none',
            borderBottom: '1px solid #f0f0f0',
            cursor: 'pointer',
            color: '#9ca3af',
            fontSize: 13,
            width: '100%',
            textAlign: 'left',
          }}
        >
          <span style={{ fontSize: 16 }}>+</span>
          Add block
        </button>
      )}
    </>
  );

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* Back nav */}
      <div style={{ padding: '8px 24px', borderBottom: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', gap: 12 }}>
        <a href="#quotes" style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#6b7280', textDecoration: 'none' }}>
          ← Quotes
        </a>
        <div style={{ fontSize: 13, color: '#d1d5db' }}>/</div>
        <div style={{ fontSize: 13, fontWeight: 600, color: '#111827' }}>Quote Builder</div>
      </div>

      {/* Summary bar */}
      <QuoteSummaryBar quote={quote} quoteId={quoteId} />

      {/* Editor body */}
      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        {/* Canvas */}
        <div style={{ flex: 1, overflowY: 'auto', borderRight: '1px solid #e5e7eb' }}>
          {/* Unsectioned blocks */}
          {(unsectionedBlocks.length > 0 || addingBlock?.sectionId === null) && (
            <div style={{ borderBottom: '1px solid #e5e7eb' }}>
              {renderBlockList(unsectionedBlocks, null)}
            </div>
          )}

          {/* Sections */}
          {quote.sections
            .sort((a, b) => a.orderIndex - b.orderIndex)
            .map(section => (
              <div key={section.id} style={{ borderBottom: '2px solid #e5e7eb' }}>
                {/* Section header */}
                <div style={{
                  display: 'flex',
                  alignItems: 'center',
                  padding: '10px 16px',
                  background: '#fafbfc',
                  borderBottom: '1px solid #e5e7eb',
                }}>
                  <div style={{ flex: 1, fontWeight: 700, fontSize: 13, color: '#374151' }}>
                    {section.name}
                  </div>
                  <button
                    onClick={() => handleDeleteSection(section.id)}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', fontSize: 12, padding: '2px 6px' }}
                    title="Delete section"
                  >
                    ×
                  </button>
                </div>
                {renderBlockList(sectionBlocks(section.id), section.id)}
              </div>
            ))}

          {/* Add section row */}
          {addingSection ? (
            <AddSectionInline
              quoteId={quote.id}
              onAdded={handleSectionAdded}
              onCancel={() => setAddingSection(false)}
            />
          ) : (
            <button
              onClick={() => setAddingSection(true)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '12px 16px',
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                color: '#9ca3af',
                fontSize: 13,
                width: '100%',
                textAlign: 'left',
              }}
            >
              <span style={{ fontSize: 16 }}>+</span> Add section
            </button>
          )}

          {/* If canvas is empty, prompt */}
          {quote.blocks.length === 0 && quote.sections.length === 0 && !addingBlock && (
            <div className="portal-empty">
              <div className="portal-empty-title">Empty quote</div>
              <div className="portal-empty-subtitle">Add blocks to build your quote, or create sections to group related items.</div>
              <button className="portal-btn portal-btn-primary" onClick={() => setAddingBlock({ sectionId: null })}>
                + Add First Block
              </button>
            </div>
          )}
        </div>

        {/* Right panel — block editor */}
        <div style={{ width: 340, flexShrink: 0, overflowY: 'auto', background: '#fff' }}>
          {selectedBlock ? (
            <div>
              <div style={{ padding: '14px 20px', borderBottom: '1px solid #e5e7eb', fontWeight: 700, fontSize: 14 }}>
                {blockLabel(selectedBlock.type)}
              </div>
              <div style={{ padding: 20 }}>
                <BlockForm
                  block={selectedBlock}
                  catalogItems={catalogItems}
                  onSave={payload => handleSaveBlock(selectedBlock.id, payload)}
                  onDelete={() => handleDeleteBlock(selectedBlock.id)}
                  onClose={() => setSelectedBlock(null)}
                />
              </div>
            </div>
          ) : (
            <div style={{ padding: 24, color: '#9ca3af', fontSize: 13, textAlign: 'center' }}>
              <div style={{ fontSize: 32, marginBottom: 12 }}>☝️</div>
              <div>Click a block on the left to edit it, or use the + buttons to add new blocks.</div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default QuoteBuilderPage;
