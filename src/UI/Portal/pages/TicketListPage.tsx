import React, { useCallback, useEffect, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

/* ─── helpers ─────────────────────────────────────────────── */
// @ts-ignore
const apiUrl  = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce   = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs    = () => ({ 'X-WP-Nonce': nonce() });
const jsonHdrs = () => ({ 'X-WP-Nonce': nonce(), 'Content-Type': 'application/json' });

function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 60)  return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24)  return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}

function priorityLabel(p: number): string {
  if (p >= 4) return 'Critical';
  if (p === 3) return 'High';
  if (p === 2) return 'Medium';
  return 'Low';
}

function priorityColor(p: number): string {
  if (p >= 4) return '#dc2626';
  if (p === 3) return '#f59e0b';
  if (p === 2) return '#3b82f6';
  return '#6b7280';
}

/* ─── types ───────────────────────────────────────────────── */
interface Ticket {
  id: number;
  customerId: number | null;
  subject: string;
  description: string;
  status: string | null;
  priority: number;
  lifecycleOwner: string | null;
  referenceCode: string | null;
  slaName: string | null;
  createdAt: string;
  resolvedAt: string | null;
  projectId: number | null;
  isRollup?: boolean;
}

interface Customer {
  id: number;
  name: string;
}

interface StatusTab {
  label: string;
  value: string;
}

interface Props {
  title: string;
  lifecycleOwner: 'support' | 'project';
  statusTabs: StatusTab[];
  emptyMessage: string;
}

