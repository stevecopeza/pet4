import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';

type QueueDescriptor = {
  queue_key: string;
  label: string;
  visibility_scope: 'SELF' | 'TEAM' | 'MANAGERIAL' | 'ADMIN';
};

type QueueSummaryRow = {
  queue_key: string;
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

type Employee = {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  status: string;
};

type ActiveModal =
  | { type: 'close'; item: QueueItem }
  | { type: 'reassign'; item: QueueItem };

export const resolveReturnQueueId = (item: QueueItem, selectedQueueKey: string): string | null => {
  if (item.assigned_team_id) {
    return item.assigned_team_id;
  }
  if (selectedQueueKey.startsWith('support:team:')) {
    const parts = selectedQueueKey.split(':');
    return parts[2] || null;
  }
  return null;
};

/* --- Human-readable label maps --- */
const MODE_LABELS: Record<string, string> = {
  TEAM_QUEUE: 'Team Queue',
  USER_ASSIGNED: 'Assigned',
  UNROUTED: 'Unrouted',
};

const ROUTING_LABELS: Record<string, string> = {
  ticket_created: 'Ticket Created',
  ticket_assigned: 'Ticket Assigned',
  manual: 'Manual',
  escalation: 'Escalation',
  sla_breach: 'SLA Breach',
};

const slaTimerColor = (state: string | null): string => {
  if (!state) return 'inherit';
  const s = state.toLowerCase();
  if (s === 'breached' || s === 'critical') return '#dc3545';
  if (s === 'warning' || s === 'risk' || s === 'at_risk') return '#f0ad4e';
  if (s === 'ok' || s === 'achieved' || s === 'healthy') return '#28a745';
  return 'inherit';
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

// ── Inline modal overlay ───────────────────────────────────────────────────────

const overlayStyle: React.CSSProperties = {
  position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.45)',
  display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999,
};
const modalStyle: React.CSSProperties = {
  background: '#fff', borderRadius: 8, padding: 24, minWidth: 340, maxWidth: 480,
  boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
};
const modalTitleStyle: React.CSSProperties = {
  margin: '0 0 16px', fontSize: 16, fontWeight: 700,
};
const modalActionsStyle: React.CSSProperties = {
  display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 20,
};

// ── CloseModal ─────────────────────────────────────────────────────────────────

interface CloseModalProps {
  item: QueueItem;
  onConfirm: (resolution: string) => Promise<void>;
  onCancel: () => void;
}

const CloseModal: React.FC<CloseModalProps> = ({ item, onConfirm, onCancel }) => {
  const [resolution, setResolution] = useState('');
  const [acting, setActing] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const handleConfirm = async () => {
    try {
      setActing(true);
      setErr(null);
      await onConfirm(resolution);
    } catch (e: any) {
      setErr(e.message ?? 'Failed to close ticket');
      setActing(false);
    }
  };

  return (
    <div style={overlayStyle} onClick={(e) => { if (e.target === e.currentTarget) onCancel(); }}>
      <div style={modalStyle}>
        <p style={modalTitleStyle}>Resolve Ticket</p>
        <p style={{ margin: '0 0 12px', fontSize: 13, color: '#555' }}>
          Ticket <strong>{item.reference_code}</strong> — {item.title ?? '(no title)'}
        </p>
        <label style={{ display: 'block', fontSize: 12, fontWeight: 600, marginBottom: 4 }}>
          Resolution note (optional)
        </label>
        <textarea
          style={{ width: '100%', padding: '8px', borderRadius: 4, border: '1px solid #ccd', fontSize: 13, resize: 'vertical', minHeight: 64 }}
          placeholder="Describe how this was resolved…"
          value={resolution}
          onChange={(e) => setResolution(e.target.value)}
          autoFocus
        />
        {err && <p style={{ color: '#dc3545', fontSize: 12, margin: '8px 0 0' }}>{err}</p>}
        <div style={modalActionsStyle}>
          <button className="button" onClick={onCancel} disabled={acting}>Cancel</button>
          <button className="button button-primary" onClick={handleConfirm} disabled={acting}
            style={{ background: '#dc3545', borderColor: '#dc3545' }}>
            {acting ? 'Resolving…' : 'Resolve Ticket'}
          </button>
        </div>
      </div>
    </div>
  );
};

// ── ReassignModal ──────────────────────────────────────────────────────────────

interface ReassignModalProps {
  item: QueueItem;
  employees: Employee[];
  onConfirm: (employeeUserId: string) => Promise<void>;
  onCancel: () => void;
}

const ReassignModal: React.FC<ReassignModalProps> = ({ item, employees, onConfirm, onCancel }) => {
  const active = employees.filter(e => e.status === 'active');
  const [selectedUserId, setSelectedUserId] = useState<string>(active[0]?.wpUserId?.toString() ?? '');
  const [acting, setActing] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const handleConfirm = async () => {
    if (!selectedUserId) { setErr('Please select an agent.'); return; }
    try {
      setActing(true);
      setErr(null);
      await onConfirm(selectedUserId);
    } catch (e: any) {
      setErr(e.message ?? 'Failed to reassign ticket');
      setActing(false);
    }
  };

  return (
    <div style={overlayStyle} onClick={(e) => { if (e.target === e.currentTarget) onCancel(); }}>
      <div style={modalStyle}>
        <p style={modalTitleStyle}>Reassign Ticket</p>
        <p style={{ margin: '0 0 12px', fontSize: 13, color: '#555' }}>
          Ticket <strong>{item.reference_code}</strong> — {item.title ?? '(no title)'}
        </p>
        <label style={{ display: 'block', fontSize: 12, fontWeight: 600, marginBottom: 4 }}>
          Assign to agent
        </label>
        {active.length === 0 ? (
          <p style={{ fontSize: 13, color: '#888' }}>No active employees found.</p>
        ) : (
          <select
            style={{ width: '100%', padding: '6px 8px', borderRadius: 4, border: '1px solid #ccd', fontSize: 13 }}
            value={selectedUserId}
            onChange={(e) => setSelectedUserId(e.target.value)}
          >
            {active.map(emp => (
              <option key={emp.id} value={emp.wpUserId.toString()}>
                {emp.firstName} {emp.lastName}
              </option>
            ))}
          </select>
        )}
        {err && <p style={{ color: '#dc3545', fontSize: 12, margin: '8px 0 0' }}>{err}</p>}
        <div style={modalActionsStyle}>
          <button className="button" onClick={onCancel} disabled={acting}>Cancel</button>
          <button className="button button-primary" onClick={handleConfirm} disabled={acting || active.length === 0}>
            {acting ? 'Reassigning…' : 'Reassign'}
          </button>
        </div>
      </div>
    </div>
  );
};

// ── Main component ─────────────────────────────────────────────────────────────

const WorkItems = () => {
  const [queues, setQueues] = useState<QueueDescriptor[]>([]);
  const [queueCounts, setQueueCounts] = useState<Record<string, number>>({});
  const [selectedQueueKey, setSelectedQueueKey] = useState<string>('');
  const [items, setItems] = useState<QueueItem[]>([]);
  const [loadingQueues, setLoadingQueues] = useState(true);
  const [loadingItems, setLoadingItems] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [activeModal, setActiveModal] = useState<ActiveModal | null>(null);
  const [employees, setEmployees] = useState<Employee[]>([]);

  // @ts-ignore
  const currentUserId = window.petSettings?.currentUserId;
  // @ts-ignore
  const apiUrl: string = window.petSettings?.apiUrl ?? '';
  // @ts-ignore
  const nonce: string = window.petSettings?.nonce ?? '';

  const hdrs = () => ({ 'X-WP-Nonce': nonce });
  const jsonHdrs = () => ({ 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' });

  const fetchQueues = async () => {
    try {
      setLoadingQueues(true);

      const resQueues = await fetch(`${apiUrl}/work/queues`, { headers: hdrs() });
      if (!resQueues.ok) throw new Error('Failed to fetch queues');
      const qData = await resQueues.json();
      setQueues(qData);

      const resSummary = await fetch(`${apiUrl}/work/queues/summary`, { headers: hdrs() });
      if (resSummary.ok) {
        const sData: QueueSummaryRow[] = await resSummary.json();
        const counts: Record<string, number> = {};
        for (const row of sData) counts[row.queue_key] = row.count;
        setQueueCounts(counts);
      }

      const preferred = `support:user:${currentUserId}`;
      const defaultKey = qData.find((q: QueueDescriptor) => q.queue_key === preferred)?.queue_key || qData[0]?.queue_key || '';
      setSelectedQueueKey(defaultKey);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoadingQueues(false);
    }
  };

  const fetchQueueItems = async (queueKey: string) => {
    try {
      setLoadingItems(true);
      const res = await fetch(`${apiUrl}/work/queues/${encodeURIComponent(queueKey)}/items`, { headers: hdrs() });
      if (!res.ok) throw new Error('Failed to fetch queue items');
      const data: Omit<QueueItem, 'id'>[] = await res.json();
      setItems(data.map((it) => ({ ...it, id: `${it.source_type}:${it.source_id}` })));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch queue items');
    } finally {
      setLoadingItems(false);
    }
  };

  // Lazy-load employees (only needed for Reassign modal)
  const ensureEmployees = async () => {
    if (employees.length > 0) return;
    try {
      const res = await fetch(`${apiUrl}/employees`, { headers: hdrs() });
      if (res.ok) setEmployees(await res.json());
    } catch { /* non-critical */ }
  };

  /* --- Item actions --- */
  const pullItem = async (item: QueueItem) => {
    if (item.source_type !== 'ticket') return;
    setActionError(null);
    try {
      const queueId = item.assigned_team_id || (selectedQueueKey.startsWith('support:team:') ? selectedQueueKey.split(':')[2] : null);
      if (!queueId) {
        throw new Error('Queue context unavailable');
      }
      const res = await fetch(`${apiUrl}/tickets/${item.source_id}/pull`, { method: 'POST', headers: jsonHdrs() });
      if (!res.ok) throw new Error(await res.text());
      if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
    } catch (e) { setActionError(e instanceof Error ? e.message : 'Pull failed'); }
  };

  const returnItem = async (item: QueueItem) => {
    if (item.source_type !== 'ticket') return;
    setActionError(null);
    try {
      const queueId = resolveReturnQueueId(item, selectedQueueKey);
      if (!queueId) {
        throw new Error('Queue context unavailable');
      }
      const res = await fetch(`${apiUrl}/tickets/${item.source_id}/return-to-queue`, {
        method: 'POST', headers: jsonHdrs(), body: JSON.stringify({ queueId }),
      });
      if (!res.ok) throw new Error(await res.text());
      if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
    } catch (e) { setActionError(e instanceof Error ? e.message : 'Return failed'); }
  };

  const closeItem = async (item: QueueItem, resolution: string) => {
    const res = await fetch(`${apiUrl}/tickets/${item.source_id}/close`, {
      method: 'POST', headers: jsonHdrs(),
      body: JSON.stringify(resolution ? { resolution } : {}),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new Error((body as any)?.error?.message ?? (body as any)?.error ?? `Close failed (${res.status})`);
    }
    setActiveModal(null);
    if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
  };

  const reassignItem = async (item: QueueItem, employeeUserId: string) => {
    const res = await fetch(`${apiUrl}/tickets/${item.source_id}/reassign`, {
      method: 'POST', headers: jsonHdrs(),
      body: JSON.stringify({ employeeUserId }),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new Error((body as any)?.error?.message ?? (body as any)?.error ?? `Reassign failed (${res.status})`);
    }
    setActiveModal(null);
    if (selectedQueueKey) fetchQueueItems(selectedQueueKey);
  };

  /* --- Drill-through --- */
  const drillThrough = (item: QueueItem) => {
    if (item.source_type === 'ticket') {
      // Navigate to Support page with ticket hash
      const supportUrl = (window as any).petSettings?.supportUrl;
      if (supportUrl) {
        window.location.href = `${supportUrl}#ticket=${item.source_id}`;
      }
    }
  };

  useEffect(() => { fetchQueues(); }, []);
  useEffect(() => { if (selectedQueueKey) fetchQueueItems(selectedQueueKey); }, [selectedQueueKey]);

  const columns: Column<QueueItem>[] = [
    {
      key: 'reference_code',
      header: 'Ref',
      render: (val, item) => (
        <button
          type="button"
          className="button-link"
          style={{ fontWeight: 600, padding: 0 }}
          onClick={() => drillThrough(item)}
        >
          {String(val)}
        </button>
      ),
    },
    { key: 'title', header: 'Title' },
    {
      key: 'priority',
      header: 'Priority',
      render: (value) => (
        <span style={{
          fontWeight: 'bold',
          color: (value as number) > 80 ? '#d63638' : ((value as number) > 50 ? '#d46f15' : 'inherit'),
        }}>
          {Number(value).toFixed(1)}
        </span>
      ),
    },
    { key: 'status', header: 'Status' },
    {
      key: 'assignment_mode',
      header: 'Mode',
      render: (val) => <span>{MODE_LABELS[String(val)] || String(val || '\u2014')}</span>,
    },
    {
      key: 'routing_reason',
      header: 'Routing',
      render: (val) => <span>{ROUTING_LABELS[String(val)] || String(val || '\u2014')}</span>,
    },
    {
      key: 'due_at',
      header: 'SLA',
      render: (val, item) => (
        <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums', color: slaTimerColor(item.sla_state) }}>
          {formatDueAt(val as string | null)}
        </span>
      ),
    },
  ];

  // Tickets in terminal status can't be closed again
  const isTerminal = (item: QueueItem) =>
    ['resolved', 'closed', 'cancelled'].includes((item.status ?? '').toLowerCase());

  if (loadingQueues) return <div>Loading...</div>;
  if (error) return <div className="notice notice-error"><p>{error}</p></div>;

  return (
    <div className="wrap">
      {/* Modals */}
      {activeModal?.type === 'close' && (
        <CloseModal
          item={activeModal.item}
          onConfirm={(resolution) => closeItem(activeModal.item, resolution)}
          onCancel={() => setActiveModal(null)}
        />
      )}
      {activeModal?.type === 'reassign' && (
        <ReassignModal
          item={activeModal.item}
          employees={employees}
          onConfirm={(userId) => reassignItem(activeModal.item, userId)}
          onCancel={() => setActiveModal(null)}
        />
      )}

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2 style={{ margin: 0 }}>Work Queues</h2>
        <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
          <select
            value={selectedQueueKey}
            onChange={(e) => setSelectedQueueKey(e.target.value)}
            style={{ height: '30px', minWidth: 280 }}
          >
            {queues.map((q) => (
              <option key={q.queue_key} value={q.queue_key}>
                {q.label} ({queueCounts[q.queue_key] ?? 0})
              </option>
            ))}
          </select>
          <button className="button" onClick={() => selectedQueueKey && fetchQueueItems(selectedQueueKey)}>
            Refresh
          </button>
        </div>
      </div>

      {actionError && (
        <div className="notice notice-error" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <p>{actionError}</p>
          <button className="button button-small" onClick={() => setActionError(null)}>Dismiss</button>
        </div>
      )}

      {loadingItems && <div>Loading...</div>}

      <DataTable
        columns={columns}
        data={items}
        emptyMessage="No work items found."
        actions={(item) => {
          if (item.source_type !== 'ticket') return null;
          const terminal = isTerminal(item);
          const menuItems = [
            { type: 'action' as const, label: 'Pull to me', onClick: () => pullItem(item) },
            { type: 'action' as const, label: 'Return to Queue', onClick: () => returnItem(item) },
            { type: 'divider' as const },
            {
              type: 'action' as const,
              label: 'Reassign…',
              onClick: async () => {
                await ensureEmployees();
                setActiveModal({ type: 'reassign', item });
              },
            },
            {
              type: 'action' as const,
              label: 'Resolve…',
              danger: true,
              disabled: terminal,
              disabledReason: terminal ? 'Ticket is already in a terminal state' : undefined,
              onClick: () => setActiveModal({ type: 'close', item }),
            },
            { type: 'divider' as const },
            { type: 'action' as const, label: 'View', onClick: async () => { drillThrough(item); } },
          ];
          return <KebabMenu items={menuItems} />;
        }}
      />
    </div>
  );
};

export default WorkItems;
