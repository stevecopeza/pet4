import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Customer, Ticket } from '../types';

// @ts-ignore
const apiUrl  = () => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce   = () => (window.petSettings?.nonce ?? '') as string;
const hdrs    = () => ({ 'X-WP-Nonce': nonce() });

interface Project { id: number; name: string; customerId: number; }

function statusBadgeClass(status: string): string {
  const s = status.toLowerCase();
  if (s === 'planned') return 'status-planned';
  if (s === 'in_progress' || s === 'open') return 'status-open';
  if (s === 'completed' || s === 'resolved' || s === 'closed') return 'status-resolved';
  return 'status-' + s;
}

function minsLabel(mins: number | null | undefined): string {
  if (!mins) return '—';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`;
}

function centsLabel(cents: number | null | undefined): string {
  if (!cents) return '—';
  return `R${(cents / 100).toLocaleString('en-ZA', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

const Fulfillments: React.FC = () => {
  const [tickets, setTickets]     = useState<Ticket[]>([]);
  const [projects, setProjects]   = useState<Project[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('active');
  const [search, setSearch]       = useState('');
  const [expandedProjects, setExpandedProjects] = useState<Set<number>>(new Set());

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [ticketsRes, projectsRes, customersRes] = await Promise.all([
        fetch(`${apiUrl()}/tickets?lifecycle_owner=project`, { headers: hdrs() }),
        fetch(`${apiUrl()}/projects`, { headers: hdrs() }),
        fetch(`${apiUrl()}/customers`, { headers: hdrs() }),
      ]);
      if (!ticketsRes.ok) throw new Error(`Failed to load tickets (${ticketsRes.status})`);
      const allTickets: Ticket[] = await ticketsRes.json();
      // Only show source_type=quote_component tickets (i.e. provisioned from quote acceptance)
      setTickets(allTickets.filter(t => t.sourceType === 'quote_component'));
      if (projectsRes.ok) setProjects(await projectsRes.json());
      if (customersRes.ok) setCustomers(await customersRes.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const projectById  = useMemo(() => new Map(projects.map(p => [p.id, p])), [projects]);
  const customerById = useMemo(() => new Map(customers.map(c => [c.id, c.name])), [customers]);

  const filteredTickets = useMemo(() => {
    const q = search.trim().toLowerCase();
    return tickets.filter(t => {
      if (statusFilter === 'active') {
        const s = (t.status ?? '').toLowerCase();
        if (s === 'completed' || s === 'resolved' || s === 'closed' || s === 'cancelled') return false;
      } else if (statusFilter !== 'all') {
        if ((t.status ?? '').toLowerCase() !== statusFilter) return false;
      }
      if (!q) return true;
      const proj = t.projectId ? projectById.get(t.projectId) : null;
      const cust = proj ? (customerById.get(proj.customerId) ?? '') : '';
      const text = `${t.subject} ${proj?.name ?? ''} ${cust} #${t.id}`.toLowerCase();
      return text.includes(q);
    });
  }, [tickets, statusFilter, search, projectById, customerById]);

  // Group by project
  const byProject = useMemo(() => {
    const map = new Map<number | null, Ticket[]>();
    for (const t of filteredTickets) {
      const pid = t.projectId ?? null;
      const existing = map.get(pid) ?? [];
      existing.push(t);
      map.set(pid, existing);
    }
    return map;
  }, [filteredTickets]);

  const sortedProjectIds = useMemo(() => {
    const pids = [...byProject.keys()];
    return pids.sort((a, b) => {
      if (a === null && b === null) return 0;
      if (a === null) return 1;
      if (b === null) return -1;
      const pa = projectById.get(a);
      const pb = projectById.get(b);
      return (pa?.name ?? '').localeCompare(pb?.name ?? '');
    });
  }, [byProject, projectById]);

  const toggleProject = (pid: number | null) => {
    const key = pid ?? -1;
    setExpandedProjects(prev => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  const isExpanded = (pid: number | null) => expandedProjects.has(pid ?? -1);

  // Summary stats
  const totalValue = filteredTickets.reduce((s, t) => s + (t.soldValueCents ?? 0), 0);
  const completedCount = filteredTickets.filter(t => {
    const s = (t.status ?? '').toLowerCase();
    return s === 'completed' || s === 'resolved' || s === 'closed';
  }).length;

  const STATUS_TABS = [
    { label: 'Active', value: 'active' },
    { label: 'All', value: 'all' },
    { label: 'Planned', value: 'planned' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
  ];

  return (
    <div className="pet-fulfillments" style={{ maxWidth: 1100 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <div>
          <h2 style={{ margin: 0 }}>Fulfillment Deliverables</h2>
          <p style={{ margin: '4px 0 0', color: '#646970', fontSize: 13 }}>
            Project tickets provisioned from accepted quote components — grouped by project.
          </p>
        </div>
        <button className="button" onClick={load}>↻ Refresh</button>
      </div>

      {/* Summary strip */}
      {!loading && !error && (
        <div style={{ display: 'flex', gap: 16, marginBottom: 16 }}>
          {[
            { label: 'Total tickets', value: filteredTickets.length },
            { label: 'Completed', value: `${completedCount} / ${filteredTickets.length}` },
            { label: 'Projects', value: sortedProjectIds.length },
            { label: 'Est. value', value: centsLabel(totalValue) },
          ].map(({ label, value }) => (
            <div key={label} style={{ background: '#fff', border: '1px solid #dcdcde', borderRadius: 6, padding: '10px 16px', minWidth: 120 }}>
              <div style={{ fontSize: 11, color: '#646970', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 4 }}>{label}</div>
              <div style={{ fontSize: 20, fontWeight: 700, color: '#1d2327' }}>{value}</div>
            </div>
          ))}
        </div>
      )}

      {/* Filters */}
      <div style={{ display: 'flex', gap: 10, marginBottom: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <input
          type="search"
          placeholder="Search tickets, projects, customers…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{ padding: '6px 10px', border: '1px solid #dcdcde', borderRadius: 4, fontSize: 13, minWidth: 260 }}
        />
        <div style={{ display: 'flex', gap: 0, border: '1px solid #dcdcde', borderRadius: 4, overflow: 'hidden' }}>
          {STATUS_TABS.map(tab => (
            <button
              key={tab.value}
              onClick={() => setStatusFilter(tab.value)}
              style={{
                padding: '6px 12px', border: 'none', background: statusFilter === tab.value ? '#2271b1' : '#f6f7f7',
                color: statusFilter === tab.value ? '#fff' : '#2c3338', cursor: 'pointer', fontSize: 12,
                fontWeight: statusFilter === tab.value ? 700 : 400,
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div style={{ background: '#fef8f8', border: '1px solid #f0b5b5', color: '#c02b2b', borderRadius: 4, padding: '10px 14px', fontSize: 13, marginBottom: 14 }}>
          {error}
        </div>
      )}
      {loading && <div style={{ padding: '40px 0', textAlign: 'center', color: '#646970' }}>Loading…</div>}

      {!loading && sortedProjectIds.length === 0 && !error && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#8c8f94', fontSize: 14 }}>
          {search ? 'No results match your search.' : 'No fulfillment deliverables found.'}
        </div>
      )}

      {/* Project groups */}
      {!loading && sortedProjectIds.map(pid => {
        const projectTickets = byProject.get(pid) ?? [];
        const proj = pid ? projectById.get(pid) : null;
        const cust = proj ? (customerById.get(proj.customerId) ?? `Customer #${proj.customerId}`) : 'No project';
        const projLabel = proj ? proj.name : '(Unlinked)';
        const quoteId = projectTickets[0]?.quoteId;
        const expanded = isExpanded(pid);

        const rootTickets = projectTickets.filter(t => !t.parentTicketId || !projectTickets.some(p => p.id === t.parentTicketId));
        const childrenOf = (id: number) => projectTickets.filter(t => t.parentTicketId === id);
        const projCompleted = projectTickets.filter(t => {
          const s = (t.status ?? '').toLowerCase();
          return s === 'completed' || s === 'resolved' || s === 'closed';
        }).length;
        const projValue = projectTickets.reduce((s, t) => s + (t.soldValueCents ?? 0), 0);

        const renderTicketRow = (t: Ticket, depth = 0): React.ReactNode => {
          const children = childrenOf(Number(t.id));
          return (
            <React.Fragment key={t.id}>
              <tr style={{ background: depth > 0 ? '#f9f9f9' : '#fff' }}>
                <td style={{ paddingLeft: 12 + depth * 20, borderBottom: '1px solid #f0f0f1' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    {t.isRollup ? <span title="Rollup" style={{ color: '#646970', fontSize: 11 }}>▾</span> : depth > 0 ? <span style={{ color: '#a0a5aa', fontSize: 11 }}>└</span> : null}
                    <span style={{ fontSize: 13, color: '#1d2327' }}>{t.subject}</span>
                  </div>
                  <div style={{ fontSize: 11, color: '#8c8f94', marginTop: 2, paddingLeft: t.isRollup || depth > 0 ? 16 : 0 }}>
                    #{t.id}{t.quoteId ? ` · Quote #${t.quoteId}` : ''}
                  </div>
                </td>
                <td style={{ borderBottom: '1px solid #f0f0f1', whiteSpace: 'nowrap' }}>
                  <span className={`pet-status-badge ${statusBadgeClass(t.status ?? '')}`}>{t.status}</span>
                </td>
                <td style={{ borderBottom: '1px solid #f0f0f1', color: '#646970', fontSize: 12, whiteSpace: 'nowrap' }}>
                  {minsLabel(t.estimatedMinutes)}
                </td>
                <td style={{ borderBottom: '1px solid #f0f0f1', color: '#646970', fontSize: 12, whiteSpace: 'nowrap' }}>
                  {t.soldValueCents ? centsLabel(t.soldValueCents) : '—'}
                </td>
                <td style={{ borderBottom: '1px solid #f0f0f1' }}>
                  {pid && (
                    <a
                      href={`?page=pet-delivery#project=${pid}`}
                      className="button button-small"
                      style={{ fontSize: 11 }}
                    >
                      View in Project
                    </a>
                  )}
                </td>
              </tr>
              {children.map(c => renderTicketRow(c, depth + 1))}
            </React.Fragment>
          );
        };

        return (
          <div key={String(pid)} style={{ border: '1px solid #dcdcde', borderRadius: 6, marginBottom: 12, overflow: 'hidden' }}>
            {/* Project header row */}
            <button
              onClick={() => toggleProject(pid)}
              style={{
                width: '100%', textAlign: 'left', padding: '10px 14px',
                background: '#f6f7f7', border: 'none', cursor: 'pointer',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                borderBottom: expanded ? '1px solid #dcdcde' : 'none',
              }}
            >
              <div>
                <span style={{ fontWeight: 700, fontSize: 14, color: '#1d2327', marginRight: 10 }}>{projLabel}</span>
                <span style={{ fontSize: 12, color: '#646970' }}>{cust}</span>
                {quoteId && <span style={{ fontSize: 11, color: '#2271b1', marginLeft: 10 }}>Quote #{quoteId}</span>}
              </div>
              <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                <span style={{ fontSize: 12, color: '#646970' }}>{projCompleted}/{projectTickets.length} complete</span>
                {projValue > 0 && <span style={{ fontSize: 12, color: '#646970' }}>{centsLabel(projValue)}</span>}
                <span style={{ fontSize: 11, color: '#646970' }}>{expanded ? '▲' : '▼'}</span>
              </div>
            </button>

            {expanded && (
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                  <tr style={{ background: '#f0f0f1' }}>
                    <th style={{ textAlign: 'left', padding: '6px 12px', fontSize: 11, fontWeight: 700, color: '#646970', textTransform: 'uppercase' }}>Ticket</th>
                    <th style={{ textAlign: 'left', padding: '6px 8px', fontSize: 11, fontWeight: 700, color: '#646970', textTransform: 'uppercase', whiteSpace: 'nowrap' }}>Status</th>
                    <th style={{ textAlign: 'left', padding: '6px 8px', fontSize: 11, fontWeight: 700, color: '#646970', textTransform: 'uppercase', whiteSpace: 'nowrap' }}>Estimate</th>
                    <th style={{ textAlign: 'left', padding: '6px 8px', fontSize: 11, fontWeight: 700, color: '#646970', textTransform: 'uppercase', whiteSpace: 'nowrap' }}>Value</th>
                    <th style={{ padding: '6px 8px' }} />
                  </tr>
                </thead>
                <tbody>
                  {rootTickets.map(t => renderTicketRow(t, 0))}
                </tbody>
              </table>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default Fulfillments;
