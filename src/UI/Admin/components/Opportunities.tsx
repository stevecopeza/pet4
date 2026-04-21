import React, { useEffect, useState, useMemo } from 'react';
import { Opportunity } from '../types';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

// ── helpers ────────────────────────────────────────────────────────────────────

const STAGES: Array<{ key: Opportunity['stage']; label: string; color: string }> = [
  { key: 'discovery',   label: 'Discovery',   color: '#6366f1' },
  { key: 'proposal',    label: 'Proposal',    color: '#0ea5e9' },
  { key: 'negotiation', label: 'Negotiation', color: '#f59e0b' },
  { key: 'closed_won',  label: 'Closed Won',  color: '#22c55e' },
  { key: 'closed_lost', label: 'Closed Lost', color: '#ef4444' },
];

const stageInfo = (stage: string) =>
  STAGES.find(s => s.key === stage) ?? { label: stage, color: '#6b7280' };

function fmt(n: number, currency = 'ZAR') {
  return new Intl.NumberFormat('en-ZA', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
}

function closeDateStyle(date: string | null): React.CSSProperties {
  if (!date) return {};
  const diff = (new Date(date).getTime() - Date.now()) / 86_400_000;
  if (diff < 0)  return { color: '#ef4444', fontWeight: 600 };
  if (diff < 30) return { color: '#f59e0b', fontWeight: 600 };
  return {};
}

// ── form component ─────────────────────────────────────────────────────────────

interface OppFormProps {
  initial?: Partial<Opportunity>;
  onSave: (data: Partial<Opportunity>) => Promise<void>;
  onCancel: () => void;
}

const OppForm: React.FC<OppFormProps> = ({ initial, onSave, onCancel }) => {
  const [name, setName]             = useState(initial?.name ?? '');
  const [customerId, setCustomerId] = useState<string>(initial?.customerId ? String(initial.customerId) : '');
  const [stage, setStage]           = useState<Opportunity['stage']>(initial?.stage ?? 'discovery');
  const [value, setValue]           = useState<string>(initial?.estimatedValue ? String(initial.estimatedValue) : '');
  const [closeDate, setCloseDate]   = useState(initial?.expectedCloseDate ?? '');
  const [notes, setNotes]           = useState(initial?.notes ?? '');
  const [saving, setSaving]         = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await onSave({
        name,
        customerId: customerId ? Number(customerId) : undefined,
        stage,
        estimatedValue: value ? Number(value) : 0,
        expectedCloseDate: closeDate || null,
        notes: notes || null,
      });
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const fieldStyle: React.CSSProperties = { display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 16 };
  const labelStyle: React.CSSProperties = { fontSize: 12, fontWeight: 600, color: '#374151' };
  const inputStyle: React.CSSProperties = { border: '1px solid #d1d5db', borderRadius: 6, padding: '7px 10px', fontSize: 14 };

  return (
    <form onSubmit={handleSubmit} style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 24, maxWidth: 520, marginBottom: 24 }}>
      <h3 style={{ margin: '0 0 20px', fontSize: 16 }}>{initial?.id ? 'Edit Opportunity' : 'New Opportunity'}</h3>

      <div style={fieldStyle}>
        <label style={labelStyle}>Name *</label>
        <input style={inputStyle} value={name} onChange={e => setName(e.target.value)} required placeholder="e.g. Acme Corp - Managed Services" />
      </div>

      <div style={fieldStyle}>
        <label style={labelStyle}>Customer ID</label>
        <input style={inputStyle} type="number" value={customerId} onChange={e => setCustomerId(e.target.value)} placeholder="Customer ID" />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
        <div style={fieldStyle}>
          <label style={labelStyle}>Stage</label>
          <select style={inputStyle} value={stage} onChange={e => setStage(e.target.value as Opportunity['stage'])}>
            {STAGES.slice(0, 3).map(s => (
              <option key={s.key} value={s.key}>{s.label}</option>
            ))}
          </select>
        </div>
        <div style={fieldStyle}>
          <label style={labelStyle}>Est. Value (ZAR)</label>
          <input style={inputStyle} type="number" value={value} onChange={e => setValue(e.target.value)} placeholder="0" min={0} />
        </div>
      </div>

      <div style={fieldStyle}>
        <label style={labelStyle}>Expected Close Date</label>
        <input style={inputStyle} type="date" value={closeDate} onChange={e => setCloseDate(e.target.value)} />
      </div>

      <div style={fieldStyle}>
        <label style={labelStyle}>Notes</label>
        <textarea style={{ ...inputStyle, resize: 'vertical', minHeight: 80 }} value={notes} onChange={e => setNotes(e.target.value)} />
      </div>

      <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
        <button type="button" className="button" onClick={onCancel} disabled={saving}>Cancel</button>
        <button type="submit" className="button button-primary" disabled={saving}>
          {saving ? 'Saving…' : (initial?.id ? 'Save Changes' : 'Create Opportunity')}
        </button>
      </div>
    </form>
  );
};

