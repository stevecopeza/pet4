import React, { useCallback, useEffect, useState } from 'react';

// @ts-ignore
const apiUrl  = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce   = (): string => (window.petSettings?.nonce   ?? '') as string;
const hdrs    = () => ({ 'X-WP-Nonce': nonce() });
const jsonHdrs = () => ({ 'X-WP-Nonce': nonce(), 'Content-Type': 'application/json' });

interface Escalation {
  id: number;
  ticket_id: number;
  ticket_subject?: string;
  rule_percentage: number;
  rule_action: string;
  status: string;
  triggered_at: string;
  acknowledged_at?: string | null;
  resolved_at?: string | null;
  notes?: string | null;
}

function statusStyle(status: string): React.CSSProperties {
  const s = status.toLowerCase();
  if (s === 'open' || s === 'triggered') return { background: '#fef2f2', color: '#dc2626' };
  if (s === 'acknowledged') return { background: '#fff7ed', color: '#c2410c' };
  if (s === 'resolved') return { background: '#f0fdf4', color: '#16a34a' };
  return { background: '#f1f5f9', color: '#64748b' };
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
}

const STATUS_TABS = [
  { label: 'Open', value: 'open' },
  { label: 'All', value: 'all' },
];

const EscalationsPage: React.FC = () => {
  const [escalations, setEscalations] = useState<Escalation[]>([]);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('open');
  const [acting, setActing]           = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = statusFilter === 'open' ? '?status=open' : '?per_page=50';
      const res = await fetch(`${apiUrl()}/escalations${params}`, { headers: hdrs() });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      // API returns { data: [...], total: N } or just an array
      setEscalations(Array.isArray(data) ? data : (data.data ?? []));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => { load(); }, [load]);

  const doAction = async (id: number, action: 'acknowledge' | 'resolve') => {
    setActing(id);
    try {
      const res = await fetch(`${apiUrl()}/escalations/${id}/${action}`, {
        method: 'POST',
        headers: jsonHdrs(),
        body: JSON.stringify({}),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setActing(null);
    }
  };

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Escalations</h1>
        <div style={{ display: 'flex', gap: 8 }}>
          <div style={{ display: 'flex', border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden' }}>
            {STATUS_TABS.map(tab => (
              <button key={tab.value} onClick={() => setStatusFilter(tab.value)} style={{
                padding: '6px 14px', border: 'none', fontSize: 12, fontWeight: statusFilter === tab.value ? 700 : 400,
                background: statusFilter === tab.value ? '#2563eb' : '#f8fafc',
                color: statusFilter === tab.value ? '#fff' : '#475569', cursor: 'pointer',
              }}>{tab.label}</button>
            ))}
          </div>
          <button onClick={load} style={{ padding: '6px 12px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>↻</button>
        </div>
      </div>

      {error && <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 12 }}>{error}</div>}
      {loading && <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading…</div>}

      {!loading && escalations.length === 0 && !error && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          {statusFilter === 'open' ? '✓ No open escalations.' : 'No escalations found.'}
        </div>
      )}

      {!loading && escalations.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {escalations.map(esc => (
            <div key={esc.id} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '14px 18px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12, marginBottom: 8 }}>
                <div>
                  <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a', marginBottom: 2 }}>
                    {esc.ticket_subject ?? `Ticket #${esc.ticket_id}`}
                  </div>
                  <div style={{ fontSize: 12, color: '#64748b' }}>
                    Ticket #{esc.ticket_id} · Rule: {esc.rule_percentage}% SLA · {esc.rule_action?.replace(/_/g, ' ')}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexShrink: 0 }}>
                  <span style={{ fontSize: 12, fontWeight: 700, padding: '3px 10px', borderRadius: 20, ...statusStyle(esc.status) }}>
                    {esc.status}
                  </span>
                  <span style={{ fontSize: 12, color: '#94a3b8' }}>{timeAgo(esc.triggered_at)}</span>
                </div>
              </div>

              {esc.notes && (
                <div style={{ fontSize: 12, color: '#64748b', background: '#f8fafc', borderRadius: 6, padding: '6px 10px', marginBottom: 8 }}>
                  {esc.notes}
                </div>
              )}

              <div style={{ display: 'flex', gap: 8 }}>
                {(esc.status === 'open' || esc.status === 'triggered') && (
                  <button
                    onClick={() => doAction(esc.id, 'acknowledge')}
                    disabled={acting === esc.id}
                    style={{ padding: '5px 12px', border: '1px solid #fed7aa', borderRadius: 6, background: '#fff7ed', color: '#c2410c', fontSize: 12, fontWeight: 600, cursor: 'pointer', opacity: acting === esc.id ? 0.6 : 1 }}
                  >
                    Acknowledge
                  </button>
                )}
                {esc.status !== 'resolved' && (
                  <button
                    onClick={() => doAction(esc.id, 'resolve')}
                    disabled={acting === esc.id}
                    style={{ padding: '5px 12px', border: '1px solid #bbf7d0', borderRadius: 6, background: '#f0fdf4', color: '#16a34a', fontSize: 12, fontWeight: 600, cursor: 'pointer', opacity: acting === esc.id ? 0.6 : 1 }}
                  >
                    Resolve
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default EscalationsPage;
