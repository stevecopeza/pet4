import React, { useEffect, useMemo, useState, useCallback } from 'react';
import '../dashboard-styles.css';
import { Ticket, Employee } from '../types';
import TicketDetails from './TicketDetails';

/* ============================================================
   Types
   ============================================================ */
type QueueRow = {
  queue_key: string;
  label: string;
  visibility_scope: 'SELF' | 'TEAM' | 'MANAGERIAL' | 'ADMIN';
  count: number;
};

type QueueItem = {
  id: string;
  source_type: string;
  source_id: string;
  reference_code: string;
  title: string | null;
  customer_id: number | null;
  site_id: number | null;
  status: string | null;
  priority: number;
  assignment_mode: string | null;
  department_id: string | null;
  assigned_team_id: string | null;
  assigned_user_id: string | null;
  queue_key: string | null;
  created_at: string;
  updated_at: string;
  due_at: string | null;
  sla_state: string | null;
  project_id: number | null;
  visibility_scope: string;
  routing_reason: string | null;
};

type TeamSummary = {
  team_id: number;
  team_label: string;
  visibility_scope: string;
  counts: { team_queue: number; user_assigned: number; unrouted: number; total: number };
  sla: { breached: number; risk: number };
  aging: { lt_2_days: number; days_2_to_7: number; days_7_to_14: number; gt_14_days: number };
  workload_per_technician: Array<{ wp_user_id: string; count: number }>;
  unresolved_escalations: { total_open: number; by_severity: Record<string, number> };
};

/* ============================================================
   Helpers
   ============================================================ */
// @ts-ignore
const getApiUrl = (): string => window.petSettings?.apiUrl ?? '';
// @ts-ignore
const getNonce = (): string => window.petSettings?.nonce ?? '';
// @ts-ignore
const getCurrentUserId = (): string => String(window.petSettings?.currentUserId ?? '');

const hdrs = () => ({ 'X-WP-Nonce': getNonce() });
const jsonHdrs = () => ({ 'X-WP-Nonce': getNonce(), 'Content-Type': 'application/json' });

const slaClass = (state: string | null): string => {
  if (!state) return 'severity-unassigned';
  const s = state.toLowerCase();
  if (s === 'breached' || s === 'critical') return 'severity-breached';
  if (s === 'warning' || s === 'risk' || s === 'at_risk') return 'severity-warning';
  if (s === 'ok' || s === 'achieved' || s === 'healthy') return 'severity-info';
  return 'severity-unassigned';
};

const slaTimerClass = (state: string | null): string => {
  if (!state) return '';
  const s = state.toLowerCase();
  if (s === 'breached' || s === 'critical') return 'red';
  if (s === 'warning' || s === 'risk' || s === 'at_risk') return 'amber';
  return 'green';
};

const slaLabel = (state: string | null): string => {
  if (!state) return 'No SLA';
  const s = state.toLowerCase();
  if (s === 'breached' || s === 'critical') return 'Breached';
  if (s === 'warning' || s === 'risk' || s === 'at_risk') return 'At Risk';
  if (s === 'ok' || s === 'achieved' || s === 'healthy') return 'Healthy';
  return state;
};

const modeLabel = (mode: string | null): string => {
  if (!mode) return '\u2014';
  if (mode === 'TEAM_QUEUE') return 'Team Queue';
  if (mode === 'USER_ASSIGNED') return 'Assigned';
  if (mode === 'UNROUTED') return 'Unrouted';
  return mode;
};

const mapQueuePriorityToTicketPriority = (priority: number): string => {
  if (priority >= 90) return 'urgent';
  if (priority >= 70) return 'high';
  if (priority >= 40) return 'medium';
  return 'low';
};

const relativeTime = (iso: string | null): string => {
  if (!iso) return '\u2014';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
};