// ── main component ─────────────────────────────────────────────────────────────

interface OpportunitiesProps {
  onNavigateToQuote?: (quoteId: number) => void;
}

const Opportunities: React.FC<OpportunitiesProps> = ({ onNavigateToQuote }) => {
  const [opps, setOpps]             = useState<Opportunity[]>([]);
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState<string | null>(null);
  const [view, setView]             = useState<'kanban' | 'list'>('kanban');
  const [showForm, setShowForm]     = useState(false);
  const [editing, setEditing]       = useState<Opportunity | null>(null);
  const [selected, setSelected]     = useState<Opportunity | null>(null);

  // @ts-ignore
  const apiUrl: string = window.petSettings?.apiUrl ?? '';
  // @ts-ignore
  const nonce: string  = window.petSettings?.nonce ?? '';

  const fetchOpps = async () => {
    setLoading(true);
    try {
      const r = await fetch(`${apiUrl}/opportunities`, { headers: { 'X-WP-Nonce': nonce } });
      if (!r.ok) throw new Error('Failed to load opportunities');
      setOpps(await r.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchOpps(); }, []);

  // KPI strip
  const openOpps   = useMemo(() => opps.filter(o => o.isOpen), [opps]);
  const pipeline   = useMemo(() => openOpps.reduce((s, o) => s + o.estimatedValue, 0), [openOpps]);
  const wonOpps    = useMemo(() => opps.filter(o => o.stage === 'closed_won'), [opps]);
  const wonValue   = useMemo(() => wonOpps.reduce((s, o) => s + o.estimatedValue, 0), [wonOpps]);

  const handleSave = async (data: Partial<Opportunity>) => {
    if (editing) {
      const r = await fetch(`${apiUrl}/opportunities/${editing.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({
          name: data.name,
          stage: data.stage,
          estimatedValue: data.estimatedValue,
          currency: 'ZAR',
          expectedCloseDate: data.expectedCloseDate,
          notes: data.notes,
        }),
      });
      if (!r.ok) throw new Error((await r.json()).message || 'Save failed');
    } else {
      const r = await fetch(`${apiUrl}/opportunities`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({
          customerId: data.customerId ?? 0,
          name: data.name,
          stage: data.stage ?? 'discovery',
          estimatedValue: data.estimatedValue ?? 0,
          currency: 'ZAR',
          expectedCloseDate: data.expectedCloseDate,
          notes: data.notes,
        }),
      });
      if (!r.ok) throw new Error((await r.json()).message || 'Create failed');
    }
    setShowForm(false);
    setEditing(null);
    fetchOpps();
  };

  const handleClose = async (opp: Opportunity, stage: 'closed_won' | 'closed_lost') => {
    if (!legacyConfirm(`Mark "${opp.name}" as ${stage === 'closed_won' ? 'Won' : 'Lost'}?`)) return;
    const r = await fetch(`${apiUrl}/opportunities/${opp.id}/close`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({ stage }),
    });
    if (!r.ok) { legacyAlert((await r.json()).message || 'Failed'); return; }
    if (selected?.id === opp.id) setSelected(null);
    fetchOpps();
  };

  const handleConvert = async (opp: Opportunity) => {
    if (!legacyConfirm(`Convert "${opp.name}" to a quote?`)) return;
    const r = await fetch(`${apiUrl}/opportunities/${opp.id}/convert-quote`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({}),
    });
    if (!r.ok) { legacyAlert((await r.json()).message || 'Failed'); return; }
    const data = await r.json();
    fetchOpps();
    if (onNavigateToQuote && data.quoteId) onNavigateToQuote(data.quoteId);
  };

  const handleDelete = async (opp: Opportunity) => {
    if (!legacyConfirm(`Delete "${opp.name}"? This cannot be undone.`)) return;
    const r = await fetch(`${apiUrl}/opportunities/${opp.id}`, {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': nonce },
    });
    if (!r.ok) { legacyAlert('Failed to delete'); return; }
    if (selected?.id === opp.id) setSelected(null);
    fetchOpps();
  };

  if (showForm || editing) {
    return (
      <OppForm
        initial={editing ?? undefined}
        onSave={handleSave}
        onCancel={() => { setShowForm(false); setEditing(null); }}
      />
    );
  }

  // ── KPI strip ────────────────────────────────────────────────────────────────
  const kpiCard = (label: string, value: string | number) => (
    <div key={label} style={{ background: '#f8fafc', border: '1px solid #e5e7eb', borderRadius: 8, padding: '14px 20px', minWidth: 150 }}>
      <div style={{ fontSize: 11, color: '#6b7280', fontWeight: 600, textTransform: 'uppercase', marginBottom: 4 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700, color: '#111827' }}>{value}</div>
    </div>
  );

  // ── Kanban column ────────────────────────────────────────────────────────────
  const renderKanban = () => {
    const byStage = (stageKey: Opportunity['stage']) =>
      opps.filter(o => o.stage === stageKey);

    return (
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12, overflowX: 'auto' }}>
        {STAGES.map(stg => {
          const cards = byStage(stg.key);
          const total = cards.reduce((s, o) => s + o.estimatedValue, 0);
          return (
            <div key={stg.key} style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12, minWidth: 180 }}>
              {/* Column header */}
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <span style={{ width: 8, height: 8, borderRadius: '50%', background: stg.color, display: 'inline-block' }} />
                  <span style={{ fontSize: 12, fontWeight: 700, color: '#374151' }}>{stg.label}</span>
                </div>
                <span style={{ fontSize: 11, color: '#9ca3af' }}>{cards.length}</span>
              </div>
              {total > 0 && (
                <div style={{ fontSize: 11, color: '#6b7280', marginBottom: 10 }}>{fmt(total)}</div>
              )}
              {/* Cards */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {cards.map(opp => (
                  <div
                    key={opp.id}
                    onClick={() => setSelected(opp)}
                    style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 6, padding: '10px 12px', cursor: 'pointer', transition: 'box-shadow 0.15s' }}
                    onMouseEnter={e => (e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)')}
                    onMouseLeave={e => (e.currentTarget.style.boxShadow = '')}
                  >
                    <div style={{ fontSize: 13, fontWeight: 600, color: '#111827', marginBottom: 4, lineHeight: 1.3 }}>{opp.name}</div>
                    {opp.customerName && (
                      <div style={{ fontSize: 11, color: '#6b7280', marginBottom: 6 }}>{opp.customerName}</div>
                    )}
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontSize: 12, fontWeight: 600, color: '#374151' }}>{fmt(opp.estimatedValue, opp.currency)}</span>
                      {opp.expectedCloseDate && (
                        <span style={{ fontSize: 11, ...closeDateStyle(opp.expectedCloseDate) }}>
                          {new Date(opp.expectedCloseDate).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' })}
                        </span>
                      )}
                    </div>
                    {opp.quoteId && (
                      <div style={{ fontSize: 11, color: '#2563eb', marginTop: 4 }}>Quote #{opp.quoteId}</div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    );
  };

  // ── List view ─────────────────────────────────────────────────────────────────
  const renderList = () => {
    const badgeStyle = (stage: string): React.CSSProperties => {
      const info = stageInfo(stage);
      return { display: 'inline-block', padding: '2px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600, color: '#fff', background: info.color };
    };
    return (
      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #e5e7eb', textAlign: 'left' }}>
            {['Name', 'Customer', 'Stage', 'Est. Value', 'Close Date', 'Quote', 'Actions'].map(h => (
              <th key={h} style={{ padding: '8px 12px', color: '#6b7280', fontWeight: 600, fontSize: 12 }}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {opps.map(opp => (
            <tr
              key={opp.id}
              style={{ borderBottom: '1px solid #f3f4f6', cursor: 'pointer' }}
              onClick={() => setSelected(opp)}
              onMouseEnter={e => (e.currentTarget.style.background = '#f9fafb')}
              onMouseLeave={e => (e.currentTarget.style.background = '')}
            >
              <td style={{ padding: '10px 12px', fontWeight: 600, color: '#111827' }}>{opp.name}</td>
              <td style={{ padding: '10px 12px', color: '#6b7280' }}>{opp.customerName ?? '—'}</td>
              <td style={{ padding: '10px 12px' }}><span style={badgeStyle(opp.stage)}>{stageInfo(opp.stage).label}</span></td>
              <td style={{ padding: '10px 12px', fontWeight: 600 }}>{fmt(opp.estimatedValue, opp.currency)}</td>
              <td style={{ padding: '10px 12px', ...closeDateStyle(opp.expectedCloseDate) }}>{opp.expectedCloseDate ?? '—'}</td>
              <td style={{ padding: '10px 12px', color: '#2563eb' }}>{opp.quoteId ? `#${opp.quoteId}` : '—'}</td>
              <td style={{ padding: '10px 12px' }} onClick={e => e.stopPropagation()}>
                <div style={{ display: 'flex', gap: 6 }}>
                  <button
                    className="button button-small"
                    onClick={() => { setEditing(opp); }}
                    style={{ fontSize: 11 }}
                  >Edit</button>
                  {opp.isOpen && !opp.quoteId && (
                    <button
                      className="button button-small"
                      onClick={() => handleConvert(opp)}
                      style={{ fontSize: 11 }}
                    >→ Quote</button>
                  )}
                  {opp.isOpen && (
                    <>
                      <button
                        className="button button-small"
                        onClick={() => handleClose(opp, 'closed_won')}
                        style={{ fontSize: 11, color: '#16a34a', borderColor: '#16a34a' }}
                      >Won</button>
                      <button
                        className="button button-small"
                        onClick={() => handleClose(opp, 'closed_lost')}
                        style={{ fontSize: 11, color: '#dc2626', borderColor: '#dc2626' }}
                      >Lost</button>
                    </>
                  )}
                  <button
                    className="button button-small button-link-delete"
                    onClick={() => handleDelete(opp)}
                    style={{ fontSize: 11 }}
                  >Delete</button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  };

  // ── Detail drawer ─────────────────────────────────────────────────────────────
  const renderDrawer = () => {
    if (!selected) return null;
    const opp = selected;
    const info = stageInfo(opp.stage);

    const row = (label: string, val: React.ReactNode) => (
      <div key={label} style={{ display: 'grid', gridTemplateColumns: '130px 1fr', gap: 8, paddingBottom: 10, borderBottom: '1px solid #f3f4f6', marginBottom: 10 }}>
        <div style={{ fontSize: 12, fontWeight: 600, color: '#6b7280' }}>{label}</div>
        <div style={{ fontSize: 13, color: '#111827' }}>{val ?? '—'}</div>
      </div>
    );

    return (
      <>
        {/* Backdrop */}
        <div
          onClick={() => setSelected(null)}
          style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.25)', zIndex: 1000 }}
        />
        {/* Panel */}
        <div style={{ position: 'fixed', right: 0, top: 0, bottom: 0, width: 420, background: '#fff', boxShadow: '-4px 0 20px rgba(0,0,0,0.12)', zIndex: 1001, display: 'flex', flexDirection: 'column', overflowY: 'auto' }}>
          {/* Header */}
          <div style={{ padding: '20px 24px', borderBottom: '1px solid #e5e7eb' }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
              <div>
                <h2 style={{ margin: 0, fontSize: 18, color: '#111827' }}>{opp.name}</h2>
                {opp.customerName && (
                  <div style={{ fontSize: 13, color: '#6b7280', marginTop: 4 }}>{opp.customerName}</div>
                )}
              </div>
              <button
                onClick={() => setSelected(null)}
                style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer', color: '#9ca3af', padding: 0, lineHeight: 1 }}
              >×</button>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 14, flexWrap: 'wrap' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '3px 10px', borderRadius: 12, fontSize: 12, fontWeight: 700, color: '#fff', background: info.color }}>
                <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'rgba(255,255,255,0.7)', display: 'inline-block' }} />
                {info.label}
              </span>
              <span style={{ padding: '3px 10px', borderRadius: 12, fontSize: 12, fontWeight: 700, color: '#111827', background: '#f3f4f6' }}>
                {fmt(opp.estimatedValue, opp.currency)}
              </span>
            </div>
          </div>

          {/* Details */}
          <div style={{ padding: '20px 24px', flex: 1 }}>
            {row('Customer ID', opp.customerId)}
            {opp.leadId && row('Lead', `#${opp.leadId}`)}
            {opp.quoteId && row('Quote', <a href="#" onClick={e => { e.preventDefault(); onNavigateToQuote?.(opp.quoteId!); setSelected(null); }} style={{ color: '#2563eb' }}>Quote #{opp.quoteId}</a>)}
            {row('Close Date', opp.expectedCloseDate
              ? <span style={closeDateStyle(opp.expectedCloseDate)}>{opp.expectedCloseDate}</span>
              : '—')}
            {row('Created', opp.createdAt)}
            {opp.closedAt && row('Closed', opp.closedAt)}

            {/* Qualification */}
            {opp.qualification && Object.keys(opp.qualification).length > 0 && (
              <div style={{ marginBottom: 16 }}>
                <div style={{ fontSize: 12, fontWeight: 700, color: '#374151', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Qualification</div>
                {Object.entries(opp.qualification).map(([k, v]) => (
                  <div key={k} style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: 8, marginBottom: 6 }}>
                    <div style={{ fontSize: 12, color: '#6b7280' }}>{k}</div>
                    <div style={{ fontSize: 13, color: '#111827' }}>
                      {typeof v === 'boolean' ? (v ? '✓ Yes' : '✗ No') : String(v)}
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Notes */}
            {opp.notes && (
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, color: '#374151', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Notes</div>
                <div style={{ fontSize: 13, color: '#374151', background: '#f9fafb', borderRadius: 6, padding: '10px 14px', lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>
                  {opp.notes}
                </div>
              </div>
            )}
          </div>

          {/* Actions footer */}
          {opp.isOpen && (
            <div style={{ padding: '16px 24px', borderTop: '1px solid #e5e7eb', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              <button className="button button-primary" onClick={() => { setSelected(null); setEditing(opp); }}>Edit</button>
              {!opp.quoteId && (
                <button className="button" onClick={() => handleConvert(opp)}>Convert to Quote</button>
              )}
              <button
                className="button"
                onClick={() => handleClose(opp, 'closed_won')}
                style={{ color: '#16a34a', borderColor: '#16a34a' }}
              >Mark Won</button>
              <button
                className="button"
                onClick={() => handleClose(opp, 'closed_lost')}
                style={{ color: '#dc2626', borderColor: '#dc2626' }}
              >Mark Lost</button>
            </div>
          )}
        </div>
      </>
    );
  };

  return (
    <div>
      {/* Header */}
      <div className="pet-page-header" style={{ marginBottom: 20 }}>
        <h2>Opportunities</h2>
        <div style={{ display: 'flex', gap: 8 }}>
          <button
            className={`button${view === 'kanban' ? ' button-primary' : ''}`}
            onClick={() => setView('kanban')}
          >Kanban</button>
          <button
            className={`button${view === 'list' ? ' button-primary' : ''}`}
            onClick={() => setView('list')}
          >List</button>
          <button className="button button-primary" onClick={() => setShowForm(true)}>
            + New Opportunity
          </button>
        </div>
      </div>

      {/* KPI strip */}
      <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
        {kpiCard('Open', openOpps.length)}
        {kpiCard('Pipeline', fmt(pipeline))}
        {kpiCard('Closed Won', wonOpps.length)}
        {kpiCard('Won Value', fmt(wonValue))}
      </div>

      {error && <div className="notice notice-error inline"><p>{error}</p></div>}
      {loading && <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>Loading…</div>}

      {!loading && opps.length === 0 && (
        <div style={{ textAlign: 'center', padding: 60, color: '#9ca3af' }}>
          <div style={{ fontSize: 32, marginBottom: 8 }}>🎯</div>
          <div style={{ fontSize: 16, fontWeight: 600 }}>No opportunities yet</div>
          <div style={{ fontSize: 13, marginTop: 4 }}>Create your first opportunity to start tracking your pipeline.</div>
        </div>
      )}

      {!loading && opps.length > 0 && (
        view === 'kanban' ? renderKanban() : renderList()
      )}

      {renderDrawer()}
    </div>
  );
};

export default Opportunities;
