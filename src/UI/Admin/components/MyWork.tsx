import React, { useEffect, useMemo, useState } from 'react';
import { Project, Ticket } from '../types';
import { computeProjectHealth, computeTicketHealth } from '../healthCompute';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';

type MyWorkItemType = 'ticket' | 'project' | 'task';
type MyWorkGroup = 'needs-attention' | 'in-progress' | 'stable';

type MyWorkItem = {
  id: string;
  type: MyWorkItemType;
  group: MyWorkGroup;
  sortScore: number;
  title: string;
  context: string;
  statusLabel: string;
  riskLabel: string;
  onClick: () => void;
};
type WorkSignal = { severity?: string; message?: string };
type WorkItem = {
  id?: string;
  sourceType?: string;
  source_type?: string;
  sourceId?: string | number;
  source_id?: string | number;
  priorityScore?: number;
  priority_score?: number;
  slaTimeRemaining?: number | null;
  sla_time_remaining?: number | null;
  signals?: WorkSignal[];
};

const toneStyles: Record<MyWorkGroup, { border: string; background: string }> = {
  'needs-attention': { border: '#dc3545', background: '#fff5f5' },
  'in-progress': { border: '#0d6efd', background: '#f5f9ff' },
  stable: { border: '#adb5bd', background: '#f8f9fa' },
};

