import React, { useCallback, useEffect, useMemo, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Ticket {
  id: number;
  subject: string;
  status: string;
  priority: string;
  customerId: number;
  assignedUserId?: string | null;
  sla_status?: string;
  createdAt: string;
  updatedAt?: string | null;
}

interface Customer {
  id: number;
  name: string;
}

function statusStyle(status: string): React.CSSProperties {
  const s = status.toLowerCase();
  if (s === 'open' || s === 'new') return { background: '#eff6ff', color: '#1d4ed8' };
  if (s === 'in_progress' || s === 'pending') return { background: '#fff7ed', color: '#c2410c' };
  if (s === 'resolved' || s === 'closed') return { background: '#f0fdf4', color: '#16a34a' };
  if (s === 'cancelled') return { background: '#fef2f2', color: '#dc2626' };
  return { background: '#f1f5f9', color: '#64748b' };
}

function slaStyle(sla: string | undefined): React.CSSProperties | null {
  if (!sla) return null;
  if (sla === 'breached') return { background: '#fef2f2', color: '#dc2626' };
  if (sla === 'warning') return { background: '#fff7ed', color: '#c2410c' };
  return null;
}

function priorityStyle(priority: string): React.CSSProperties {
  const p = priority.toLowerCase();
  if (p === 'critical') return { color: '#dc2626', fontWeight: 700 };
  if (p === 'high') return { color: '#c2410c', fontWeight: 600 };
  if (p === 'medium') return { color: '#b45309', fontWeight: 500 };
  return { color: '#64748b' };
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
  { label: 'All Active', value: 'active' },
  { label: 'All', value: 'all' },
];

const SupportQueuePage: React.FC = () => {
  const [tickets, setTickets]     = useState<Ticket[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('open');
  const [search, setSearch]       = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [tickRes, custRes] = await Promise.all([
        fetch(`${apiUrl()}/tickets?lifecycle_owner=helpdesk`, { headers: hdrs() }),
        fetch(`${apiUrl()}/customers`, { headers: hdrs() }),
      ]);
      if (!tickRes.ok) throw new Error(`HTTP ${tickRes.status}`);
      setTickets(await tickRes.json());
      if (custRes.ok) setCustomers(await custRes.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const customerById = useMemo(() => new Map(customers.map(c => [c.id, c.name])), [customers]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return tickets.filter(t => {
      const s = (t.status ?? '').toLowerCase();
      if (statusFilter === 'open') {
        if (s !== 'open' && s !== 'new') return false;
      } else if (statusFilter === 'active') {
        if (s === 'resolved' || s === 'closed' || s === 'cancelled') return false;
      }
      if (!q) return true;
      const custName = customerById.get(t.customerId) ?? '';
      return `${t.subject} ${custName} #${t.id}`.toLowerCase().includes(q);
    });
  }, [tickets, statusFilter, search, customerById]);

  const openCount    = tickets.filter(t => { const s = t.status?.toLowerCase(); return s === 'open' || s === 'new'; }).length;
  const breachedCount = tickets.filter(t => t.sla_status === 'breached').length;
  const unassignedCount = tickets.filter(t => !t.assignedUserId && (t.status?.toLowerCase() === 'open' || t.status?.toLowerCase() === 'new')).length;

  return (
    <div style={{ maxWidth: 1050, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Support Queue</h1>
        <button onClick={load} style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {/* KPI strip */}
      {!loading && !error && (
        <div style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
          {[
            { label: 'Open tickets', value: String(openCount), highlight: openCount > 0 },
            { label: 'Unassigned', value: String(unassignedCount), highlight: unassignedCount > 0, color: '#c2410c' },
            { label: 'SLA breached', value: String(breachedCount), highlight: breachedCount > 0, color: '#dc2626' },
            { label: 'Total', value: String(tickets.length), highlight: false },
          ].map(({ label, value, highlight, color }) => (
            <div key={label} style={{ background: '#fff', border: `1px solid ${highlight && color ? '#fecaca' : '#e2e8f0'}`, borderRadius: 10, padding: '12px 16px', flex: 1 }}>
              <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b', marginBottom: 4 }}>{label}</div>
              <div style={{ fontSize: 20, fontWeight: 700, color: (highlight && color) ? color : '#0f172a' }}>{value}</div>
            </div>
          ))}
        </div>
      )}

      {/* Filters */}
      <div style={{ display: 'flex', gap: 10, marginBottom: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <input
          type="search"
          placeholder="Search by subject, customer, or #id…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{ padding: '7px 12px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13, minWidth: 280, color: '#1e293b' }}
        />
        <div style={{ display: 'flex', border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden' }}>
          {STATUS_TABS.map(tab => (
            <button key={tab.value} onClick={() => setStatusFilter(tab.value)} style={{
              padding: '7px 14px', border: 'none', fontSize: 12, fontWeight: statusFilter === tab.value ? 700 : 400,
              background: statusFilter === tab.value ? '#2563eb' : '#f8fafc',
              color: statusFilter === tab.value ? '#fff' : '#475569', cursor: 'pointer',
            }}>{tab.label}</button>
          ))}
        </div>
      </div>

      {error && <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 12 }}>{error}</div>}
      {loading && <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading…</div>}

      {!loading && filtered.length === 0 && !error && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          {search ? 'No tickets match your search.' : 'No tickets found.'}
        </div>
      )}

      {!loading && filtered.length > 0 && (
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
            <thead>
              <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
                {['#', 'Subject', 'Customer', 'Priority', 'Status', 'SLA', 'Updated'].map(h => (
                  <th key={h} style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.map(t => {
                const slaSt = slaStyle(t.sla_status);
                return (
                  <tr key={t.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                    <td style={{ padding: '10px 14px', color: '#94a3b8', fontSize: 12 }}>#{t.id}</td>
                    <td style={{ padding: '10px 14px', color: '#1e293b', fontWeight: 500, maxWidth: 280 }}>
                      <div style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.subject}</div>
                      {!t.assignedUserId && <span style={{ fontSize: 10, color: '#c2410c', fontWeight: 700 }}>UNASSIGNED</span>}
                    </td>
                    <td style={{ padding: '10px 14px', color: '#64748b', whiteSpace: 'nowrap' }}>
                      {customerById.get(t.customerId) ?? `#${t.customerId}`}
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap' }}>
                      <span style={{ ...priorityStyle(t.priority ?? ''), fontSize: 12 }}>{t.priority ?? '—'}</span>
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap' }}>
                      <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 10, ...statusStyle(t.status ?? '') }}>
                        {t.status}
                      </span>
                    </td>
                    <td style={{ padding: '10px 14px', whiteSpace: 'nowrap' }}>
                      {t.sla_status && slaSt ? (
                        <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 10, ...slaSt }}>{t.sla_status}</span>
                      ) : <span style={{ color: '#cbd5e1', fontSize: 12 }}>—</span>}
                    </td>
                    <td style={{ padding: '10px 14px', color: '#94a3b8', fontSize: 12, whiteSpace: 'nowrap' }}>
                      {timeAgo(t.updatedAt ?? t.createdAt)}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default SupportQueuePage;