/* ─── detail panel ────────────────────────────────────────── */
const DetailPanel: React.FC<{
  ticket: Ticket;
  customerName: string;
  onClose: () => void;
  onResolve: (id: number) => void;
  resolving: boolean;
}> = ({ ticket, customerName, onClose, onResolve, resolving }) => {
  const statusBg: Record<string, string> = {
    open: '#dbeafe', in_progress: '#fef3c7', pending: '#f3e8ff',
    planned: '#dbeafe', blocked: '#fee2e2', resolved: '#dcfce7', closed: '#f1f5f9',
  };
  const statusFg: Record<string, string> = {
    open: '#1d4ed8', in_progress: '#92400e', pending: '#6d28d9',
    planned: '#1d4ed8', blocked: '#dc2626', resolved: '#15803d', closed: '#475569',
  };
  const st = (ticket.status ?? '').toLowerCase();
  const isTerminal = ['resolved', 'closed', 'cancelled'].includes(st);

  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.4)', zIndex: 1000, display: 'flex', alignItems: 'flex-start', justifyContent: 'flex-end' }}>
      <div style={{ width: 480, maxWidth: '100vw', height: '100vh', background: '#fff', boxShadow: '-4px 0 24px rgba(0,0,0,0.12)', display: 'flex', flexDirection: 'column', overflowY: 'auto' }}>
        {/* header */}
        <div style={{ padding: '20px 24px 16px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
          <div>
            <div style={{ fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 4 }}>
              {ticket.referenceCode ?? `#${ticket.id}`}
            </div>
            <div style={{ fontSize: 17, fontWeight: 700, color: '#0f172a', lineHeight: 1.3 }}>{ticket.subject}</div>
          </div>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b', fontSize: 20, lineHeight: 1 }}>✕</button>
        </div>

        {/* meta strip */}
        <div style={{ padding: '12px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'center' }}>
          <span style={{ padding: '2px 10px', borderRadius: 10, fontSize: 12, fontWeight: 600, background: statusBg[st] ?? '#f1f5f9', color: statusFg[st] ?? '#475569' }}>
            {ticket.status ?? '—'}
          </span>
          <span style={{ fontSize: 12, fontWeight: 700, color: priorityColor(ticket.priority) }}>
            {priorityLabel(ticket.priority)}
          </span>
          {customerName && <span style={{ fontSize: 12, color: '#64748b' }}>{customerName}</span>}
          {ticket.slaName && <span style={{ fontSize: 12, color: '#64748b' }}>SLA: {ticket.slaName}</span>}
          <span style={{ fontSize: 12, color: '#94a3b8', marginLeft: 'auto' }}>{relativeTime(ticket.createdAt)}</span>
        </div>

        {/* body */}
        <div style={{ padding: '16px 24px', flex: 1 }}>
          {ticket.description && (
            <p style={{ fontSize: 14, color: '#334155', lineHeight: 1.6, margin: 0, whiteSpace: 'pre-wrap' }}>{ticket.description}</p>
          )}
        </div>

        {/* actions */}
        {!isTerminal && (
          <div style={{ padding: '16px 24px', borderTop: '1px solid #e2e8f0' }}>
            <button
              onClick={() => onResolve(ticket.id)}
              disabled={resolving}
              style={{ width: '100%', padding: '10px 0', background: '#dc2626', color: '#fff', border: 'none', borderRadius: 8, fontSize: 14, fontWeight: 600, cursor: resolving ? 'not-allowed' : 'pointer', opacity: resolving ? 0.6 : 1 }}
            >
              {resolving ? 'Resolving…' : 'Resolve Ticket'}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

/* ─── main component ──────────────────────────────────────── */
const TicketListPage: React.FC<Props> = ({ title, lifecycleOwner, statusTabs, emptyMessage }) => {
  const user = usePortalUser();
  const [tickets, setTickets]       = useState<Ticket[]>([]);
  const [customers, setCustomers]   = useState<Record<number, string>>({});
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState<string | null>(null);
  const [activeTab, setActiveTab]   = useState('');
  const [selected, setSelected]     = useState<Ticket | null>(null);
  const [resolving, setResolving]   = useState(false);
  const [actionErr, setActionErr]   = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const url = `${apiUrl()}/tickets?assigned_user_id=${user.id}&lifecycle_owner=${lifecycleOwner}`;
      const res = await fetch(url, { headers: hdrs() });
      if (!res.ok) throw new Error(`Failed to load tickets (${res.status})`);
      const data: Ticket[] = await res.json();
      setTickets(data);

      // batch-load customer names
      const ids = [...new Set(data.map(t => t.customerId).filter(Boolean) as number[])];
      if (ids.length) {
        const cr = await fetch(`${apiUrl()}/customers`, { headers: hdrs() });
        if (cr.ok) {
          const cs: Customer[] = await cr.json();
          const map: Record<number, string> = {};
          cs.forEach(c => { map[c.id] = c.name; });
          setCustomers(map);
        }
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [user.id, lifecycleOwner]);

  useEffect(() => { load(); }, [load]);

  const resolve = async (id: number) => {
    setResolving(true);
    setActionErr(null);
    try {
      const res = await fetch(`${apiUrl()}/tickets/${id}/close`, { method: 'POST', headers: jsonHdrs() });
      if (!res.ok) throw new Error(await res.text());
      setSelected(null);
      await load();
    } catch (e) {
      setActionErr(e instanceof Error ? e.message : 'Failed to resolve ticket');
    } finally {
      setResolving(false);
    }
  };

  const filtered = activeTab
    ? tickets.filter(t => (t.status ?? '').toLowerCase() === activeTab)
    : tickets;

  const statusBg: Record<string, string> = {
    open: '#dbeafe', in_progress: '#fef3c7', pending: '#f3e8ff',
    planned: '#dbeafe', blocked: '#fee2e2', resolved: '#dcfce7', closed: '#f1f5f9',
  };
  const statusFg: Record<string, string> = {
    open: '#1d4ed8', in_progress: '#92400e', pending: '#6d28d9',
    planned: '#1d4ed8', blocked: '#dc2626', resolved: '#15803d', closed: '#475569',
  };

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      {/* title */}
      <div style={{ marginBottom: 20, display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>{title}</h1>
        <button onClick={load} style={{ background: 'none', border: '1px solid #cbd5e1', borderRadius: 6, padding: '4px 12px', fontSize: 13, color: '#64748b', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {/* tabs */}
      <div style={{ display: 'flex', gap: 4, marginBottom: 16, borderBottom: '2px solid #e2e8f0' }}>
        {statusTabs.map(tab => (
          <button
            key={tab.value}
            onClick={() => setActiveTab(tab.value)}
            style={{
              padding: '8px 16px', background: 'none', border: 'none', cursor: 'pointer',
              fontSize: 14, fontWeight: 600,
              color: activeTab === tab.value ? '#2563eb' : '#64748b',
              borderBottom: activeTab === tab.value ? '2px solid #2563eb' : '2px solid transparent',
              marginBottom: -2,
            }}
          >
            {tab.label}
            {tab.value === '' && (
              <span style={{ marginLeft: 6, fontSize: 12, fontWeight: 700, background: '#e2e8f0', color: '#475569', padding: '1px 6px', borderRadius: 8 }}>
                {tickets.length}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* error */}
      {(error || actionErr) && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>
          {error ?? actionErr}
        </div>
      )}

      {/* loading */}
      {loading && (
        <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', fontSize: 14 }}>Loading…</div>
      )}

      {/* empty */}
      {!loading && !error && filtered.length === 0 && (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8', fontSize: 14 }}>{emptyMessage}</div>
      )}

      {/* list */}
      {!loading && filtered.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {filtered.map(ticket => {
            const st = (ticket.status ?? '').toLowerCase();
            const customerName = ticket.customerId ? (customers[ticket.customerId] ?? '') : '';
            return (
              <button
                key={ticket.id}
                onClick={() => setSelected(ticket)}
                style={{
                  background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '14px 16px',
                  textAlign: 'left', cursor: 'pointer', width: '100%',
                  display: 'flex', gap: 16, alignItems: 'flex-start',
                  transition: 'box-shadow 0.12s',
                  boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                }}
                onMouseEnter={e => (e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)')}
                onMouseLeave={e => (e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.04)')}
              >
                {/* priority bar */}
                <div style={{ width: 4, borderRadius: 4, background: priorityColor(ticket.priority), flexShrink: 0, alignSelf: 'stretch' }} />

                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8, marginBottom: 4 }}>
                    <div>
                      <span style={{ fontSize: 11, fontWeight: 700, color: '#64748b', fontFamily: 'monospace', marginRight: 8 }}>
                        {ticket.referenceCode ?? `#${ticket.id}`}
                      </span>
                      {ticket.isRollup && (
                        <span style={{ fontSize: 10, fontWeight: 700, background: '#f1f5f9', color: '#475569', padding: '1px 6px', borderRadius: 6, marginRight: 6 }}>ROLLUP</span>
                      )}
                      <span style={{ padding: '2px 8px', borderRadius: 8, fontSize: 11, fontWeight: 600, background: statusBg[st] ?? '#f1f5f9', color: statusFg[st] ?? '#475569' }}>
                        {ticket.status ?? '—'}
                      </span>
                    </div>
                    <span style={{ fontSize: 11, color: '#94a3b8', whiteSpace: 'nowrap', flexShrink: 0 }}>{relativeTime(ticket.createdAt)}</span>
                  </div>
                  <div style={{ fontSize: 15, fontWeight: 600, color: '#1e293b', marginBottom: 4, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {ticket.subject}
                  </div>
                  <div style={{ display: 'flex', gap: 12, fontSize: 12, color: '#64748b' }}>
                    {customerName && <span>{customerName}</span>}
                    {ticket.slaName && <span>SLA: {ticket.slaName}</span>}
                    <span style={{ color: priorityColor(ticket.priority), fontWeight: 600 }}>{priorityLabel(ticket.priority)}</span>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}

      {/* detail panel */}
      {selected && (
        <DetailPanel
          ticket={selected}
          customerName={selected.customerId ? (customers[selected.customerId] ?? '') : ''}
          onClose={() => setSelected(null)}
          onResolve={resolve}
          resolving={resolving}
        />
      )}
    </div>
  );
};

export default TicketListPage;