const MyWork: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [workItems, setWorkItems] = useState<WorkItem[]>([]);

  const currentUserId = String(window.petSettings?.currentUserId ?? '');

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        const apiUrl = window.petSettings?.apiUrl;
        const nonce = window.petSettings?.nonce;
        if (!apiUrl || !nonce) {
          throw new Error('API settings missing');
        }

        const [ticketRes, projectRes, workRes] = await Promise.all([
          fetch(`${apiUrl}/tickets`, {
            headers: { 'X-WP-Nonce': nonce },
          }).catch(() => null),
          fetch(`${apiUrl}/projects`, {
            headers: { 'X-WP-Nonce': nonce },
          }).catch(() => null),
          fetch(`${apiUrl}/work/my-items`, {
            headers: { 'X-WP-Nonce': nonce },
          }).catch(() => null),
        ]);

        if (ticketRes?.ok) {
          setTickets(await ticketRes.json());
        } else {
          setTickets([]);
        }

        if (projectRes?.ok) {
          setProjects(await projectRes.json());
        } else {
          setProjects([]);
        }
        if (workRes?.ok) {
          const payload = await workRes.json();
          setWorkItems(Array.isArray(payload) ? payload : []);
        } else {
          setWorkItems([]);
        }
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to load My Work');
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [currentUserId]);

  const items = useMemo<MyWorkItem[]>(() => {
    const byTicketId = new Map<number, Ticket>();
    const byProjectId = new Map<number, Project>();
    for (const ticket of tickets) byTicketId.set(Number(ticket.id), ticket);
    for (const project of projects) byProjectId.set(Number(project.id), project);

    const base: MyWorkItem[] = [];
    const scoreFromSignals = (signals: WorkSignal[] = []) =>
      signals.reduce((sum, signal) => {
        const sev = String(signal?.severity || '').toLowerCase();
        if (sev === 'critical' || sev === 'high') return sum + 180;
        if (sev === 'warning' || sev === 'medium') return sum + 80;
        return sum + 20;
      }, 0);

    const workDriven = workItems.length > 0;
    if (workDriven) {
      for (const workItem of workItems) {
        const sourceType = String(workItem.sourceType || workItem.source_type || '').toLowerCase();
        const sourceId = Number(workItem.sourceId || workItem.source_id || 0);
        const priorityScore = Number(workItem.priorityScore ?? workItem.priority_score ?? 0);
        const signals = Array.isArray(workItem.signals) ? workItem.signals : [];
        const signalScore = scoreFromSignals(signals);
        const slaRemaining = workItem.slaTimeRemaining ?? workItem.sla_time_remaining ?? null;
        const slaMinutes = typeof slaRemaining === 'number' ? slaRemaining : null;
        const hasCriticalSignal = signals.some((s) => {
          const sev = String(s?.severity || '').toLowerCase();
          return sev === 'critical' || sev === 'high';
        });
        const hasWarningSignal = signals.some((s) => {
          const sev = String(s?.severity || '').toLowerCase();
          return sev === 'warning' || sev === 'medium';
        });
        const breached = slaMinutes !== null && slaMinutes < 0;
        const nearBreach = slaMinutes !== null && slaMinutes >= 0 && slaMinutes <= 30;

        if (sourceType === 'ticket') {
          const ticket = byTicketId.get(sourceId);
          if (!ticket) continue;
          const ticketHealth = computeTicketHealth(ticket, slaMinutes);
          const status = String(ticket.status || '').toLowerCase();
          let group: MyWorkGroup = 'in-progress';
          if (breached || hasCriticalSignal || priorityScore >= 450 || ticketHealth.state === 'red') {
            group = 'needs-attention';
          } else if (nearBreach || hasWarningSignal || ticketHealth.state === 'amber') {
            group = 'needs-attention';
          } else if (status === 'resolved' || status === 'closed' || status === 'done') {
            group = 'stable';
          }

          const riskLabel = breached || hasCriticalSignal
            ? 'Escalated'
            : nearBreach || hasWarningSignal || ticketHealth.state === 'amber' || ticketHealth.state === 'red'
              ? 'At Risk'
              : group === 'stable'
                ? 'Stable'
                : 'In Progress';
          const topSignal = signals[0]?.message ? ` · ${signals[0].message}` : '';
          base.push({
            id: `ticket-${ticket.id}`,
            type: 'ticket',
            group,
            sortScore: priorityScore + signalScore + (breached ? 300 : nearBreach ? 120 : 0),
            title: ticket.subject || `Ticket #${ticket.id}`,
            context: `Ticket #${ticket.id} · Customer #${ticket.customerId}${topSignal}`,
            statusLabel: ticket.status,
            riskLabel,
            onClick: () => {
              window.location.href = `/wp-admin/admin.php?page=pet-support#ticket=${ticket.id}`;
            },
          });
          continue;
        }

        if (sourceType === 'project') {
          const project = byProjectId.get(sourceId);
          if (!project) continue;
          const health = computeProjectHealth(project);
          let group: MyWorkGroup = 'in-progress';
          if (health.state === 'red' || health.state === 'amber' || hasCriticalSignal || priorityScore >= 420) group = 'needs-attention';
          else if (project.state === 'completed') group = 'stable';
          base.push({
            id: `project-${project.id}`,
            type: 'project',
            group,
            sortScore: priorityScore + signalScore + (health.state === 'red' ? 220 : health.state === 'amber' ? 120 : 0),
            title: project.name,
            context: `Project #${project.id} · Customer #${project.customerId}`,
            statusLabel: project.state,
            riskLabel: group === 'needs-attention' ? 'At Risk' : group === 'stable' ? 'Stable' : 'In Progress',
            onClick: () => {
              window.location.href = `/wp-admin/admin.php?page=pet-delivery#project=${project.id}`;
            },
          });
        }
      }
    }

    if (!workDriven) {
      for (const ticket of tickets.filter((ticket) => String(ticket.assignedUserId || '') === currentUserId)) {
        const health = computeTicketHealth(ticket, null);
        const status = String(ticket.status || '').toLowerCase();
        let group: MyWorkGroup = 'in-progress';
        if (health.state === 'red' || health.state === 'amber') group = 'needs-attention';
        else if (status === 'resolved' || status === 'closed' || status === 'done') group = 'stable';
        base.push({
          id: `ticket-${ticket.id}`,
          type: 'ticket',
          group,
          sortScore: health.state === 'red' ? 400 : health.state === 'amber' ? 280 : 120,
          title: ticket.subject || `Ticket #${ticket.id}`,
          context: `Ticket #${ticket.id} · Customer #${ticket.customerId}`,
          statusLabel: ticket.status,
          riskLabel: group === 'needs-attention' ? 'At Risk' : group === 'stable' ? 'Stable' : 'In Progress',
          onClick: () => {
            window.location.href = `/wp-admin/admin.php?page=pet-support#ticket=${ticket.id}`;
          },
        });
      }
    }

    return base.sort((a, b) => b.sortScore - a.sortScore);
  }, [currentUserId, projects, tickets, workItems]);

  const needsAttention = items.filter((item) => item.group === 'needs-attention');
  const inProgress = items.filter((item) => item.group === 'in-progress');
  const stable = items.filter((item) => item.group === 'stable');

  const attentionSummary = useMemo(() => {
    const escalations = tickets.filter((ticket) => (ticket.sla_status || '').toLowerCase() === 'breached').length;
    const atRiskProjects = projects.filter((project) => {
      const health = computeProjectHealth(project);
      return health.state === 'red' || health.state === 'amber';
    }).length;
    const atRiskTickets = tickets.filter((ticket) => {
      const sla = (ticket.sla_status || '').toLowerCase();
      return sla === 'warning' || sla === 'risk' || sla === 'at_risk';
    }).length;

    return [
      `${escalations} escalations`,
      `${atRiskProjects} projects at risk`,
      `${atRiskTickets} tickets at risk`,
      `${needsAttention.length} items need attention`,
    ];
  }, [needsAttention.length, projects, tickets]);

  const visibleNeedsAttention = needsAttention.slice(0, 10);
  const visibleInProgress = inProgress.slice(0, 12);
  const visibleStable = stable.slice(0, 8);

  const renderItem = (item: MyWorkItem) => {
    const typeLabel = item.type === 'ticket' ? 'Ticket' : item.type === 'project' ? 'Project' : 'Task';
    return (
      <button
        key={item.id}
        type="button"
        onClick={item.onClick}
        className="pd-card pd-clickable"
        style={{
          textAlign: 'left',
          padding: '12px 14px',
          border: `1px solid ${toneStyles[item.group].border}`,
          borderLeft: `4px solid ${toneStyles[item.group].border}`,
          background: toneStyles[item.group].background,
          cursor: 'pointer',
        }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
          <div style={{ fontWeight: 700, color: '#101828' }}>{item.title}</div>
          <span className="pd-badge">{typeLabel}</span>
        </div>
        <div style={{ marginTop: 6, fontSize: '0.82rem', color: '#475467' }}>{item.context}</div>
        <div style={{ marginTop: 8, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <span className="pd-badge">{item.statusLabel}</span>
          <span className="pd-badge">{item.riskLabel}</span>
        </div>
      </button>
    );
  };

  return (
    <PageShell
      title="My Work"
      subtitle="Single staff operational surface for what needs action now."
      className="pet-my-work"
      testId="my-work-shell"
    >
      {loading && (
        <Panel>
          <div className="pd-empty">Loading my work…</div>
        </Panel>
      )}

      {error && (
        <Panel>
          <div className="pd-error">{error}</div>
        </Panel>
      )}

      {!loading && !error && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <Panel>
            <div style={{ background: '#101828', color: '#fff', borderRadius: 10, padding: 12, display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', gap: 10 }}>
              {attentionSummary.slice(0, 4).map((line) => (
                <div key={line} style={{ background: 'rgba(255,255,255,0.1)', borderRadius: 8, padding: 10, fontWeight: 700 }}>
                  {line}
                </div>
              ))}
            </div>
          </Panel>

          <Panel>
            <h3 style={{ marginTop: 0 }}>Needs Attention</h3>
            <div style={{ display: 'grid', gap: 10 }}>
              {needsAttention.length > 0 ? visibleNeedsAttention.map(renderItem) : <div className="pd-empty">No critical items.</div>}
              {needsAttention.length > visibleNeedsAttention.length && (
                <div className="pd-empty">+{needsAttention.length - visibleNeedsAttention.length} more urgent items</div>
              )}
            </div>
          </Panel>

          <Panel>
            <h3 style={{ marginTop: 0 }}>In Progress</h3>
            <div style={{ display: 'grid', gap: 10 }}>
              {inProgress.length > 0 ? visibleInProgress.map(renderItem) : <div className="pd-empty">No active work items.</div>}
              {inProgress.length > visibleInProgress.length && (
                <div className="pd-empty">+{inProgress.length - visibleInProgress.length} more active items</div>
              )}
            </div>
          </Panel>

          <Panel>
            <h3 style={{ marginTop: 0 }}>Stable / Monitoring</h3>
            <div style={{ display: 'grid', gap: 10 }}>
              {stable.length > 0 ? visibleStable.map(renderItem) : <div className="pd-empty">No monitoring items.</div>}
              {stable.length > visibleStable.length && (
                <div className="pd-empty">+{stable.length - visibleStable.length} more stable items</div>
              )}
            </div>
          </Panel>
        </div>
      )}
    </PageShell>
  );
};

export default MyWork;
