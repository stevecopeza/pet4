import React, { useCallback, useEffect, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Ticket {
  id: number;
  subject: string;
  status: string | null;
  priority: number;
  referenceCode: string | null;
  lifecycleOwner: string | null;
  dueAt: string | null;
  customerId: number | null;
}

interface Customer { id: number; name: string; }

function priorityColor(p: number): string {
  if (p >= 4) return '#dc2626';
  if (p === 3) return '#f59e0b';
  if (p === 2) return '#3b82f6';
  return '#6b7280';
}

function dayLabel(dateStr: string): string {
  const d = new Date(dateStr);
  const today = new Date(); today.setHours(0,0,0,0);
  const target = new Date(d); target.setHours(0,0,0,0);
  const diff = Math.round((target.getTime() - today.getTime()) / 86400000);
  if (diff === 0) return 'Today';
  if (diff === 1) return 'Tomorrow';
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' });
}

function isOverdue(dueAt: string): boolean {
  return new Date(dueAt) < new Date();
}

const CalendarPage: React.FC = () => {
  const user = usePortalUser();
  const [tickets, setTickets]     = useState<Ticket[]>([]);
  const [customers, setCustomers] = useState<Record<number, string>>({});
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/tickets?assigned_user_id=${user.id}`, { headers: hdrs() });
      if (!res.ok) throw new Error(`Failed to load tickets (${res.status})`);
      const all: Ticket[] = await res.json();
      // Only keep tickets with a due date and not yet resolved/closed
      const withDue = all.filter(t => !!t.dueAt && !['resolved','closed','cancelled'].includes((t.status ?? '').toLowerCase()));
      withDue.sort((a, b) => new Date(a.dueAt!).getTime() - new Date(b.dueAt!).getTime());
      setTickets(withDue);

      const ids = [...new Set(withDue.map(t => t.customerId).filter(Boolean) as number[])];
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
  }, [user.id]);

  useEffect(() => { load(); }, [load]);

  const overdue  = tickets.filter(t => isOverdue(t.dueAt!));
  const upcoming = tickets.filter(t => !isOverdue(t.dueAt!));

  // Group upcoming by day label
  const groups: { day: string; items: Ticket[] }[] = [];
  for (const t of upcoming) {
    const day = dayLabel(t.dueAt!);
    const last = groups[groups.length - 1];
    if (last && last.day === day) last.items.push(t);
    else groups.push({ day, items: [t] });
  }

  const renderTicket = (t: Ticket, overdueBadge = false) => {
    const customerName = t.customerId ? (customers[t.customerId] ?? '') : '';
    const lcBadge = t.lifecycleOwner === 'project' ? 'Project' : 'Support';
    const lcColor = t.lifecycleOwner === 'project' ? { bg: '#eff6ff', fg: '#1d4ed8' } : { bg: '#faf5ff', fg: '#6d28d9' };
    return (
      <div key={t.id} style={{
        background: '#fff', border: '1px solid #e2e8f0', borderRadius: 8, padding: '12px 14px',
        display: 'flex', gap: 12, alignItems: 'flex-start',
        borderLeft: `4px solid ${priorityColor(t.priority)}`,
      }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 4, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 11, fontWeight: 700, color: '#64748b', fontFamily: 'monospace' }}>
              {t.referenceCode ?? `#${t.id}`}
            </span>
            <span style={{ fontSize: 11, fontWeight: 600, padding: '1px 7px', borderRadius: 8, background: lcColor.bg, color: lcColor.fg }}>
              {lcBadge}
            </span>
            {overdueBadge && (
              <span style={{ fontSize: 11, fontWeight: 700, padding: '1px 7px', borderRadius: 8, background: '#fef2f2', color: '#dc2626' }}>Overdue</span>
            )}
          </div>
          <div style={{ fontSize: 14, fontWeight: 600, color: '#1e293b', marginBottom: 2, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{t.subject}</div>
          {customerName && <div style={{ fontSize: 12, color: '#64748b' }}>{customerName}</div>}
        </div>
        <div style={{ fontSize: 12, color: overdueBadge ? '#dc2626' : '#64748b', whiteSpace: 'nowrap', flexShrink: 0, fontWeight: overdueBadge ? 700 : 400 }}>
          {new Date(t.dueAt!).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
        </div>
      </div>
    );
  };

  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 24 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Calendar</h1>
        <button onClick={load} style={{ background: 'none', border: '1px solid #cbd5e1', borderRadius: 6, padding: '4px 12px', fontSize: 13, color: '#64748b', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>{error}</div>
      )}

      {loading && <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', fontSize: 14 }}>Loading…</div>}

      {!loading && tickets.length === 0 && !error && (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8', fontSize: 14 }}>No upcoming work with due dates.</div>
      )}

      {/* overdue */}
      {!loading && overdue.length > 0 && (
        <div style={{ marginBottom: 28 }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: '#dc2626', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 10, display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#dc2626', display: 'inline-block' }} />
            Overdue — {overdue.length} item{overdue.length !== 1 ? 's' : ''}
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {overdue.map(t => renderTicket(t, true))}
          </div>
        </div>
      )}

      {/* upcoming grouped by day */}
      {!loading && groups.map(group => (
        <div key={group.day} style={{ marginBottom: 24 }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: '#475569', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 10, paddingBottom: 6, borderBottom: '1px solid #f1f5f9' }}>
            {group.day}
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {group.items.map(t => renderTicket(t, false))}
          </div>
        </div>
      ))}
    </div>
  );
};

export default CalendarPage;
