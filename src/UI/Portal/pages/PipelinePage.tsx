import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Opportunity {
  id: string;
  customerId: number;
  customerName: string | null;
  leadId: number | null;
  name: string;
  stage: string;
  estimatedValue: number;
  currency: string | null;
  expectedCloseDate: string | null;
  ownerId: number;
  qualification: Record<string, any>;
  notes: string | null;
  quoteId: number | null;
  isOpen: boolean;
  createdAt: string;
  updatedAt: string | null;
  closedAt: string | null;
}

const STAGES = [
  { key: 'discovery',   label: 'Discovery',   color: '#3b82f6', bg: '#eff6ff' },
  { key: 'proposal',    label: 'Proposal',    color: '#8b5cf6', bg: '#f5f3ff' },
  { key: 'negotiation', label: 'Negotiation', color: '#f59e0b', bg: '#fffbeb' },
  { key: 'closed_won',  label: 'Closed Won',  color: '#10b981', bg: '#f0fdf4' },
  { key: 'closed_lost', label: 'Closed Lost', color: '#ef4444', bg: '#fef2f2' },
];

function formatCurrency(value: number, currency?: string | null): string {
  return new Intl.NumberFormat('en-ZA', {
    style: 'currency',
    currency: currency ?? 'ZAR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

function closeDateStyle(date: string | null): React.CSSProperties {
  if (!date) return { color: '#94a3b8' };
  const diff = new Date(date).getTime() - Date.now();
  const days  = diff / 86400000;
  if (days < 0)  return { color: '#dc2626', fontWeight: 700 };
  if (days < 30) return { color: '#c2410c', fontWeight: 600 };
  return { color: '#64748b' };
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatStatus(s: string): string {
  return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const PipelinePage: React.FC = () => {
  const user = usePortalUser();
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [loading, setLoading]             = useState(true);
  const [error, setError]                 = useState<string | null>(null);
  const [view, setView]                   = useState<'kanban' | 'list'>('kanban');
  const [myOnly, setMyOnly]               = useState(false);
  const [selected, setSelected]           = useState<Opportunity | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/opportunities`, { headers: hdrs() });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setOpportunities(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load pipeline');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // @ts-ignore
  const currentWpUserId = String((window.petSettings?.currentUserId ?? 0));

  const filtered = useMemo(() => {
    if (!myOnly) return opportunities;
    return opportunities.filter(o => String(o.ownerId) === currentWpUserId);
  }, [opportunities, myOnly, currentWpUserId]);

  const byStage = useMemo(() => {
    const map: Record<string, Opportunity[]> = {};
    STAGES.forEach(s => { map[s.key] = []; });
    filtered.forEach(o => {
      if (map[o.stage]) map[o.stage].push(o);
    });
    return map;
  }, [filtered]);

  const pipelineValue = useMemo(() =>
    filtered.filter(o => o.isOpen).reduce((sum, o) => sum + o.estimatedValue, 0),
    [filtered]
  );

  const openCount = filtered.filter(o => o.isOpen).length;

  return (
    <div style={{ maxWidth: 1100, margin: '0 auto', padding: '24px 20px' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <div>
          <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: '0 0 2px' }}>Pipeline</h1>
          <p style={{ margin: 0, color: '#64748b', fontSize: 13 }}>Track opportunities from discovery to close.</p>
        </div>
        <button onClick={load} style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {/* KPI strip */}
      {!loading && !error && (
        <div style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
          <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '12px 16px', flex: 1 }}>
            <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b', marginBottom: 4 }}>Open Opportunities</div>
            <div style={{ fontSize: 20, fontWeight: 700, color: '#0f172a' }}>{openCount}</div>
          </div>
          <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '12px 16px', flex: 2 }}>
            <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b', marginBottom: 4 }}>Pipeline Value</div>
            <div style={{ fontSize: 20, fontWeight: 700, color: '#2563eb' }}>{formatCurrency(pipelineValue)}</div>
          </div>
        </div>
      )}

      {/* Controls */}
      <div style={{ display: 'flex', gap: 10, marginBottom: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden' }}>
          {(['kanban', 'list'] as const).map(v => (
            <button key={v} onClick={() => setView(v)} style={{
              padding: '7px 14px', border: 'none', fontSize: 12,
              fontWeight: view === v ? 700 : 400,
              background: view === v ? '#2563eb' : '#f8fafc',
              color: view === v ? '#fff' : '#475569', cursor: 'pointer',
            }}>{v === 'kanban' ? '⬛ Kanban' : '≡ List'}</button>
          ))}
        </div>
        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#475569', cursor: 'pointer' }}>
          <input type="checkbox" checked={myOnly} onChange={e => setMyOnly(e.target.checked)} />
          My opportunities only
        </label>
      </div>

      {error && <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 12 }}>{error}</div>}
      {loading && <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading…</div>}

      {!loading && filtered.length === 0 && !error && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          No opportunities found.
        </div>
      )}

      {/* Kanban view */}
      {!loading && view === 'kanban' && filtered.length > 0 && (
        <div style={{ display: 'flex', gap: 12, overflowX: 'auto', paddingBottom: 8 }}>
          {STAGES.map(stage => {
            const cards = byStage[stage.key] ?? [];
            return (
              <div key={stage.key} style={{ flex: '0 0 220px', minWidth: 220 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                  <span style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: stage.color }}>
                    {stage.label}
                  </span>
                  <span style={{ fontSize: 11, background: stage.bg, color: stage.color, borderRadius: 10, padding: '1px 7px', fontWeight: 700 }}>
                    {cards.length}
                  </span>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                  {cards.map(opp => (
                    <button
                      key={opp.id}
                      onClick={() => setSelected(opp)}
                      style={{ textAlign: 'left', background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '12px 14px', cursor: 'pointer', width: '100%' }}
                    >
                      <div style={{ fontSize: 13, fontWeight: 600, color: '#0f172a', marginBottom: 4, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {opp.name}
                      </div>
                      <div style={{ fontSize: 11, color: '#64748b', marginBottom: 6, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {opp.customerName ?? `Customer #${opp.customerId}`}
                      </div>
                      <div style={{ fontSize: 12, fontWeight: 700, color: '#2563eb', marginBottom: 4 }}>
                        {formatCurrency(opp.estimatedValue, opp.currency)}
                      </div>
                      {opp.expectedCloseDate && (
                        <div style={{ fontSize: 11, ...closeDateStyle(opp.expectedCloseDate) }}>
                          Close: {formatDate(opp.expectedCloseDate)}
                        </div>
                      )}
                    </button>
                  ))}
                  {cards.length === 0 && (
                    <div style={{ border: '1px dashed #e2e8f0', borderRadius: 10, padding: '20px 0', textAlign: 'center', color: '#cbd5e1', fontSize: 12 }}>
                      Empty
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* List view */}
      {!loading && view === 'list' && filtered.length > 0 && (
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
            <thead>
              <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
                {['Name', 'Customer', 'Stage', 'Value', 'Close Date', 'Quote'].map(h => (
                  <th key={h} style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.map(opp => {
                const stageInfo = STAGES.find(s => s.key === opp.stage);
                return (
                  <tr key={opp.id} style={{ borderBottom: '1px solid #f1f5f9', cursor: 'pointer' }} onClick={() => setSelected(opp)}>
                    <td style={{ padding: '10px 14px', fontWeight: 600, color: '#1e293b', maxWidth: 200 }}>
                      <div style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{opp.name}</div>
                    </td>
                    <td style={{ padding: '10px 14px', color: '#64748b', whiteSpace: 'nowrap' }}>
                      {opp.customerName ?? `#${opp.customerId}`}
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap' }}>
                      <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 10, background: stageInfo?.bg ?? '#f1f5f9', color: stageInfo?.color ?? '#64748b' }}>
                        {stageInfo?.label ?? formatStatus(opp.stage)}
                      </span>
                    </td>
                    <td style={{ padding: '10px 14px', fontWeight: 600, color: '#2563eb', whiteSpace: 'nowrap' }}>
                      {formatCurrency(opp.estimatedValue, opp.currency)}
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap', ...closeDateStyle(opp.expectedCloseDate) }}>
                      {formatDate(opp.expectedCloseDate)}
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap' }}>
                      {opp.quoteId
                        ? <a href={`#quotes/${opp.quoteId}`} style={{ color: '#2563eb', fontSize: 12 }}>Quote #{opp.quoteId}</a>
                        : <span style={{ color: '#cbd5e1', fontSize: 12 }}>—</span>
                      }
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Detail drawer */}
      {selected && (
        <div
          style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.35)', zIndex: 1000, display: 'flex', justifyContent: 'flex-end' }}
          onClick={() => setSelected(null)}
        >
          <div
            style={{ width: 420, maxWidth: '95vw', background: '#fff', height: '100%', overflowY: 'auto', padding: 24, boxShadow: '-4px 0 24px rgba(0,0,0,0.1)' }}
            onClick={e => e.stopPropagation()}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', margin: 0 }}>{selected.name}</h2>
              <button onClick={() => setSelected(null)} style={{ background: 'none', border: 'none', fontSize: 20, cursor: 'pointer', color: '#94a3b8' }}>✕</button>
            </div>

            {/* Stage badge */}
            {(() => {
              const st = STAGES.find(s => s.key === selected.stage);
              return (
                <span style={{ fontSize: 12, fontWeight: 700, padding: '3px 12px', borderRadius: 20, background: st?.bg ?? '#f1f5f9', color: st?.color ?? '#64748b', display: 'inline-block', marginBottom: 16 }}>
                  {st?.label ?? formatStatus(selected.stage)}
                </span>
              );
            })()}

            {/* Key fields */}
            {[
              { label: 'Customer', value: selected.customerName ?? `#${selected.customerId}` },
              { label: 'Value', value: formatCurrency(selected.estimatedValue, selected.currency) },
              { label: 'Close Date', value: formatDate(selected.expectedCloseDate) },
              { label: 'Linked Quote', value: selected.quoteId ? `Quote #${selected.quoteId}` : '—' },
            ].map(({ label, value }) => (
              <div key={label} style={{ display: 'grid', gridTemplateColumns: '120px 1fr', gap: 4, padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                <span style={{ fontSize: 12, color: '#64748b', fontWeight: 600 }}>{label}</span>
                <span style={{ fontSize: 13, color: '#1e293b' }}>{value}</span>
              </div>
            ))}

            {/* Qualification */}
            {selected.qualification && Object.keys(selected.qualification).length > 0 && (
              <div style={{ marginTop: 20 }}>
                <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#94a3b8', marginBottom: 10 }}>Qualification</div>
                {Object.entries(selected.qualification).map(([k, v]) => (
                  <div key={k} style={{ display: 'grid', gridTemplateColumns: '140px 1fr', gap: 4, padding: '7px 0', borderBottom: '1px solid #f1f5f9' }}>
                    <span style={{ fontSize: 12, color: '#64748b', fontWeight: 600 }}>
                      {k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                    </span>
                    <span style={{ fontSize: 13, color: '#1e293b' }}>
                      {typeof v === 'boolean' ? (v ? '✓ Yes' : '✗ No') : String(v ?? '—')}
                    </span>
                  </div>
                ))}
              </div>
            )}

            {/* Notes */}
            {selected.notes && (
              <div style={{ marginTop: 20 }}>
                <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#94a3b8', marginBottom: 8 }}>Notes</div>
                <div style={{ fontSize: 13, color: '#475569', lineHeight: 1.6, background: '#f8fafc', borderRadius: 8, padding: 12 }}>{selected.notes}</div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default PipelinePage;
