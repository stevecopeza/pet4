import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
// @ts-ignore
const wpUserId = (): string => String((window.petSettings?.currentUserId ?? 0));
const hdrs     = () => ({ 'X-WP-Nonce': nonce() });

interface Project {
  id: number;
  name: string;
  customerId: number;
  state: string;
  startDate?: string | null;
  endDate?: string | null;
}

interface Customer {
  id: number;
  name: string;
}

interface Ticket {
  id: number;
  subject: string;
  status: string;
  projectId?: number | null;
  assignedUserId?: string | null;
  parentTicketId?: number | null;
  isRollup?: boolean;
  estimatedMinutes?: number | null;
  soldValueCents?: number | null;
}

function statusBadgeStyle(status: string): React.CSSProperties {
  const s = status.toLowerCase();
  if (s === 'planned') return { background: '#eff6ff', color: '#2563eb' };
  if (s === 'in_progress' || s === 'open') return { background: '#fff7ed', color: '#c2410c' };
  if (s === 'completed' || s === 'resolved' || s === 'closed') return { background: '#f0fdf4', color: '#16a34a' };
  if (s === 'cancelled') return { background: '#fef2f2', color: '#dc2626' };
  return { background: '#f1f5f9', color: '#64748b' };
}

function minsLabel(mins: number | null | undefined): string {
  if (!mins) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`;
}

const STATUS_TABS = [
  { label: 'Active', value: 'active' },
  { label: 'All', value: 'all' },
  { label: 'Completed', value: 'completed' },
];

const ProjectsPage: React.FC = () => {
  const user = usePortalUser();
  const [projects, setProjects]     = useState<Project[]>([]);
  const [customers, setCustomers]   = useState<Customer[]>([]);
  const [tickets, setTickets]       = useState<Ticket[]>([]);
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('active');
  const [search, setSearch]         = useState('');
  const [expanded, setExpanded]     = useState<Set<number>>(new Set());

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [projRes, custRes, tickRes] = await Promise.all([
        fetch(`${apiUrl()}/projects`, { headers: hdrs() }),
        fetch(`${apiUrl()}/customers`, { headers: hdrs() }),
        fetch(`${apiUrl()}/tickets?lifecycle_owner=project`, { headers: hdrs() }),
      ]);
      if (!projRes.ok) throw new Error(`Failed to load projects (${projRes.status})`);
      const allProjects: Project[] = await projRes.json();
      const allTickets: Ticket[]   = tickRes.ok ? await tickRes.json() : [];
      const allCustomers: Customer[] = custRes.ok ? await custRes.json() : [];

      // For non-managers: scope to projects where user has assigned tickets
      if (!user.isManager && !user.isAdmin) {
        const myTickets = allTickets.filter(t => t.assignedUserId === wpUserId());
        const myProjectIds = new Set(myTickets.map(t => t.projectId).filter(Boolean) as number[]);
        setProjects(allProjects.filter(p => myProjectIds.has(p.id)));
        setTickets(myTickets);
      } else {
        setProjects(allProjects);
        setTickets(allTickets);
      }
      setCustomers(allCustomers);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [user.isManager, user.isAdmin]);

  useEffect(() => { load(); }, [load]);

  const customerById = useMemo(() => new Map(customers.map(c => [c.id, c.name])), [customers]);
  const ticketsByProject = useMemo(() => {
    const map = new Map<number, Ticket[]>();
    for (const t of tickets) {
      if (!t.projectId) continue;
      const existing = map.get(t.projectId) ?? [];
      existing.push(t);
      map.set(t.projectId, existing);
    }
    return map;
  }, [tickets]);

  const filteredProjects = useMemo(() => {
    const q = search.trim().toLowerCase();
    return projects.filter(p => {
      const state = p.state.toLowerCase();
      if (statusFilter === 'active') {
        if (state === 'completed' || state === 'archived' || state === 'cancelled') return false;
      } else if (statusFilter === 'completed') {
        if (state !== 'completed') return false;
      }
      if (!q) return true;
      const custName = customerById.get(p.customerId) ?? '';
      return `${p.name} ${custName}`.toLowerCase().includes(q);
    });
  }, [projects, statusFilter, search, customerById]);

  const toggleProject = (id: number) => {
    setExpanded(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <div style={{ maxWidth: 1000, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <div>
          <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>
            {user.isManager || user.isAdmin ? 'Projects' : 'My Projects'}
          </h1>
          {!user.isManager && !user.isAdmin && (
            <p style={{ margin: '4px 0 0', color: '#64748b', fontSize: 13 }}>Projects with tickets assigned to you.</p>
          )}
        </div>
        <button onClick={load} style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {/* Filters */}
      <div style={{ display: 'flex', gap: 10, marginBottom: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <input
          type="search"
          placeholder="Search projects or customers…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{ padding: '7px 12px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13, minWidth: 240, color: '#1e293b' }}
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

      {!loading && filteredProjects.length === 0 && !error && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          {search ? 'No projects match your search.' : 'No projects found.'}
        </div>
      )}

      {!loading && filteredProjects.map(project => {
        const projTickets = ticketsByProject.get(project.id) ?? [];
        const custName    = customerById.get(project.customerId) ?? `Customer #${project.customerId}`;
        const isExpanded  = expanded.has(project.id);
        const completed   = projTickets.filter(t => {
          const s = (t.status ?? '').toLowerCase();
          return s === 'completed' || s === 'resolved' || s === 'closed';
        }).length;
        const rootTickets = projTickets.filter(t => !t.parentTicketId || !projTickets.some(p => p.id === t.parentTicketId));
        const childrenOf  = (id: number) => projTickets.filter(t => t.parentTicketId === id);

        const renderTicket = (t: Ticket, depth = 0): React.ReactNode => (
          <React.Fragment key={t.id}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: `8px ${16 + depth * 20}px`, borderBottom: '1px solid #f8fafc', fontSize: 13 }}>
              {depth > 0 && <span style={{ color: '#cbd5e1', fontSize: 11 }}>└</span>}
              {t.isRollup && depth === 0 && <span style={{ color: '#94a3b8', fontSize: 11 }}>▾</span>}
              <span style={{ flex: 1, color: '#1e293b' }}>{t.subject}</span>
              <span style={{ fontSize: 11, color: '#94a3b8' }}>#{t.id}</span>
              {t.estimatedMinutes && <span style={{ fontSize: 11, color: '#64748b' }}>{minsLabel(t.estimatedMinutes)}</span>}
              <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 10, ...statusBadgeStyle(t.status ?? '') }}>
                {t.status}
              </span>
            </div>
            {childrenOf(Number(t.id)).map(c => renderTicket(c, depth + 1))}
          </React.Fragment>
        );

        return (
          <div key={project.id} style={{ border: '1px solid #e2e8f0', borderRadius: 10, marginBottom: 10, overflow: 'hidden' }}>
            <button
              onClick={() => toggleProject(project.id)}
              style={{ width: '100%', textAlign: 'left', padding: '12px 16px', background: '#f8fafc', border: 'none', cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: isExpanded ? '1px solid #e2e8f0' : 'none' }}
            >
              <div>
                <span style={{ fontWeight: 700, fontSize: 14, color: '#0f172a', marginRight: 10 }}>{project.name}</span>
                <span style={{ fontSize: 12, color: '#64748b' }}>{custName}</span>
              </div>
              <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                <span style={{ fontSize: 12, ...statusBadgeStyle(project.state), padding: '2px 10px', borderRadius: 10, fontWeight: 600 }}>{project.state}</span>
                {projTickets.length > 0 && (
                  <span style={{ fontSize: 12, color: '#64748b' }}>{completed}/{projTickets.length} tickets done</span>
                )}
                <span style={{ fontSize: 11, color: '#94a3b8' }}>{isExpanded ? '▲' : '▼'}</span>
              </div>
            </button>

            {isExpanded && (
              <div>
                {projTickets.length === 0 ? (
                  <div style={{ padding: '20px 16px', color: '#94a3b8', fontSize: 13, textAlign: 'center' }}>No tickets found for this project.</div>
                ) : (
                  rootTickets.map(t => renderTicket(t, 0))
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default ProjectsPage;