const formatDueAt = (dueAt: string | null): string => {
  if (!dueAt) return '\u2014';
  const diff = new Date(dueAt).getTime() - Date.now();
  const mins = Math.round(diff / 60000);
  if (mins < 0) {
    const over = Math.abs(mins);
    if (over < 60) return `-${over}m`;
    return `-${Math.floor(over / 60)}h ${over % 60}m`;
  }
  if (mins < 60) return `${mins}m`;
  return `${Math.floor(mins / 60)}h ${mins % 60}m`;
};

/* ============================================================
   Component
   ============================================================ */
const SupportOperational = () => {
  const [queues, setQueues] = useState<QueueRow[]>([]);
  const [selectedQueueKey, setSelectedQueueKey] = useState<string>('');
  const [items, setItems] = useState<QueueItem[]>([]);
  const [summary, setSummary] = useState<TeamSummary | null>(null);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loadingQueues, setLoadingQueues] = useState(true);
  const [loadingItems, setLoadingItems] = useState(false);
  const [loadingSummary, setLoadingSummary] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [detailTicket, setDetailTicket] = useState<Ticket | null>(null);
  const [assigningId, setAssigningId] = useState<string | null>(null);
  const [assignTarget, setAssignTarget] = useState<string>('');

  const selectedQueue = useMemo(
    () => queues.find((q) => q.queue_key === selectedQueueKey) || null,
    [queues, selectedQueueKey],
  );

  const isManagerView = useMemo(
    () => selectedQueue?.visibility_scope === 'MANAGERIAL' || selectedQueue?.visibility_scope === 'ADMIN',
    [selectedQueue],
  );

  const employeeMap = useMemo(() => {
    const m = new Map<string, string>();
    for (const e of employees) {
      m.set(String(e.wpUserId), `${e.firstName} ${e.lastName}`);
    }
    return m;
  }, [employees]);

  const supportEmployees = useMemo(
    () => employees.filter((e) => e.status === 'active'),
    [employees],
  );

  /* --- Fetchers --- */
  const fetchQueues = useCallback(async () => {
    try {
      setLoadingQueues(true);
      setError(null);
      const res = await fetch(`${getApiUrl()}/support/queue`, { headers: hdrs() });
      if (!res.ok) {
        if (res.status === 404) {
          setError('Support operational UX is disabled (feature flag off)');
          setQueues([]);
          return;
        }
        throw new Error('Failed to fetch support queues');
      }
      const data = await res.json();
      const q: QueueRow[] = data.queues || [];
      setQueues(q);
      const preferred = `support:user:${getCurrentUserId()}`;
      const def = q.find((r) => r.queue_key === preferred)?.queue_key || q[0]?.queue_key || '';
      setSelectedQueueKey(def);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load queues');
    } finally {
      setLoadingQueues(false);
    }
  }, []);

  const fetchQueueItems = useCallback(async (queueKey: string) => {
    try {
      setLoadingItems(true);
      setError(null);
      const res = await fetch(`${getApiUrl()}/work/queues/${encodeURIComponent(queueKey)}/items`, { headers: hdrs() });
      if (!res.ok) throw new Error('Failed to fetch queue items');
      const data: Omit<QueueItem, 'id'>[] = await res.json();
      setItems(data.map((it) => ({ ...it, id: `${it.source_type}:${it.source_id}` })));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load items');
    } finally {
      setLoadingItems(false);
    }
  }, []);

  const fetchTeamSummary = useCallback(async (teamId: number) => {
    try {
      setLoadingSummary(true);
      setSummary(null);
      const res = await fetch(`${getApiUrl()}/support/summary/team?team_id=${teamId}`, { headers: hdrs() });
      if (!res.ok) { setSummary(null); return; }
      setSummary(await res.json());
    } finally {
      setLoadingSummary(false);
    }
  }, []);

  const fetchEmployees = useCallback(async () => {
    try {
      const res = await fetch(`${getApiUrl()}/employees`, { headers: hdrs() });
      if (res.ok) setEmployees(await res.json());
    } catch { /* non-critical */ }
  }, []);

  /* --- Ticket actions --- */
  const pullTicket = async (sourceId: string) => {
    setActionError(null);
    try {
      const res = await fetch(`${getApiUrl()}/tickets/${sourceId}/pull`, { method: 'POST', headers: jsonHdrs() });
      if (!res.ok) throw new Error(await res.text());
      if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Pull failed');
    }
  };

  const returnToQueue = async (sourceId: string, queueId: string) => {
    setActionError(null);
    try {
      const res = await fetch(`${getApiUrl()}/tickets/${sourceId}/return-to-queue`, {
        method: 'POST', headers: jsonHdrs(), body: JSON.stringify({ queueId }),
      });
      if (!res.ok) throw new Error(await res.text());
      if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Return failed');
    }
  };

  const reassignTicket = async (sourceId: string, employeeUserId: string) => {
    setActionError(null);
    try {
      const res = await fetch(`${getApiUrl()}/tickets/${sourceId}/reassign`, {
        method: 'POST', headers: jsonHdrs(), body: JSON.stringify({ employeeUserId }),
      });
      if (!res.ok) throw new Error(await res.text());
      setAssigningId(null);
      setAssignTarget('');
      if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Reassign failed');
    }
  };

  /* --- Effects --- */
  useEffect(() => {
    if (!getApiUrl() || !getNonce()) { setError('API settings missing'); setLoadingQueues(false); return; }
    fetchQueues();
    fetchEmployees();
  }, []);

  useEffect(() => {
    if (!selectedQueueKey) return;
    fetchQueueItems(selectedQueueKey);
    setSummary(null);
    const parts = selectedQueueKey.split(':');
    if (parts.length === 3 && parts[0] === 'support' && parts[1] === 'team') {
      const teamId = Number(parts[2]);
      if (!Number.isNaN(teamId) && selectedQueue && (selectedQueue.visibility_scope === 'MANAGERIAL' || selectedQueue.visibility_scope === 'ADMIN')) {
        fetchTeamSummary(teamId);
      }
    }
  }, [selectedQueueKey]);

  /* --- Drill-through --- */
  const buildTicketFromQueueItem = (item: QueueItem): Ticket => ({
    id: Number(item.source_id),
    customerId: item.customer_id ?? 0,
    siteId: item.site_id ?? undefined,
    subject: item.title || `Ticket ${item.reference_code || item.source_id}`,
    description: item.routing_reason || 'Ticket details loaded from support queue.',
    status: item.status || 'open',
    priority: mapQueuePriorityToTicketPriority(item.priority),
    createdAt: item.created_at,
    resolvedAt: null,
    queueId: item.queue_key,
    lifecycleOwner: 'support',
  });

  const openTicketDetail = async (item: QueueItem) => {
    const sourceId = item.source_id;
    try {
      const res = await fetch(`${getApiUrl()}/tickets?status=`, { headers: hdrs() });
      if (res.ok) {
        const tickets: Ticket[] = await res.json();
        const t = tickets.find((tk) => String(tk.id) === sourceId);
        if (t) {
          setDetailTicket(t);
          return;
        }
      }
    } catch { /* noop */ }
    setDetailTicket(buildTicketFromQueueItem(item));
  };

  /* ============================================================
     Render: detail view
     ============================================================ */
  if (detailTicket) {
    return (
      <div className="pet-dashboards-fullscreen">
        <div className="pd-content">
          <TicketDetails ticket={detailTicket} onBack={() => { setDetailTicket(null); if (selectedQueueKey) fetchQueueItems(selectedQueueKey); }} />
        </div>
      </div>
    );
  }

  /* ============================================================
     Render: loading / error
     ============================================================ */
  if (loadingQueues) {
    return (
      <div className="pet-dashboards-fullscreen">
        <div className="pd-loading"><div className="pd-spinner" /><span>Loading support queues\u2026</span></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="pet-dashboards-fullscreen">
        <div className="pd-content"><div className="pd-error">{error}</div></div>
      </div>
    );
  }

  /* ============================================================
     Sub-components
     ============================================================ */
  const KpiCard = ({ value, label, color }: { value: number; label: string; color: string }) => (
    <div className={`pd-kpi-card ${color}`}>
      <div className="pd-kpi-value">{value}</div>
      <div className="pd-kpi-label">{label}</div>
    </div>
  );

  const AgingPanel = ({ aging }: { aging: TeamSummary['aging'] }) => {
    const buckets = [
      { label: '< 2 days', value: aging.lt_2_days, color: '#28a745' },
      { label: '2\u20137 days', value: aging.days_2_to_7, color: '#f0ad4e' },
      { label: '7\u201314 days', value: aging.days_7_to_14, color: '#dc3545' },
      { label: '> 14 days', value: aging.gt_14_days, color: '#8b0000' },
    ];
    const max = Math.max(...buckets.map((b) => b.value), 1);
    return (
      <div className="pd-card">
        <div className="pd-card-header">
          <div className="pd-card-title">Backlog Aging</div>
        </div>
        <div className="pd-priority-bars">
          {buckets.map((b) => (
            <div className="pd-bar-row" key={b.label}>
              <div className="pd-bar-label">{b.label}</div>
              <div className="pd-bar-track">
                <div className="pd-bar-fill" style={{ width: `${(b.value / max) * 100}%`, background: b.color }} />
              </div>
              <div className="pd-bar-count">{b.value}</div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  const WorkloadPanel = ({ workload }: { workload: TeamSummary['workload_per_technician'] }) => {
    const max = Math.max(...workload.map((w) => w.count), 1);
    return (
      <div className="pd-card">
        <div className="pd-card-header">
          <div className="pd-card-title">Workload per Technician</div>
        </div>
        {workload.length === 0 ? (
          <div className="pd-empty">No assigned tickets</div>
        ) : (
          <div className="pd-ts-staff-grid">
            {workload.map((w) => (
              <div className="pd-ts-staff-row" key={w.wp_user_id}>
                <div className="pd-ts-staff-info">
                  <div className="pd-ts-staff-name">{employeeMap.get(w.wp_user_id) || `User ${w.wp_user_id}`}</div>
                  <div className="pd-ts-staff-meta">{w.count} ticket{w.count !== 1 ? 's' : ''}</div>
                </div>
                <div className="pd-ts-bar-container">
                  <div className="pd-ts-bar" style={{ width: `${(w.count / max) * 100}%` }}>
                    <div className="pd-ts-bar-seg bar-locked" style={{ width: '100%' }} />
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  /* ============================================================
     Main render
     ============================================================ */
  return (
    <div className="pet-dashboards-fullscreen">
      {/* --- Header --- */}
      <div className="pd-header">
        <div>
          <h1>Support Operations</h1>
          <div className="pd-header-subtitle">{queues.length} queue{queues.length !== 1 ? 's' : ''} visible</div>
        </div>
        <div className="pd-header-right">
          <a className="pd-refresh-btn" href="/wp-admin/admin.php?page=pet-escalations" style={{ marginRight: 8 }}>
            Escalations
          </a>
          <button type="button" className="pd-refresh-btn" onClick={() => { fetchQueues(); if (selectedQueueKey) fetchQueueItems(selectedQueueKey); }}>
            \u21BB Refresh
          </button>
        </div>
      </div>

      {/* --- Content --- */}
      <div className="pd-content" style={{ display: 'flex', gap: 24, alignItems: 'flex-start' }}>
        {/* --- Queue sidebar --- */}
        <div style={{ width: 280, flexShrink: 0 }}>
          <div className="pd-section-title">Queues</div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {queues.map((q) => {
              const active = q.queue_key === selectedQueueKey;
              return (
                <button
                  key={q.queue_key}
                  type="button"
                  onClick={() => setSelectedQueueKey(q.queue_key)}
                  className={`pd-card pd-clickable${active ? ' pd-info' : ''}`}
                  style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    padding: '12px 14px', cursor: 'pointer', textAlign: 'left',
                    border: active ? '2px solid #0d6efd' : undefined,
                    background: active ? 'rgba(13,110,253,0.06)' : undefined,
                  }}
                >
                  <div>
                    <div style={{ fontWeight: 600, fontSize: '0.88rem', color: '#1a1a2e' }}>{q.label}</div>
                    <div style={{ fontSize: '0.72rem', color: '#888', marginTop: 2, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      {q.visibility_scope}
                    </div>
                  </div>
                  <div style={{ fontSize: '1.4rem', fontWeight: 700, color: q.count > 0 ? '#0d6efd' : '#adb5bd' }}>
                    {q.count}
                  </div>
                </button>
              );
            })}
          </div>
        </div>

        {/* --- Main panel --- */}
        <div style={{ flex: 1, minWidth: 0 }}>
          {/* Action error toast */}
          {actionError && (
            <div className="pd-error" style={{ marginBottom: 16, textAlign: 'left', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>{actionError}</span>
              <button type="button" className="pd-refresh-btn" onClick={() => setActionError(null)} style={{ color: '#dc3545', borderColor: '#fecaca' }}>Dismiss</button>
            </div>
          )}

          {/* KPI strip — manager view */}
          {isManagerView && summary && (
            <div className="pd-kpi-strip">
              <KpiCard value={summary.counts.team_queue} label="In Queue" color="blue" />
              <KpiCard value={summary.counts.user_assigned} label="User Assigned" color="green" />
              {summary.counts.unrouted > 0 && <KpiCard value={summary.counts.unrouted} label="Unrouted" color="amber" />}
              <KpiCard value={summary.sla.breached} label="SLA Breached" color="red" />
              {summary.sla.risk > 0 && <KpiCard value={summary.sla.risk} label="SLA At Risk" color="amber" />}
              {summary.unresolved_escalations.total_open > 0 && (
                <KpiCard value={summary.unresolved_escalations.total_open} label="Open Escalations" color="red" />
              )}
            </div>
          )}

          {/* Manager oversight panels */}
          {isManagerView && summary && (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 24 }}>
              <AgingPanel aging={summary.aging} />
              <WorkloadPanel workload={summary.workload_per_technician} />
            </div>
          )}

          {loadingSummary && isManagerView && !summary && (
            <div className="pd-card" style={{ marginBottom: 16, textAlign: 'center', padding: 20, color: '#888' }}>Loading team summary\u2026</div>
          )}

          {/* Queue header */}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
            <div className="pd-section-title" style={{ margin: 0 }}>
              {selectedQueue?.label || 'Queue'}
              <span className="pd-badge" style={{ marginLeft: 8 }}>{items.length}</span>
            </div>
            <button type="button" className="pd-refresh-btn" onClick={() => selectedQueueKey && fetchQueueItems(selectedQueueKey)} disabled={loadingItems} style={{ background: 'rgba(13,110,253,0.1)', color: '#0d6efd', borderColor: '#b6d4fe' }}>
              {loadingItems ? 'Loading\u2026' : '\u21BB Refresh'}
            </button>
          </div>

          {/* Queue items — attention cards */}
          {items.length === 0 && !loadingItems && (
            <div className="pd-empty">No items in this queue.</div>
          )}

          <div className="pd-attention-grid">
            {items.map((item) => {
              const isTicket = item.source_type === 'ticket';
              const showReassign = assigningId === item.id;

              return (
                <div
                  key={item.id}
                  className={`pd-attention-card ${slaClass(item.sla_state)}`}
                >
                  {/* SLA label */}
                  <div className="pd-sla-label">{slaLabel(item.sla_state)}</div>

                  {/* Body — clickable for drill-through */}
                  <div
                    className="pd-attention-body"
                    style={{ cursor: isTicket ? 'pointer' : undefined }}
                    onClick={() => { if (isTicket) openTicketDetail(item); }}
                  >
                    <div className="pd-attention-subject">
                      <span style={{ color: '#0d6efd', marginRight: 6 }}>{item.reference_code}</span>
                      {item.title || '(no title)'}
                    </div>
                    <div className="pd-attention-meta">
                      {item.customer_id ? `Customer #${item.customer_id}` : ''}
                      {item.customer_id && item.status ? ' \u00B7 ' : ''}
                      {item.status || ''}
                      {' \u00B7 '}
                      {modeLabel(item.assignment_mode)}
                      {item.assigned_user_id ? ` \u2014 ${employeeMap.get(item.assigned_user_id) || item.assigned_user_id}` : ''}
                    </div>

                    {/* Inline reassign form */}
                    {showReassign && (
                      <div className="pd-assign-controls" onClick={(e) => e.stopPropagation()}>
                        <div className="pd-assign-row">
                          <div className="pd-assign-label">Reassign to</div>
                          <select className="pd-assign-select" value={assignTarget} onChange={(e) => setAssignTarget(e.target.value)}>
                            <option value="">\u2014 select \u2014</option>
                            {supportEmployees.map((emp) => (
                              <option key={emp.wpUserId} value={String(emp.wpUserId)}>
                                {emp.firstName} {emp.lastName}
                              </option>
                            ))}
                          </select>
                        </div>
                        <div style={{ display: 'flex', gap: 8 }}>
                          <button
                            type="button"
                            className="pd-assign-pull-btn"
                            disabled={!assignTarget}
                            onClick={() => reassignTicket(item.source_id, assignTarget)}
                          >
                            Confirm
                          </button>
                          <button type="button" className="pd-refresh-btn" onClick={() => { setAssigningId(null); setAssignTarget(''); }} style={{ color: '#666', borderColor: '#d0d5dd' }}>
                            Cancel
                          </button>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Right column — timer + actions */}
                  <div className="pd-attention-right" onClick={(e) => e.stopPropagation()}>
                    {item.due_at && (
                      <div className={`pd-attention-timer ${slaTimerClass(item.sla_state)}`}>
                        {formatDueAt(item.due_at)}
                      </div>
                    )}
                    <div className="pd-attention-status">{relativeTime(item.created_at)}</div>

                    {/* Actions */}
                    {isTicket && (
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginTop: 8 }}>
                        <button type="button" className="pd-assign-pull-btn" style={{ fontSize: '0.72rem', padding: '4px 10px' }} onClick={() => pullTicket(item.source_id)}>
                          Pull
                        </button>
                        <button
                          type="button"
                          className="pd-refresh-btn"
                          style={{ fontSize: '0.72rem', padding: '4px 10px', color: '#666', borderColor: '#d0d5dd' }}
                          onClick={() => {
                            const teamKey = queues.find((q) => q.queue_key.startsWith('support:team:'))?.queue_key;
                            if (teamKey) {
                              const teamQueueId = teamKey.split(':')[2];
                              returnToQueue(item.source_id, teamQueueId);
                            } else {
                              setActionError('Team queue context unavailable');
                            }
                          }}
                        >
                          Return
                        </button>
                        {isManagerView && (
                          <button
                            type="button"
                            className="pd-refresh-btn"
                            style={{ fontSize: '0.72rem', padding: '4px 10px', color: '#6f42c1', borderColor: '#d8c4f7' }}
                            onClick={() => { setAssigningId(showReassign ? null : item.id); setAssignTarget(''); }}
                          >
                            {showReassign ? 'Cancel' : 'Reassign'}
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};

export default SupportOperational;

