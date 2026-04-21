import React, { useEffect, useState, useCallback, useMemo } from 'react';
import '../dashboard-styles.css';
import { computeTicketHealth, computeProjectHealth, computeQuoteHealth, computeLeadHealth, HealthResult, HealthHistory } from '../healthCompute';
import type { JourneyData } from '../healthCompute';
import JourneyBar from './JourneyBar';
import { legacyConfirm } from './legacyDialogs';

/* ============================================================
   Types
   ============================================================ */
interface DashboardOverview {
  activeProjects: number;
  pendingQuotes: number;
  utilizationRate: number;
  revenueThisMonth: number;
}

interface DemoWow {
  slaRisk: { warningCount: number; breachedCount: number };
  workload: { unassignedTicketsCount: number };
}

interface TicketItem {
  id: number;
  subject: string;
  status: string;
  priority: string;
  customerId: number;
  assignedUserId: string | null;
  sla_status?: string;
  response_due_at?: string;
  resolution_due_at?: string;
  createdAt: string;
  description?: string;
  category?: string;
  subcategory?: string;
  ticketMode?: string;
  lifecycleOwner?: string;
  soldMinutes?: number | null;
  estimatedMinutes?: number | null;
  isBaselineLocked?: boolean;
  isRollup?: boolean;
  siteId?: number;
  slaId?: number;
  contactId?: number | null;
  openedAt?: string | null;
  resolvedAt?: string | null;
  closedAt?: string | null;
  intake_source?: string | null;
  queueId?: string | null;
  ownerUserId?: string | null;
  isBillableDefault?: boolean;
  billingContextType?: string;
  slaSnapshotId?: number | null;
  slaName?: string | null;
  projectId?: number | null;
}

interface WorkItem {
  id: string;
  source_type: string;
  source_id: string;
  assigned_user_id: string | null;
  department_id?: string;
  priority_score: number;
  status: string;
  sla_time_remaining: number | null;
  due_date: string | null;
  signals: { type: string; severity: string; message: string }[];
}

interface ProjectItem {
  id: number;
  name: string;
  customerId: number;
  soldHours: number;
  soldValue: number;
  state: string;
  startDate?: string;
  endDate?: string;
  tasks: { id: number; name: string; estimatedHours: number; completed: boolean }[];
  malleableData?: { pm?: string; health?: string; hours_used?: number };
}

interface ActivityItem {
  id: string;
  occurred_at: string;
  event_type: string;
  severity: string;
  reference_type: string | null;
  reference_id: string | null;
  reference_url: string | null;
  headline: string;
  subline: string;
  customer_id: string | null;
  customer_name: string | null;
  company_logo_url: string | null;
  actor_type: string;
  actor_id: string | null;
  actor_display_name: string;
  actor_avatar_url: string | null;
  tags: string[];
  sla: { clock_state: string; seconds_remaining: number | null; kind: string | null } | null;
  meta: Record<string, unknown>;
}

interface CustomerItem {
  id: number;
  name: string;
}

interface LeadItem {
  id: number;
  customerId: number;
  subject: string;
  description: string;
  status: string;
  source: string | null;
  estimatedValue: number | null;
  createdAt: string;
  updatedAt: string | null;
  convertedAt: string | null;
}

interface QuoteItem {
  id: number;
  customerId: number;
  leadId: number | null;
  title: string;
  state: string;
  totalValue: number;
  currency: string;
  createdAt: string;
  updatedAt: string | null;
  acceptedAt: string | null;
}

interface SalesData {
  pipelineValue: number;
  quotesSent: number;
  winRate: number;
  revenueMtd: number;
  activeLeads: number;
  avgDealSize: number;
  quotesByState: Record<string, number>;
}

type Persona = 'manager' | 'support' | 'pm' | 'sales' | 'timesheets';

/* Server-side dashboard summary types (from DashboardCompositionService) */
interface ServerPanel {
  panel_key: string;
  title: string;
  metric_value: number | string | null;
  metric_unit: string | null;
  severity: string | null;
  scope_type: string;
  scope_id: number;
  as_of: string;
  count_breakdown: Record<string, any> | null;
  items: any[];
  source_summary: string | null;
  actions?: { label: string; method: string; path: string; body?: any }[];
}

interface ServerScope {
  scope_type: string;
  scope_id: number;
  visibility_scope: 'TEAM' | 'MANAGERIAL' | 'ADMIN';
  label: string;
}

interface ServerSummary {
  as_of: string;
  scopes: ServerScope[];
  active_scope: ServerScope | null;
  allowed_personas: Persona[];
  personas: Record<string, { panels: ServerPanel[] }>;
}

const findPanel = (panels: ServerPanel[] | undefined, key: string): ServerPanel | undefined =>
  panels?.find(p => p.panel_key === key);

/* ============================================================
   API helpers
   ============================================================ */
const api = (path: string) => {
  const s = window.petSettings;
  return fetch(`${s.apiUrl}/${path}`, {
    headers: { 'X-WP-Nonce': s.nonce },
  }).then(r => r.json());
};

const apiPost = (path: string, body: Record<string, unknown>) => {
  const s = window.petSettings;
  return fetch(`${s.apiUrl}/${path}`, {
    method: 'POST',
    headers: { 'X-WP-Nonce': s.nonce, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).then(r => r.json());
};

const apiPut = (path: string, body: Record<string, unknown>) => {
  const s = window.petSettings;
  return fetch(`${s.apiUrl}/${path}`, {
    method: 'PUT',
    headers: { 'X-WP-Nonce': s.nonce, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).then(r => r.json());
};

/* ============================================================
   Utility helpers
   ============================================================ */
const timeAgo = (iso: string): string => {
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
};

const formatMinutes = (mins: number | null): string => {
  if (mins === null) return '--';
  const abs = Math.abs(mins);
  const h = Math.floor(abs / 60);
  const m = abs % 60;
  const sign = mins < 0 ? '-' : '';
  return h > 0 ? `${sign}${h}h ${m}m` : `${sign}${m}m`;
};

const timerColor = (mins: number | null): string => {
  if (mins === null) return '';
  if (mins < 0) return 'red';
  if (mins < 60) return 'amber';
  return 'green';
};

const pct = (a: number, b: number): number => (b > 0 ? Math.round((a / b) * 100) : 0);

/* ============================================================
   Sub-components
   ============================================================ */
const KpiCard: React.FC<{ value: string | number; label: string; color: string }> = ({ value, label, color }) => (
  <div className={`pd-kpi-card ${color}`}>
    <div className="pd-kpi-value">{value}</div>
    <div className="pd-kpi-label">{label}</div>
  </div>
);

const AttentionCard: React.FC<{
  subject: string;
  meta: string;
  severity: string;
  timer?: string;
  timerClass?: string;
  statusLabel?: string;
  pulse?: boolean;
  isPulseway?: boolean;
  uhbClass?: string;
  reasons?: { label: string; color: string }[];
  recoveryHistory?: HealthHistory | null;
  slaName?: string | null;
  onClick?: () => void;
}> = ({ subject, meta, severity, timer, timerClass, statusLabel, pulse, isPulseway, uhbClass, reasons, recoveryHistory, slaName, onClick }) => (
  <div
    className={`pd-attention-card ${uhbClass || `severity-${severity}`} ${pulse ? 'pd-pulse' : ''} ${onClick ? 'pd-clickable' : ''} ${recoveryHistory ? 'uhb-has-recovery' : ''}`}
    onClick={onClick}
  >
    {/* Recovery indicator dots for completed items */}
    {recoveryHistory && (recoveryHistory.was_red || recoveryHistory.was_amber) && (
      <div className="uhb-recovery-dots">
        {recoveryHistory.was_red && <span className="uhb-dot uhb-dot-red" title="Was critical during lifecycle" />}
        {recoveryHistory.was_amber && <span className="uhb-dot uhb-dot-amber" title="Was at-risk during lifecycle" />}
      </div>
    )}
    {slaName && <div className="pd-sla-label">{slaName}</div>}
    <div className="pd-attention-body">
      <div className="pd-attention-subject">
        {isPulseway && <span className="pd-pulseway-tag">{`\u{1F5A5}\uFE0F`} PW</span>}
        {subject}
        {reasons && reasons.length > 0 && reasons.map((r, i) => (
          <span key={i} className={`uhb-tag uhb-tag-${r.color}`}>{r.label}</span>
        ))}
      </div>
      <div className="pd-attention-meta">{meta}</div>
    </div>
    {(timer || statusLabel) && (
      <div className="pd-attention-right">
        {timer && <div className={`pd-attention-timer ${timerClass || ''}`}>{timer}</div>}
        {statusLabel && <div className="pd-attention-status">{statusLabel}</div>}
      </div>
    )}
  </div>
);

const formatSlaSeconds = (sec: number): string => {
  const abs = Math.abs(sec);
  const h = Math.floor(abs / 3600);
  const m = Math.floor((abs % 3600) / 60);
  const sign = sec < 0 ? '-' : '';
  return h > 0 ? `${sign}${h}h ${m}m` : `${sign}${m}m`;
};

const severityBorder = (severity: string): string => {
  switch (severity) {
    case 'breach': return 'pd-activity-sev-breach';
    case 'risk':
    case 'attention': return 'pd-activity-sev-risk';
    case 'commercial': return 'pd-activity-sev-commercial';
    default: return '';
  }
};

const refIcon = (refType: string | null): string => {
  switch (refType) {
    case 'ticket': return '\uD83C\uDFA7';
    case 'project': return '\uD83D\uDCCA';
    case 'quote': return '\uD83D\uDCB0';
    case 'milestone': return '\uD83C\uDFC1';
    case 'time_entry':
    case 'timesheet': return '\u23F1';
    case 'escalation': return '\uD83D\uDD25';
    default: return '';
  }
};

const ActivityStream: React.FC<{ items: ActivityItem[]; emptyMsg?: string }> = ({ items, emptyMsg }) => {
  if (items.length === 0) return <div className="pd-empty">{emptyMsg || 'No recent activity'}</div>;

  return (
    <ul className="pd-activity-list">
      {items.slice(0, 20).map(a => {
        const initial = a.actor_display_name?.charAt(0)?.toUpperCase() || '?';
        const hasSla = a.sla && a.sla.seconds_remaining !== null;
        const slaBreached = hasSla && a.sla!.seconds_remaining! < 0;
        const slaWarn = hasSla && !slaBreached && a.sla!.seconds_remaining! < 3600;
        const sevClass = severityBorder(a.severity);
        const isPulse = a.severity === 'breach' || slaBreached;

        return (
          <li key={a.id} className={`pd-activity-item ${sevClass} ${isPulse ? 'pd-pulse' : ''}`}>
            {/* Actor avatar */}
            <div className="pd-activity-avatar">
              {a.actor_avatar_url ? (
                <img src={a.actor_avatar_url} alt={a.actor_display_name} className="pd-activity-avatar-img" />
              ) : (
                <span className="pd-activity-avatar-initial">{initial}</span>
              )}
            </div>

            {/* Body */}
            <div className="pd-activity-body">
              <div className="pd-activity-headline">
                <strong>{a.actor_display_name}</strong>{' '}
                {a.headline}
              </div>
              {a.subline && <div className="pd-activity-subline">{a.subline}</div>}

              {/* Customer badge + tags row */}
              <div className="pd-activity-meta-row">
                {a.customer_name && (
                  <span className="pd-activity-customer">
                    {a.company_logo_url ? (
                      <img src={a.company_logo_url} alt="" className="pd-activity-customer-logo" />
                    ) : (
                      <span className="pd-activity-customer-dot" />
                    )}
                    {a.customer_name}
                  </span>
                )}
                {a.reference_type && (
                  <span className="pd-activity-ref-badge">
                    {refIcon(a.reference_type)} {a.reference_id || a.reference_type}
                  </span>
                )}
                {a.tags && a.tags.length > 0 && a.tags.map((tag, ti) => (
                  <span key={ti} className={`pd-activity-tag ${tag.toLowerCase().includes('breach') ? 'tag-breach' : tag.toLowerCase().includes('risk') ? 'tag-risk' : tag.toLowerCase().includes('escalat') ? 'tag-escalation' : tag.toLowerCase().includes('quote') ? 'tag-commercial' : tag.toLowerCase().includes('milestone') ? 'tag-milestone' : ''}`}>
                    {tag}
                  </span>
                ))}
              </div>
            </div>

            {/* Right column: time + SLA */}
            <div className="pd-activity-right">
              <div className="pd-activity-time">{timeAgo(a.occurred_at)}</div>
              {hasSla && (
                <div className={`pd-activity-sla ${slaBreached ? 'sla-breach' : slaWarn ? 'sla-warn' : 'sla-ok'}`}>
                  {formatSlaSeconds(a.sla!.seconds_remaining!)}
                </div>
              )}
            </div>
          </li>
        );
      })}
    </ul>
  );
};

/* ============================================================
   MANAGER VIEW — "Am I in control?"
   ============================================================ */
const ManagerView: React.FC<{
  overview: DashboardOverview;
  demoWow?: DemoWow;
  tickets: TicketItem[];
  workItems: WorkItem[];
  activity: ActivityItem[];
  customers: Map<number, string>;
  serverPanels?: ServerPanel[];
}> = ({ overview, demoWow, tickets, workItems, activity, customers, serverPanels }) => {
  // SLA health: % of open tickets NOT breached
  const openTickets = tickets.filter(t => !['closed', 'resolved'].includes(t.status));
  const breachedWorkItems = workItems.filter(
    wi => wi.source_type === 'ticket' && wi.sla_time_remaining !== null && wi.sla_time_remaining < 0
  );
  const slaHealth = openTickets.length > 0
    ? pct(openTickets.length - breachedWorkItems.length, openTickets.length)
    : 100;

  // Needs attention: breached, warnings, unassigned
  const ticketMap = new Map(tickets.map(t => [String(t.id), t]));
  const attentionItems: { subject: string; meta: string; severity: string; timer?: string; timerClass?: string; statusLabel?: string; pulse?: boolean; isPulseway?: boolean; uhbClass?: string; reasons?: { label: string; color: string }[]; slaName?: string | null; sort: number }[] = [];

  workItems
    .filter(wi => wi.source_type === 'ticket')
    .forEach(wi => {
      const ticket = ticketMap.get(wi.source_id);
      if (!ticket || ['closed', 'resolved'].includes(ticket.status)) return;
      const custName = customers.get(ticket.customerId) || `Customer #${ticket.customerId}`;
      const health = computeTicketHealth(ticket, wi.sla_time_remaining);

      if (wi.sla_time_remaining !== null && wi.sla_time_remaining < 0) {
        attentionItems.push({
          subject: ticket.subject,
          meta: `${custName} · Breached ${formatMinutes(wi.sla_time_remaining)} ago`,
          severity: 'breached',
          timer: formatMinutes(wi.sla_time_remaining),
          timerClass: 'red',
          statusLabel: 'BREACHED',
          pulse: true,
          isPulseway: ticket.intake_source === 'pulseway',
          uhbClass: health.className,
          reasons: health.reasons,
          slaName: ticket.slaName || null,
          sort: 0,
        });
      } else if (wi.sla_time_remaining !== null && wi.sla_time_remaining < 60) {
        attentionItems.push({
          subject: ticket.subject,
          meta: `${custName} · SLA warning`,
          severity: 'warning',
          timer: formatMinutes(wi.sla_time_remaining),
          timerClass: 'amber',
          statusLabel: 'WARNING',
          isPulseway: ticket.intake_source === 'pulseway',
          uhbClass: health.className,
          reasons: health.reasons,
          slaName: ticket.slaName || null,
          sort: 1,
        });
      }

      if (!wi.assigned_user_id) {
        attentionItems.push({
          subject: ticket.subject,
          meta: `${custName} · No one assigned`,
          severity: 'unassigned',
          statusLabel: 'UNASSIGNED',
          isPulseway: ticket.intake_source === 'pulseway',
          uhbClass: health.className,
          slaName: ticket.slaName || null,
          sort: 2,
        });
      }
    });

  attentionItems.sort((a, b) => a.sort - b.sort);

  // Strategic activity (API returns UPPERCASE event types)
  const strategicTypes = ['QUOTE_ACCEPTED', 'CONTRACT_CREATED', 'PROJECT_CREATED', 'MILESTONE_COMPLETED', 'ESCALATION_TRIGGERED', 'QUOTE_SENT', 'SLA_BREACH_RECORDED'];
  const strategicActivity = activity.filter(a => strategicTypes.includes(a.event_type) || a.severity === 'breach' || a.severity === 'commercial');

  /* ---- Operational Health computations ---- */
  // Build a work-item lookup keyed by source_id for ticket work items
  const ticketWiMap = new Map<string, WorkItem>();
  workItems.filter(wi => wi.source_type === 'ticket').forEach(wi => ticketWiMap.set(wi.source_id, wi));

  // SLA Compliance donut segments
  const slaSegments = { breached: 0, warning: 0, healthy: 0, paused: 0, closed: 0 };
  tickets.forEach(t => {
    if (['closed', 'resolved'].includes(t.status)) { slaSegments.closed++; return; }
    if (t.status === 'paused' || t.status === 'on_hold') { slaSegments.paused++; return; }
    const wi = ticketWiMap.get(String(t.id));
    const rem = wi?.sla_time_remaining ?? null;
    if (rem !== null && rem < 0) slaSegments.breached++;
    else if (rem !== null && rem < 60) slaSegments.warning++;
    else slaSegments.healthy++;
  });
  const slaTotal = Object.values(slaSegments).reduce((a, b) => a + b, 0);
  const slaDeg = (n: number) => slaTotal > 0 ? (n / slaTotal) * 360 : 0;
  const donutGradient = slaTotal > 0
    ? `conic-gradient(
        #dc3545 0deg ${slaDeg(slaSegments.breached)}deg,
        #f0ad4e ${slaDeg(slaSegments.breached)}deg ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning)}deg,
        #28a745 ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning)}deg ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning) + slaDeg(slaSegments.healthy)}deg,
        #6c757d ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning) + slaDeg(slaSegments.healthy)}deg ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning) + slaDeg(slaSegments.healthy) + slaDeg(slaSegments.paused)}deg,
        #adb5bd ${slaDeg(slaSegments.breached) + slaDeg(slaSegments.warning) + slaDeg(slaSegments.healthy) + slaDeg(slaSegments.paused)}deg 360deg
      )`
    : 'conic-gradient(#e0e0e0 0deg 360deg)';

  // Tickets by Priority (open only)
  const priorityCounts: Record<string, number> = {};
  openTickets.forEach(t => {
    const p = (t.priority || 'medium').toLowerCase();
    priorityCounts[p] = (priorityCounts[p] || 0) + 1;
  });
  const priorityOrder = ['critical', 'high', 'medium', 'low'];
  const priorityColors: Record<string, string> = { critical: '#dc3545', high: '#f0ad4e', medium: '#0d6efd', low: '#28a745' };
  const maxPriority = Math.max(1, ...Object.values(priorityCounts));

  // Customer Exposure (open tickets per customer, sorted by breached count)
  const custExposure: { name: string; open: number; breached: number; warning: number }[] = [];
  const custBuckets = new Map<number, { open: number; breached: number; warning: number }>();
  openTickets.forEach(t => {
    const b = custBuckets.get(t.customerId) || { open: 0, breached: 0, warning: 0 };
    b.open++;
    const wi = ticketWiMap.get(String(t.id));
    const rem = wi?.sla_time_remaining ?? null;
    if (rem !== null && rem < 0) b.breached++;
    else if (rem !== null && rem < 60) b.warning++;
    custBuckets.set(t.customerId, b);
  });
  custBuckets.forEach((v, cid) => {
    custExposure.push({ name: customers.get(cid) || `Customer #${cid}`, ...v });
  });
  custExposure.sort((a, b) => b.breached - a.breached || b.open - a.open);
  const maxExposure = Math.max(1, ...custExposure.map(c => c.open));

  return (
    <>
      <div className="pd-kpi-strip">
        <KpiCard value={`$${overview.revenueThisMonth.toLocaleString()}`} label="Revenue MTD" color="green" />
        <KpiCard value={overview.activeProjects} label="Active Projects" color="blue" />
        <KpiCard value={`${slaHealth}%`} label="SLA Health" color={slaHealth >= 80 ? 'green' : slaHealth >= 50 ? 'amber' : 'red'} />
        <KpiCard value={`${overview.utilizationRate}%`} label="Utilisation" color="purple" />
        <KpiCard value={openTickets.length} label="Open Tickets" color="teal" />
        <KpiCard value={tickets.filter(t => t.intake_source === 'pulseway' && !['closed','resolved'].includes(t.status)).length} label={'\u{1F5A5}\uFE0F Pulseway Alerts'} color="purple" />
        <KpiCard value={overview.pendingQuotes} label="Pending Quotes" color="amber" />
      </div>

      {/* Operational Health at a Glance */}
      <div className="pd-attention-panel pd-ops-health">
        <h3 className="pd-section-title">Operational Health at a Glance</h3>
        <div className="pd-ops-grid">
          {/* SLA Compliance Donut */}
          <div className="pd-ops-chart">
            <h4 className="pd-ops-chart-title">SLA Compliance</h4>
            <div className="pd-donut-wrap">
              <div className="pd-donut" style={{ background: donutGradient }}>
                <div className="pd-donut-hole">
                  <span className="pd-donut-pct">{slaHealth}%</span>
                  <span className="pd-donut-label">compliant</span>
                </div>
              </div>
              <div className="pd-donut-legend">
                {[{ label: 'Breached', count: slaSegments.breached, color: '#dc3545' },
                  { label: 'Warning', count: slaSegments.warning, color: '#f0ad4e' },
                  { label: 'Healthy', count: slaSegments.healthy, color: '#28a745' },
                  { label: 'Paused', count: slaSegments.paused, color: '#6c757d' },
                  { label: 'Closed', count: slaSegments.closed, color: '#adb5bd' },
                ].map(s => (
                  <div key={s.label} className="pd-legend-row">
                    <span className="pd-legend-dot" style={{ background: s.color }} />
                    <span className="pd-legend-label">{s.label}</span>
                    <span className="pd-legend-count">{s.count}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Tickets by Priority */}
          <div className="pd-ops-chart">
            <h4 className="pd-ops-chart-title">Tickets by Priority</h4>
            <div className="pd-priority-bars">
              {priorityOrder.map(p => (
                <div key={p} className="pd-bar-row">
                  <span className="pd-bar-label">{p.charAt(0).toUpperCase() + p.slice(1)}</span>
                  <div className="pd-bar-track">
                    <div
                      className="pd-bar-fill"
                      style={{ width: `${pct(priorityCounts[p] || 0, maxPriority)}%`, background: priorityColors[p] || '#0d6efd' }}
                    />
                  </div>
                  <span className="pd-bar-count">{priorityCounts[p] || 0}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Customer Exposure */}
          <div className="pd-ops-chart">
            <h4 className="pd-ops-chart-title">Customer Exposure</h4>
            <div className="pd-exposure-bars">
              {custExposure.length === 0 && <div className="pd-empty">No open tickets</div>}
              {custExposure.slice(0, 8).map(c => (
                <div key={c.name} className="pd-bar-row">
                  <span className="pd-bar-label pd-bar-label--cust">{c.name}</span>
                  <div className="pd-bar-track">
                    <div className="pd-bar-fill" style={{ width: `${pct(c.breached, maxExposure)}%`, background: '#dc3545' }} />
                    <div className="pd-bar-fill pd-bar-fill--stack" style={{ width: `${pct(c.warning, maxExposure)}%`, background: '#f0ad4e' }} />
                    <div className="pd-bar-fill pd-bar-fill--stack" style={{ width: `${pct(c.open - c.breached - c.warning, maxExposure)}%`, background: '#28a745' }} />
                  </div>
                  <span className="pd-bar-count">{c.open}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="pd-attention-panel">
        <h3 className="pd-section-title">
          Needs Attention
          {attentionItems.length > 0 && <span className="pd-badge">{attentionItems.length}</span>}
        </h3>
        {attentionItems.length === 0 ? (
          <div className="pd-empty">All clear — nothing needs your attention right now.</div>
        ) : (
          <div className="pd-attention-grid">
            {attentionItems.slice(0, 12).map((item, i) => (
              <AttentionCard key={i} {...item} slaName={item.slaName} />
            ))}
          </div>
        )}
      </div>

      {/* === Server-composed panels (escalation, advisory, resilience) === */}
      {(() => {
        const escPanel = findPanel(serverPanels, 'escalation_summary');
        if (!escPanel) return null;
        const bySev = escPanel.count_breakdown?.by_severity as Record<string, number> | undefined;
        return (
          <div className="pd-attention-panel">
            <h3 className="pd-section-title">
              Open Escalations
              {escPanel.metric_value != null && Number(escPanel.metric_value) > 0 && <span className="pd-badge">{escPanel.metric_value}</span>}
            </h3>
            {Number(escPanel.metric_value) === 0 ? (
              <div className="pd-empty">No open escalations.</div>
            ) : (
              <div className="pd-kpi-strip">
                <KpiCard value={escPanel.metric_value ?? 0} label="Total Open" color={escPanel.severity === 'critical' ? 'red' : escPanel.severity === 'warning' ? 'amber' : 'blue'} />
                {bySev && Object.entries(bySev).map(([sev, count]) => (
                  <KpiCard key={sev} value={count} label={sev} color={sev.toLowerCase() === 'critical' ? 'red' : sev.toLowerCase() === 'high' ? 'amber' : 'blue'} />
                ))}
              </div>
            )}
          </div>
        );
      })()}

      {(() => {
        const advPanel = findPanel(serverPanels, 'advisory_summary');
        if (!advPanel) return null;
        const bySev = advPanel.count_breakdown?.by_severity as Record<string, number> | undefined;
        return (
          <div className="pd-attention-panel">
            <h3 className="pd-section-title">
              Advisory Signals
              {advPanel.metric_value != null && Number(advPanel.metric_value) > 0 && <span className="pd-badge">{advPanel.metric_value}</span>}
            </h3>
            {Number(advPanel.metric_value) === 0 ? (
              <div className="pd-empty">No active advisory signals.</div>
            ) : (
              <div className="pd-kpi-strip">
                <KpiCard value={advPanel.metric_value ?? 0} label="Active Signals" color={advPanel.severity === 'attention' ? 'amber' : 'blue'} />
                {bySev && Object.entries(bySev).map(([sev, count]) => (
                  <KpiCard key={sev} value={count} label={sev} color={sev.toLowerCase() === 'critical' ? 'red' : sev.toLowerCase() === 'warning' ? 'amber' : 'blue'} />
                ))}
              </div>
            )}
          </div>
        );
      })()}

      {(() => {
        const resPanel = findPanel(serverPanels, 'resilience_summary');
        if (!resPanel) return null;
        return (
          <div className="pd-attention-panel">
            <h3 className="pd-section-title">
              Resilience
              {resPanel.metric_value != null && Number(resPanel.metric_value) > 0 && <span className="pd-badge">{resPanel.metric_value}</span>}
            </h3>
            {resPanel.source_summary && <div className="pd-empty">{resPanel.source_summary}</div>}
            {resPanel.items.length > 0 && (
              <div className="pd-attention-grid">
                {resPanel.items.slice(0, 8).map((sig: any) => (
                  <AttentionCard
                    key={sig.id}
                    subject={sig.title || sig.signal_type}
                    meta={sig.summary || ''}
                    severity={sig.severity === 'critical' ? 'breached' : sig.severity === 'warning' ? 'warning' : 'info'}
                    statusLabel={sig.severity?.toUpperCase()}
                  />
                ))}
              </div>
            )}
            {resPanel.actions && resPanel.actions.length > 0 && (
              <div style={{ marginTop: 10, display: 'flex', gap: 8 }}>
                {resPanel.actions.map((a, i) => (
                  <button
                    key={i}
                    className="pd-refresh-btn"
                    type="button"
                    onClick={async () => { await apiPost(a.path, a.body || {}); window.location.reload(); }}
                  >
                    {a.label}
                  </button>
                ))}
              </div>
            )}
          </div>
        );
      })()}

      <div className="pd-activity-panel">
        <h3 className="pd-section-title">Strategic Activity</h3>
        <ActivityStream items={strategicActivity.length > 0 ? strategicActivity : activity} emptyMsg="No strategic events yet" />
      </div>
    </>
  );
};

/* ============================================================
   SUPPORT VIEW — "What should I do next?"
   ============================================================ */
const SupportView: React.FC<{
  tickets: TicketItem[];
  workItems: WorkItem[];
  activity: ActivityItem[];
  customers: Map<number, string>;
  currentUserId: number;
  onTicketClick: (ticketId: number) => void;
  serverPanels?: ServerPanel[];
}> = ({ tickets, workItems, activity, customers, currentUserId, onTicketClick, serverPanels }) => {
  const uid = String(currentUserId);
  const myWorkItems = workItems.filter(wi => wi.assigned_user_id === uid && wi.source_type === 'ticket');
  const ticketMap = new Map(tickets.map(t => [String(t.id), t]));

  const myOpenTickets = myWorkItems.filter(wi => {
    const ticket = ticketMap.get(wi.source_id);
    return ticket && !['closed', 'resolved'].includes(ticket.status);
  });

  const myBreached = myOpenTickets.filter(wi => wi.sla_time_remaining !== null && wi.sla_time_remaining < 0);
  const dueWithinHour = myOpenTickets.filter(wi => wi.sla_time_remaining !== null && wi.sla_time_remaining > 0 && wi.sla_time_remaining <= 60);

  // Unassigned tickets (queue)
  const unassignedItems = workItems.filter(wi => wi.source_type === 'ticket' && !wi.assigned_user_id);
  const unassignedTickets = unassignedItems.filter(wi => {
    const ticket = ticketMap.get(wi.source_id);
    return ticket && !['closed', 'resolved'].includes(ticket.status);
  });

  // Attention: my tickets sorted by SLA urgency
  const attentionItems = myOpenTickets
    .map(wi => {
      const ticket = ticketMap.get(wi.source_id)!;
      const custName = customers.get(ticket.customerId) || `Customer #${ticket.customerId}`;
      const breached = wi.sla_time_remaining !== null && wi.sla_time_remaining < 0;
      const warning = wi.sla_time_remaining !== null && wi.sla_time_remaining > 0 && wi.sla_time_remaining <= 60;

      const health = computeTicketHealth(ticket, wi.sla_time_remaining);

      return {
        ticketId: ticket.id,
        subject: ticket.subject,
        meta: `${custName} · ${ticket.priority} priority`,
        severity: breached ? 'breached' as const : warning ? 'warning' as const : 'info' as const,
        timer: formatMinutes(wi.sla_time_remaining),
        timerClass: timerColor(wi.sla_time_remaining),
        statusLabel: breached ? 'BREACHED' : warning ? 'DUE SOON' : 'ON TRACK',
        pulse: breached,
        isPulseway: ticket.intake_source === 'pulseway',
        uhbClass: health.className,
        reasons: health.reasons,
        slaName: ticket.slaName || null,
        sort: wi.sla_time_remaining ?? 9999,
      };
    })
    .sort((a, b) => a.sort - b.sort);

  // Unassigned queue items for display
  const unassignedAttention = unassignedTickets.map(wi => {
    const ticket = ticketMap.get(wi.source_id)!;
    const custName = customers.get(ticket.customerId) || `Customer #${ticket.customerId}`;
    const health = computeTicketHealth(ticket, wi.sla_time_remaining);
    return {
      ticketId: ticket.id,
      subject: ticket.subject,
      meta: `${custName} · Unassigned`,
      severity: 'unassigned' as const,
      timer: formatMinutes(wi.sla_time_remaining),
      timerClass: timerColor(wi.sla_time_remaining),
      statusLabel: 'UNASSIGNED',
      pulse: false,
      isPulseway: ticket.intake_source === 'pulseway',
      uhbClass: health.className,
      reasons: health.reasons,
      slaName: ticket.slaName || null,
      sort: wi.sla_time_remaining ?? 9999,
    };
  });

  // Support-relevant activity (API returns UPPERCASE event types)
  const supportTypes = ['TICKET_CREATED', 'TICKET_ASSIGNED', 'TICKET_STATUS_CHANGED', 'SLA_WARNING', 'SLA_RISK_DETECTED', 'SLA_BREACH_RECORDED', 'TICKET_RESOLVED', 'ESCALATION_TRIGGERED'];
  const supportActivity = activity.filter(a => supportTypes.includes(a.event_type) || a.reference_type === 'ticket');

  return (
    <>
      <div className="pd-kpi-strip">
        <KpiCard value={myOpenTickets.length} label="My Open Tickets" color="blue" />
        <KpiCard value={myBreached.length} label="Breached (Mine)" color={myBreached.length > 0 ? 'red' : 'green'} />
        <KpiCard value={dueWithinHour.length} label="Due Within 1hr" color={dueWithinHour.length > 0 ? 'amber' : 'green'} />
        <KpiCard value={unassignedTickets.length} label="Unassigned Queue" color={unassignedTickets.length > 0 ? 'amber' : 'teal'} />
        <KpiCard value={tickets.filter(t => t.intake_source === 'pulseway' && !['closed','resolved'].includes(t.status)).length} label={'\u{1F5A5}\uFE0F Pulseway Alerts'} color="purple" />
        {(() => { const tq = findPanel(serverPanels, 'team_queue'); return tq ? <KpiCard value={tq.metric_value ?? 0} label="Team Queue" color="teal" /> : null; })()}
        {(() => { const mq = findPanel(serverPanels, 'my_queue'); return mq ? <KpiCard value={mq.metric_value ?? 0} label="Server My Queue" color="blue" /> : null; })()}
      </div>

      <div className="pd-attention-panel">
        <h3 className="pd-section-title">
          My Tickets by SLA Urgency
          {attentionItems.length > 0 && <span className="pd-badge">{attentionItems.length}</span>}
        </h3>
        {attentionItems.length === 0 ? (
          <div className="pd-empty">You have no open tickets assigned. Check the unassigned queue!</div>
        ) : (
          <div className="pd-attention-grid">
            {attentionItems.map((item, i) => (
              <AttentionCard key={i} {...item} slaName={item.slaName} onClick={() => onTicketClick(item.ticketId)} />
            ))}
          </div>
        )}
      </div>

      {unassignedAttention.length > 0 && (
        <div className="pd-attention-panel">
          <h3 className="pd-section-title">
            Unassigned Queue
            <span className="pd-badge">{unassignedAttention.length}</span>
          </h3>
          <div className="pd-attention-grid">
            {unassignedAttention.map((item, i) => (
              <AttentionCard key={i} {...item} slaName={item.slaName} onClick={() => onTicketClick(item.ticketId)} />
            ))}
          </div>
        </div>
      )}

      {(() => {
        const advPanel = findPanel(serverPanels, 'advisory_signals');
        if (!advPanel || Number(advPanel.metric_value) === 0) return null;
        const bySev = advPanel.count_breakdown?.by_severity as Record<string, number> | undefined;
        return (
          <div className="pd-attention-panel">
            <h3 className="pd-section-title">
              Advisory Signals
              <span className="pd-badge">{advPanel.metric_value}</span>
            </h3>
            <div className="pd-kpi-strip">
              {bySev && Object.entries(bySev).map(([sev, count]) => (
                <KpiCard key={sev} value={count} label={sev} color={sev.toLowerCase() === 'critical' ? 'red' : sev.toLowerCase() === 'warning' ? 'amber' : 'blue'} />
              ))}
            </div>
          </div>
        );
      })()}

      <div className="pd-activity-panel">
        <h3 className="pd-section-title">Ticket Activity</h3>
        <ActivityStream items={supportActivity.length > 0 ? supportActivity : activity} emptyMsg="No ticket events yet" />
      </div>
    </>
  );
};

/* ============================================================
   PROJECT MANAGER VIEW — "Are we on track?"
   ============================================================ */
const daysUntil = (dateStr?: string): number | null => {
  if (!dateStr) return null;
  const diff = new Date(dateStr).getTime() - Date.now();
  return Math.ceil(diff / 86400000);
};

/* ============================================================
   SALES VIEW — "What do I need to focus on today to sell more?"
   ============================================================ */
const SalesView: React.FC<{
  salesData: SalesData | null;
  leads: LeadItem[];
  quotes: QuoteItem[];
  activity: ActivityItem[];
  customers: Map<number, string>;
}> = ({ salesData, leads, quotes, activity, customers }) => {
  const sd = salesData || { pipelineValue: 0, quotesSent: 0, winRate: 0, revenueMtd: 0, activeLeads: 0, avgDealSize: 0, quotesByState: {} };

  // Attention items
  const now = Date.now();
  const attentionItems: { subject: string; meta: string; severity: string; timer?: string; timerClass?: string; statusLabel?: string; pulse?: boolean; uhbClass?: string; reasons?: { label: string; color: string }[]; sort: number }[] = [];

  // Sent quotes needing follow-up
  quotes.filter(q => q.state === 'sent').forEach(q => {
    const health = computeQuoteHealth(q);
    if (health.state === 'green') return; // healthy, no attention needed
    const sentAge = q.updatedAt ? Math.floor((now - new Date(q.updatedAt).getTime()) / 86400000) : 0;
    const custName = customers.get(q.customerId) || `Customer #${q.customerId}`;
    attentionItems.push({
      subject: q.title,
      meta: `${custName} · Sent ${sentAge}d ago · $${q.totalValue.toLocaleString()}`,
      severity: health.state === 'red' ? 'breached' : 'warning',
      timer: `${sentAge}d`,
      timerClass: health.state === 'red' ? 'red' : 'amber',
      statusLabel: 'FOLLOW UP',
      pulse: health.state === 'red',
      uhbClass: health.className,
      reasons: health.reasons,
      sort: health.state === 'red' ? 0 : 1,
    });
  });

  // Stale / cooling leads
  leads.filter(l => l.status === 'new' || l.status === 'qualified').forEach(l => {
    const health = computeLeadHealth(l);
    if (health.state === 'green') return; // healthy, no attention needed
    const age = Math.floor((now - new Date(l.updatedAt || l.createdAt).getTime()) / 86400000);
    const custName = customers.get(l.customerId) || `Customer #${l.customerId}`;
    attentionItems.push({
      subject: l.subject,
      meta: `${custName} · ${l.status} · ${age}d stale`,
      severity: health.state === 'red' ? 'breached' : 'warning',
      timer: `${age}d`,
      timerClass: health.state === 'red' ? 'red' : 'amber',
      statusLabel: health.reasons[0]?.label || 'STALE LEAD',
      uhbClass: health.className,
      reasons: health.reasons,
      sort: health.state === 'red' ? 0 : 2,
    });
  });

  // Aging draft quotes
  quotes.filter(q => q.state === 'draft' && q.totalValue > 0).forEach(q => {
    const health = computeQuoteHealth(q);
    const custName = customers.get(q.customerId) || `Customer #${q.customerId}`;
    attentionItems.push({
      subject: q.title,
      meta: `${custName} · $${q.totalValue.toLocaleString()} · Ready to send`,
      severity: health.state === 'amber' ? 'warning' : 'info',
      statusLabel: health.reasons[0]?.label || 'SEND QUOTE',
      uhbClass: health.className,
      reasons: health.reasons,
      sort: 3,
    });
  });

  attentionItems.sort((a, b) => a.sort - b.sort);

  // Pipeline summary by state
  const draftQuotes = quotes.filter(q => q.state === 'draft');
  const sentQuotes = quotes.filter(q => q.state === 'sent');
  const acceptedQuotes = quotes.filter(q => q.state === 'accepted');

  // Sales-relevant activity
  const salesTypes = ['QUOTE_CREATED', 'QUOTE_SENT', 'QUOTE_ACCEPTED', 'QUOTE_REJECTED', 'LEAD_CREATED', 'LEAD_CONVERTED', 'CONTRACT_CREATED'];
  const salesActivity = activity.filter(a => salesTypes.includes(a.event_type) || a.severity === 'commercial' || a.reference_type === 'quote' || a.reference_type === 'lead');

  return (
    <>
      <div className="pd-kpi-strip">
        <KpiCard value={`$${sd.pipelineValue.toLocaleString()}`} label="Pipeline Value" color="blue" />
        <KpiCard value={sd.quotesSent} label="Quotes Sent" color="teal" />
        <KpiCard value={`${sd.winRate}%`} label="Win Rate" color={sd.winRate >= 50 ? 'green' : sd.winRate >= 25 ? 'amber' : 'red'} />
        <KpiCard value={`$${sd.revenueMtd.toLocaleString()}`} label="Revenue MTD" color="green" />
        <KpiCard value={sd.activeLeads} label="Active Leads" color="purple" />
        <KpiCard value={`$${sd.avgDealSize.toLocaleString()}`} label="Avg Deal Size" color="teal" />
      </div>

      <div className="pd-attention-panel">
        <h3 className="pd-section-title">
          Needs Your Attention
          {attentionItems.length > 0 && <span className="pd-badge">{attentionItems.length}</span>}
        </h3>
        {attentionItems.length === 0 ? (
          <div className="pd-empty">Pipeline is healthy — keep selling!</div>
        ) : (
          <div className="pd-attention-grid">
            {attentionItems.slice(0, 12).map((item, i) => (
              <AttentionCard key={i} {...item} />
            ))}
          </div>
        )}
      </div>

      {/* Pipeline Summary */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Pipeline Summary</h3>
        <div className="pd-kpi-strip">
          <KpiCard value={draftQuotes.length} label="Drafts" color="purple" />
          <KpiCard value={sentQuotes.length} label="Sent / Awaiting" color="amber" />
          <KpiCard value={acceptedQuotes.length} label="Won" color="green" />
          <KpiCard value={leads.filter(l => l.status === 'new' || l.status === 'qualified').length} label="Active Leads" color="blue" />
        </div>
      </div>

      <div className="pd-activity-panel">
        <h3 className="pd-section-title">Commercial Activity</h3>
        <ActivityStream items={salesActivity.length > 0 ? salesActivity : activity} emptyMsg="No commercial events yet" />
      </div>
    </>
  );
};

const PMView: React.FC<{
  projects: ProjectItem[];
  tickets: TicketItem[];
  workItems: WorkItem[];
  activity: ActivityItem[];
  customers: Map<number, string>;
}> = ({ projects, tickets, workItems, activity, customers }) => {
  const now = new Date();
  const intakeProjects = projects.filter(p => p.state === 'intake');
  const activeProjects = projects.filter(p => p.state === 'active' || p.state === 'planned');
  const projectTasks = (project: ProjectItem) => Array.isArray(project.tasks) ? project.tasks : [];
  const openDeliveryProjectDetail = (projectId: number) => {
    const nextUrl = new URL(window.location.href);
    nextUrl.search = '?page=pet-delivery';
    nextUrl.hash = `project=${projectId}`;
    window.location.assign(nextUrl.toString());
  };
  const projectTicketMetrics = useMemo(() => {
    const statusAwareProgress = (completed: number, inProgress: number, total: number): number => {
      if (total <= 0) return 0;
      const weighted = completed + (inProgress * 0.5);
      return Math.round((weighted / total) * 100);
    };
    const completeStates = new Set(['completed', 'done', 'resolved', 'closed']);
    const inProgressStates = new Set(['in_progress', 'in-progress', 'inprogress', 'started', 'active', 'working']);
    const byProject = new Map<number, TicketItem[]>();

    for (const ticket of tickets) {
      const projectId = Number(ticket.projectId || 0);
      if (projectId <= 0) continue;
      if (ticket.isRollup) continue;
      if (ticket.lifecycleOwner && ticket.lifecycleOwner !== 'project') continue;
      const group = byProject.get(projectId) || [];
      group.push(ticket);
      byProject.set(projectId, group);
    }

    const result = new Map<number, {
      total: number;
      completed: number;
      inProgress: number;
      planned: number;
      progress: number;
      hasTicketData: boolean;
      healthTasks: { completed: boolean }[];
    }>();

    for (const project of projects) {
      const ticketRows = byProject.get(project.id) || [];
      if (ticketRows.length > 0) {
        let completed = 0;
        let inProgress = 0;
        for (const ticket of ticketRows) {
          const status = String(ticket.status || '').toLowerCase().trim();
          if (completeStates.has(status)) {
            completed += 1;
          } else if (inProgressStates.has(status)) {
            inProgress += 1;
          }
        }
        const total = ticketRows.length;
        const planned = Math.max(total - completed - inProgress, 0);
        result.set(project.id, {
          total,
          completed,
          inProgress,
          planned,
          progress: statusAwareProgress(completed, inProgress, total),
          hasTicketData: true,
          healthTasks: [
            ...Array.from({ length: completed }, () => ({ completed: true })),
            ...Array.from({ length: Math.max(total - completed, 0) }, () => ({ completed: false })),
          ],
        });
      } else {
        const fallbackTasks = projectTasks(project);
        const total = fallbackTasks.length;
        const completed = fallbackTasks.filter(t => t.completed).length;
        result.set(project.id, {
          total,
          completed,
          inProgress: 0,
          planned: Math.max(total - completed, 0),
          progress: total > 0 ? pct(completed, total) : 0,
          hasTicketData: false,
          healthTasks: fallbackTasks.map(t => ({ completed: Boolean(t.completed) })),
        });
      }
    }

    return result;
  }, [projects, tickets]);

  // Fetch journey data for all active projects
  const [journeyMap, setJourneyMap] = useState<Record<number, JourneyData>>({});
  useEffect(() => {
    if (activeProjects.length === 0) return;
    const ids = activeProjects.map(p => p.id).join(',');
    api(`health-history/journey?project_ids=${ids}`)
      .then((data: Record<string, JourneyData>) => {
        const map: Record<number, JourneyData> = {};
        for (const [k, v] of Object.entries(data)) {
          map[Number(k)] = v;
        }
        setJourneyMap(map);
      })
      .catch(() => { /* fall back to no journey data */ });
  }, [projects.length]); // re-fetch when project count changes
  const totalSold = activeProjects.reduce((sum, p) => sum + (p.soldHours || 0), 0);
  const totalHoursUsed = activeProjects.reduce((sum, p) => sum + (p.malleableData?.hours_used ?? 0), 0);
  const totalTasks = activeProjects.reduce((sum, p) => sum + (projectTicketMetrics.get(p.id)?.total || 0), 0);
  const totalCompleted = activeProjects.reduce((sum, p) => sum + (projectTicketMetrics.get(p.id)?.completed || 0), 0);
  const overdueProjects = activeProjects.filter(p => p.endDate && new Date(p.endDate) < now);

  // Projects at risk: overdue, over-budget, or burn-ahead
  const attentionItems = activeProjects
    .map(p => {
      const metrics = projectTicketMetrics.get(p.id);
      const health = computeProjectHealth({ ...p, tasks: metrics?.healthTasks || projectTasks(p) });
      if (health.state === 'green' || health.state === 'grey') return null;
      const taskCount = metrics?.total || 0;
      const completedCount = metrics?.completed || 0;
      const hoursUsed = p.malleableData?.hours_used ?? 0;
      const soldH = p.soldHours || 0;
      const burnPct = soldH > 0 ? Math.round((hoursUsed / soldH) * 100) : 0;
      const isOverdue = p.endDate ? new Date(p.endDate) < now : false;
      const custName = customers.get(p.customerId) || '';

      return {
        subject: p.name,
        meta: `${custName ? custName + ' · ' : ''}${completedCount}/${taskCount} tasks · ${hoursUsed}h / ${soldH}h`,
        severity: health.state === 'red' ? 'breached' as const : 'warning' as const,
        timer: isOverdue ? `${Math.abs(daysUntil(p.endDate)!)}d overdue` : `${burnPct}% burn`,
        timerClass: health.state === 'red' ? 'red' : 'amber',
        statusLabel: health.reasons.map(r => r.label).join(' · '),
        pulse: health.state === 'red',
        uhbClass: health.className,
        reasons: health.reasons,
        sort: health.state === 'red' ? 0 : 1,
      };
    })
    .filter((x): x is NonNullable<typeof x> => x !== null)
    .sort((a, b) => a.sort - b.sort);

  // Delivery activity (API returns UPPERCASE event types)
  const deliveryTypes = ['PROJECT_CREATED', 'TASK_COMPLETED', 'TASK_STARTED', 'MILESTONE_COMPLETED', 'PROJECT_COMPLETED', 'PROJECT_STATUS_CHANGED', 'TIME_ENTRY_LOGGED', 'TIME_ENTRY_APPROVED', 'DELIVERY_TASK_COMPLETED', 'DELIVERY_TASK_STARTED'];
  const deliveryActivity = activity.filter(a => deliveryTypes.includes(a.event_type) || a.reference_type === 'project' || a.reference_type === 'milestone');

  return (
    <>
      <div className="pd-kpi-strip">
        {intakeProjects.length > 0 && (
          <KpiCard value={intakeProjects.length} label="Intake" color="purple" />
        )}
        <KpiCard value={activeProjects.length} label="Active Projects" color="blue" />
        <KpiCard value={`${totalSold}h`} label="Total Sold Hours" color="teal" />
        <KpiCard value={`${totalHoursUsed}h`} label="Hours Used" color="purple" />
        <KpiCard value={overdueProjects.length} label="Overdue Projects" color={overdueProjects.length > 0 ? 'red' : 'green'} />
        <KpiCard value={`${totalCompleted}/${totalTasks}`} label="Tasks Completed" color={totalCompleted === totalTasks ? 'green' : 'blue'} />
      </div>

      <div className="pd-attention-panel">
        <h3 className="pd-section-title">
          Projects at Risk
          {attentionItems.length > 0 && <span className="pd-badge">{attentionItems.length}</span>}
        </h3>
        {attentionItems.length === 0 ? (
          <div className="pd-empty">All projects are on track.</div>
        ) : (
          <div className="pd-attention-grid">
            {attentionItems.map((item, i) => (
              <AttentionCard key={i} {...item} />
            ))}
          </div>
        )}
      </div>

      {/* Projects Being Delivered */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">
          Projects Being Delivered
          <span className="pd-badge" style={{ background: '#0d6efd' }}>{activeProjects.length}</span>
        </h3>
        <div className="pd-project-grid">
          {activeProjects.map(p => {
            const metrics = projectTicketMetrics.get(p.id);
            const taskCount = metrics?.total || 0;
            const completedCount = metrics?.completed || 0;
            const progress = metrics?.progress || 0;
            const hoursUsed = p.malleableData?.hours_used ?? 0;
            const soldH = p.soldHours || 0;
            const burnPct = soldH > 0 ? Math.round((hoursUsed / soldH) * 100) : 0;
            const days = daysUntil(p.endDate);
            const isOverdue = days !== null && days < 0;
            const custName = customers.get(p.customerId) || `Customer #${p.customerId}`;
            const pm = p.malleableData?.pm || '--';
            const projHealth = computeProjectHealth({ ...p, tasks: metrics?.healthTasks || projectTasks(p) });

            // Trajectory indicator: compare actual burn rate vs planned burn rate
            let trajLabel = '\u2192'; // → stable
            let trajClass = 'jb-traj-stable';
            if (p.startDate && p.endDate && soldH > 0) {
              const totalDays = Math.max((new Date(p.endDate).getTime() - new Date(p.startDate).getTime()) / 86400000, 1);
              const elapsedDays = Math.max((Date.now() - new Date(p.startDate).getTime()) / 86400000, 1);
              const plannedRate = soldH / totalDays;
              const actualRate = hoursUsed / elapsedDays;
              const paceRatio = actualRate / plannedRate;
              if (paceRatio > 1.15) { trajLabel = '\u25BC'; trajClass = 'jb-traj-down'; } // ▼ slipping
              else if (paceRatio < 0.85) { trajLabel = '\u25B2'; trajClass = 'jb-traj-up'; } // ▲ improving
            }

            // Burn variance
            const burnVariance = soldH > 0 ? Math.round(((hoursUsed - soldH) / soldH) * 100) : null;

            // Last activity: find most recent feed event for this project
            const projActivity = activity.filter(a => a.reference_id === String(p.id) && a.reference_type === 'project');
            const lastEvent = projActivity.length > 0 ? projActivity.reduce((latest, a) => new Date(a.occurred_at) > new Date(latest.occurred_at) ? a : latest) : null;
            const lastActivityStr = lastEvent ? timeAgo(lastEvent.occurred_at) : null;
            const lastActivityDays = lastEvent ? Math.floor((Date.now() - new Date(lastEvent.occurred_at).getTime()) / 86400000) : null;

            return (
              <div
                key={p.id}
                className={`pd-project-card pd-clickable ${projHealth.className}`}
                role="button"
                tabIndex={0}
                aria-label={`Open project details for ${p.name}`}
                onClick={() => openDeliveryProjectDetail(p.id)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openDeliveryProjectDetail(p.id);
                  }
                }}
              >
                <div className="pd-project-card-header">
                  <div className="pd-project-card-title">{p.name}</div>
                  <span className={`pd-project-state-badge state-${p.state}`}>{p.state.replace('_', ' ')}</span>
                </div>
                <div className="pd-project-customer">{custName}</div>
                {projHealth.reasons.length > 0 && (
                  <div className="pd-project-risk-row">
                    {projHealth.reasons.map((r, ri) => (
                      <span key={ri} className={`pd-risk-badge pd-risk-${r.color}`}>
                        {r.color === 'red' ? '⚠' : '△'} {r.label}
                      </span>
                    ))}
                  </div>
                )}

                {/* Journey Timeline Bar + Trajectory */}
                <JourneyBar
                  segments={journeyMap[p.id]?.segments || []}
                  progress={progress}
                  trajectoryLabel={trajLabel}
                  trajectoryClass={trajClass}
                  trajectoryTitle={trajClass === 'jb-traj-down' ? 'Slipping — burning faster than plan' : trajClass === 'jb-traj-up' ? 'Improving — burning slower than plan' : 'Stable'}
                />

                {/* Meta grid */}
                <div className="pd-project-meta-grid">
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">Tasks</span>
                    <span className="pd-project-meta-value">{completedCount}/{taskCount}</span>
                  </div>
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">Hours</span>
                    <span className={`pd-project-meta-value ${burnPct > 100 ? 'pd-over-budget' : ''}`}>
                      {hoursUsed}/{soldH}h
                      {burnVariance !== null && burnVariance !== 0 && (
                        <span className={`pd-burn-variance ${burnVariance > 0 ? 'pd-burn-over' : 'pd-burn-under'}`}>
                          {' '}({burnVariance > 0 ? '+' : ''}{burnVariance}%)
                        </span>
                      )}
                    </span>
                  </div>
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">Deadline</span>
                    <span className={`pd-project-meta-value ${isOverdue ? 'pd-overdue-text' : ''}`}>
                      {days === null ? '--' : isOverdue ? `${Math.abs(days)}d overdue` : `${days}d left`}
                    </span>
                  </div>
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">PM</span>
                    <span className="pd-project-meta-value">{pm}</span>
                  </div>
                  {lastActivityStr && (
                    <div className="pd-project-meta-item">
                      <span className="pd-project-meta-label">Last</span>
                      <span className={`pd-project-meta-value ${lastActivityDays !== null && lastActivityDays > 5 ? 'pd-stale-activity' : ''}`}>{lastActivityStr}</span>
                    </div>
                  )}
                  {(journeyMap[p.id]?.escalations ?? 0) > 0 && (
                    <div className="pd-project-meta-item">
                      <span className="pd-project-meta-label">Escalations</span>
                      <span className="pd-project-meta-value pd-escalation-count">
                        ▴ {journeyMap[p.id].escalations} open
                      </span>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="pd-activity-panel">
        <h3 className="pd-section-title">Delivery Activity</h3>
        <ActivityStream items={deliveryActivity.length > 0 ? deliveryActivity : activity} emptyMsg="No delivery events yet" />
      </div>
    </>
  );
};

/* ============================================================
   TICKET DETAIL VIEW — Full-featured ticket panel
   ============================================================ */
interface TimeEntryItem {
  id: number;
  employeeId: number;
  ticketId: number;
  start: string;
  end: string;
  duration: number;
  description: string;
  billable: boolean;
  status: string;
  correctsEntryId?: number | null;
  isCorrection?: boolean;
  malleableData?: Record<string, any>;
  createdAt: string | null;
}

interface EmployeeItem {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  email: string;
  jobTitle?: string;
  teamIds?: number[];
}

interface TicketDetailData {
  customer: { id: number; name: string; contactEmail?: string; status?: string } | null;
  workItem: WorkItem | null;
  employees: { wpUserId: number; firstName: string; lastName: string; id?: number }[];
  conversations: { uuid: string; subject: string; timeline: { id: number; type: string; payload: any; occurred_at: string; actor_id: number }[] }[];
  timeEntries: TimeEntryItem[];
}

const TicketDetailPanel: React.FC<{
  ticket: TicketItem;
  workItems: WorkItem[];
  customers: Map<number, string>;
  activity: ActivityItem[];
  onBack: () => void;
}> = ({ ticket, workItems, activity, onBack }) => {
  const [detail, setDetail] = useState<TicketDetailData>({ customer: null, workItem: null, employees: [], conversations: [], timeEntries: [] });
  const [loading, setLoading] = useState(true);
  const [showLogForm, setShowLogForm] = useState(false);
  const [editingEntryId, setEditingEntryId] = useState<number | null>(null);
  const [logFormData, setLogFormData] = useState({ description: '', hours: 0, minutes: 30, billable: ticket.isBillableDefault ?? true });
  const [logFormSaving, setLogFormSaving] = useState(false);
  const [logFormError, setLogFormError] = useState<string | null>(null);
  const [assignLoading, setAssignLoading] = useState(false);

  const wi = workItems.find(w => w.source_type === 'ticket' && w.source_id === String(ticket.id)) || detail.workItem;

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const [custRes, wiRes, empRes, convRes, timeRes] = await Promise.all([
          api(`customers?id=${ticket.customerId}`).catch(() => []),
          api(`work-items/by-source?source_type=ticket&source_id=${ticket.id}`).catch(() => null),
          api('employees').catch(() => []),
          api(`conversations?context_type=ticket&context_id=${ticket.id}&limit=50`).catch(() => null),
          api(`time-entries?ticket_id=${ticket.id}`).catch(() => []),
        ]);
        const cust = Array.isArray(custRes) ? custRes.find((c: any) => c.id === ticket.customerId) : null;
        const convos = convRes ? (convRes.uuid ? [convRes] : []) : [];
        const entries: TimeEntryItem[] = Array.isArray(timeRes) ? timeRes : [];
        setDetail({ customer: cust || null, workItem: wiRes, employees: Array.isArray(empRes) ? empRes : [], conversations: convos, timeEntries: entries });
      } catch (_) {}
      setLoading(false);
    };
    load();
  }, [ticket.id]);

  const getEmployeeName = (userId: string | number | null) => {
    if (!userId) return 'Unassigned';
    const emp = detail.employees.find(e => String(e.wpUserId) === String(userId));
    if (emp) return `${emp.firstName} ${emp.lastName}`;
    const me = window.petSettings?.currentUserId;
    if (me && String(userId) === String(me)) return 'You';
    return `User #${userId}`;
  };

  const getEmployeeNameById = (empId: number) => {
    const emp = detail.employees.find(e => e.id === empId || e.wpUserId === empId);
    if (emp) return `${emp.firstName} ${emp.lastName}`;
    return `Employee #${empId}`;
  };

  const formatDate = (d?: string | null) => d ? new Date(d).toLocaleString() : '--';

  const slaMins = wi?.sla_time_remaining ?? null;
  const slaColor = slaMins === null ? '' : slaMins < 0 ? 'red' : slaMins <= 60 ? 'amber' : 'green';

  // Ticket-specific activity
  const ticketActivity = activity.filter(a =>
    (a.reference_type === 'ticket') || ['ticket_created', 'ticket_assigned', 'ticket_status_changed', 'sla_warning', 'sla_breached', 'ticket_resolved'].includes(a.event_type)
  );

  // Conversation messages
  const messages = detail.conversations.length > 0
    ? detail.conversations[0].timeline
        .filter(e => e.type === 'MessagePosted')
        .sort((a, b) => a.id - b.id)
    : [];

  // Time entries (Work Log) — sorted newest first
  const timeEntries = [...detail.timeEntries].sort(
    (a, b) => new Date(b.start).getTime() - new Date(a.start).getTime()
  );
  const totalMinutes = timeEntries.reduce((sum, e) => sum + e.duration, 0);
  const billableMinutes = timeEntries.filter(e => e.billable).reduce((sum, e) => sum + e.duration, 0);
  const totalHours = (totalMinutes / 60).toFixed(1);
  const billableHours = (billableMinutes / 60).toFixed(1);

  // Resolve current user's employee ID
  const currentUserId = window.petSettings?.currentUserId || 0;
  const currentEmployee = detail.employees.find(e => String(e.wpUserId) === String(currentUserId));
  const currentEmployeeId = currentEmployee?.id ?? currentEmployee?.wpUserId ?? currentUserId;

  // Known queues (matches seed data; extend as departments grow)
  const QUEUES = [
    { id: 'support', label: 'Support' },
    { id: 'projects', label: 'Projects' },
    { id: 'internal', label: 'Internal' },
  ];

  const refreshWorkItem = async () => {
    const fresh = await api(`work-items/by-source?source_type=ticket&source_id=${ticket.id}`).catch(() => null);
    setDetail(prev => ({ ...prev, workItem: fresh }));
  };

  const handleAssignEmployee = async (userId: string) => {
    if (!userId) return;
    setAssignLoading(true);
    try {
      await apiPost(`tickets/${ticket.id}/assign/employee`, { employeeUserId: userId });
      await refreshWorkItem();
    } catch (_) {}
    setAssignLoading(false);
  };

  const handleAssignTeam = async (queueId: string) => {
    if (!queueId) return;
    setAssignLoading(true);
    try {
      await apiPost(`tickets/${ticket.id}/assign/team`, { queueId });
      await refreshWorkItem();
    } catch (_) {}
    setAssignLoading(false);
  };

  const handlePull = async () => {
    setAssignLoading(true);
    try {
      await apiPost(`tickets/${ticket.id}/pull`, {});
      await refreshWorkItem();
    } catch (_) {}
    setAssignLoading(false);
  };

  const resetLogForm = () => {
    setLogFormData({ description: '', hours: 0, minutes: 30, billable: ticket.isBillableDefault ?? true });
    setShowLogForm(false);
    setEditingEntryId(null);
    setLogFormError(null);
  };

  const handleLogWork = async () => {
    const totalMins = logFormData.hours * 60 + logFormData.minutes;
    if (!logFormData.description.trim()) { setLogFormError('Description is required'); return; }
    if (totalMins <= 0) { setLogFormError('Duration must be greater than zero'); return; }

    setLogFormSaving(true);
    setLogFormError(null);

    const now = new Date();
    const start = new Date(now.getTime() - totalMins * 60000);
    const startStr = start.toISOString().replace('T', ' ').slice(0, 19);
    const endStr = now.toISOString().replace('T', ' ').slice(0, 19);

    try {
      if (editingEntryId) {
        // Update existing draft
        await apiPut(`time-entries/${editingEntryId}`, {
          description: logFormData.description,
          start: startStr,
          end: endStr,
          isBillable: logFormData.billable,
        });
      } else {
        // Create new entry
        await apiPost('time-entries', {
          employeeId: currentEmployeeId,
          ticketId: ticket.id,
          start: startStr,
          end: endStr,
          isBillable: logFormData.billable,
          description: logFormData.description,
        });
      }
      // Refresh time entries
      const freshEntries = await api(`time-entries?ticket_id=${ticket.id}`).catch(() => []);
      setDetail(prev => ({ ...prev, timeEntries: Array.isArray(freshEntries) ? freshEntries : [] }));
      resetLogForm();
    } catch (err) {
      setLogFormError(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setLogFormSaving(false);
    }
  };


  const startEdit = (entry: TimeEntryItem) => {
    setEditingEntryId(entry.id);
    setLogFormData({
      description: entry.description,
      hours: Math.floor(entry.duration / 60),
      minutes: entry.duration % 60,
      billable: entry.billable,
    });
    setShowLogForm(true);
    setLogFormError(null);
  };

  return (
    <div className="pd-ticket-detail">
      {/* Back nav */}
      <button className="pd-ticket-back" onClick={onBack}>
        \u2190 Back to Support Dashboard
      </button>

      {/* Header */}
      <div className="pd-ticket-header">
        <div className="pd-ticket-header-left">
          <h2 className="pd-ticket-title">{ticket.subject}</h2>
          <div className="pd-ticket-meta">
            <span className="pd-ticket-id">#{ticket.id}</span>
            <span className={`pd-ticket-badge status-${ticket.status}`}>{ticket.status}</span>
            <span className={`pd-ticket-badge priority-${ticket.priority}`}>{ticket.priority}</span>
            {ticket.category && <span className="pd-ticket-badge cat">{ticket.category}</span>}
            {ticket.intake_source === 'pulseway' && (
              <span className="pd-ticket-badge" style={{ background: '#e8f4fd', color: '#0073aa', fontWeight: 600 }}>
                {'\u{1F5A5}\uFE0F'} Pulseway RMM
              </span>
            )}
          </div>
        </div>
        <div className="pd-ticket-header-right">
          <div className="pd-ticket-opened">Opened {formatDate(ticket.openedAt || ticket.createdAt)}</div>
        </div>
      </div>

      {/* SLA KPI strip */}
      <div className="pd-kpi-strip">
        <div className={`pd-kpi-card ${slaColor}`} style={{ borderTopWidth: '4px' }}>
          <div className="pd-kpi-value" style={{ color: slaColor === 'red' ? '#dc3545' : slaColor === 'amber' ? '#f0ad4e' : slaColor === 'green' ? '#28a745' : undefined }}>
            {formatMinutes(slaMins)}
          </div>
          <div className="pd-kpi-label">SLA Time Remaining</div>
        </div>
        <KpiCard value={wi?.priority_score?.toFixed(0) ?? '--'} label="Priority Score" color="purple" />
        <KpiCard value={ticket.response_due_at ? new Date(ticket.response_due_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--'} label="Response Due" color="blue" />
        <KpiCard value={ticket.resolution_due_at ? new Date(ticket.resolution_due_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--'} label="Resolution Due" color="teal" />
        {wi?.signals && wi.signals.length > 0 && (
          <KpiCard value={wi.signals.length} label="Active Signals" color="red" />
        )}
      </div>

      {loading ? (
        <div className="pd-loading" style={{ padding: '40px 0' }}>
          <div className="pd-spinner" />
          Loading ticket details...
        </div>
      ) : (
        <div className="pd-ticket-grid">
          {/* Left column: Description + Conversation + Activity */}
          <div className="pd-ticket-main">
            {/* Description */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">Description</h3>
              <div className="pd-ticket-description">{ticket.description || 'No description provided.'}</div>
            </div>

            {/* Work Log — primary "what got done" section */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">
                Work Log
                {timeEntries.length > 0 && <span className="pd-badge">{timeEntries.length}</span>}
                <button
                  className="pd-log-work-btn"
                  onClick={() => { resetLogForm(); setShowLogForm(true); }}
                >
                  + Log Work
                </button>
              </h3>

              {/* Inline Log Work / Edit form */}
              {showLogForm && (
                <div className="pd-logwork-form">
                  <div className="pd-logwork-form-title">{editingEntryId ? 'Edit Draft Entry' : 'Log Work'}</div>
                  <textarea
                    className="pd-logwork-desc"
                    placeholder="What did you do?"
                    value={logFormData.description}
                    onChange={e => setLogFormData(prev => ({ ...prev, description: e.target.value }))}
                    rows={3}
                  />
                  <div className="pd-logwork-row">
                    <div className="pd-logwork-duration">
                      <label>Duration</label>
                      <div className="pd-logwork-duration-inputs">
                        <input
                          type="number"
                          min={0}
                          max={23}
                          value={logFormData.hours}
                          onChange={e => setLogFormData(prev => ({ ...prev, hours: Math.max(0, parseInt(e.target.value) || 0) }))}
                        />
                        <span>h</span>
                        <input
                          type="number"
                          min={0}
                          max={59}
                          step={5}
                          value={logFormData.minutes}
                          onChange={e => setLogFormData(prev => ({ ...prev, minutes: Math.max(0, Math.min(59, parseInt(e.target.value) || 0)) }))}
                        />
                        <span>m</span>
                      </div>
                    </div>
                    <label className="pd-logwork-billable">
                      <input
                        type="checkbox"
                        checked={logFormData.billable}
                        onChange={e => setLogFormData(prev => ({ ...prev, billable: e.target.checked }))}
                      />
                      Billable
                    </label>
                  </div>
                  {logFormError && <div className="pd-logwork-error">{logFormError}</div>}
                  <div className="pd-logwork-actions">
                    <button className="pd-logwork-submit" onClick={handleLogWork} disabled={logFormSaving}>
                      {logFormSaving ? 'Saving...' : editingEntryId ? 'Update' : 'Log Work'}
                    </button>
                    <button className="pd-logwork-cancel" onClick={resetLogForm}>Cancel</button>
                  </div>
                </div>
              )}

              {timeEntries.length === 0 && !showLogForm ? (
                <div className="pd-empty" style={{ padding: '20px' }}>No time entries logged yet.</div>
              ) : timeEntries.length > 0 ? (
                <>
                  <div className="pd-worklog-summary">
                    <div className="pd-worklog-stat">
                      <span className="pd-worklog-stat-value">{totalHours}h</span>
                      <span className="pd-worklog-stat-label">Total</span>
                    </div>
                    <div className="pd-worklog-stat">
                      <span className="pd-worklog-stat-value">{billableHours}h</span>
                      <span className="pd-worklog-stat-label">Billable</span>
                    </div>
                    <div className="pd-worklog-stat">
                      <span className="pd-worklog-stat-value">{timeEntries.length}</span>
                      <span className="pd-worklog-stat-label">Entries</span>
                    </div>
                  </div>
                  <div className="pd-worklog-list">
                    {timeEntries.map(entry => (
                      <div key={entry.id} className={`pd-worklog-entry ${editingEntryId === entry.id ? 'pd-worklog-editing' : ''}`}>
                        <div className="pd-worklog-avatar">
                          {getEmployeeNameById(entry.employeeId).charAt(0).toUpperCase()}
                        </div>
                        <div className="pd-worklog-body">
                          <div className="pd-worklog-header">
                            <span className="pd-worklog-author">{getEmployeeNameById(entry.employeeId)}</span>
                            <div className="pd-worklog-tags">
                              <span className={`pd-worklog-status status-${entry.status}`}>{entry.status}</span>
                              {entry.billable && <span className="pd-worklog-billable">billable</span>}
                              {entry.isCorrection && <span className="pd-worklog-correction">correction</span>}
                              {entry.status === 'draft' && (entry.employeeId === currentEmployeeId || entry.employeeId === currentUserId) && (
                                <button className="pd-worklog-edit-btn" onClick={() => startEdit(entry)} title="Edit draft">
                                  \u270E
                                </button>
                              )}
                            </div>
                          </div>
                          <div className="pd-worklog-desc">{entry.description}</div>
                          <div className="pd-worklog-meta">
                            <span className="pd-worklog-duration">{formatMinutes(entry.duration)}</span>
                            <span className="pd-worklog-date">
                              {new Date(entry.start).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}
                              {' \u2022 '}
                              {new Date(entry.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              {' \u2013 '}
                              {new Date(entry.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </>
              ) : null}
            </div>

            {/* Conversation Thread */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">
                Discussion
                {messages.length > 0 && <span className="pd-badge">{messages.length}</span>}
              </h3>
              {messages.length === 0 ? (
                <div className="pd-empty" style={{ padding: '20px' }}>No conversation yet.</div>
              ) : (
                <div className="pd-conversation-thread">
                  {messages.map(msg => (
                    <div key={msg.id} className="pd-conversation-msg">
                      <div className="pd-msg-avatar">
                        {getEmployeeName(msg.actor_id).charAt(0).toUpperCase()}
                      </div>
                      <div className="pd-msg-body">
                        <div className="pd-msg-header">
                          <span className="pd-msg-author">{getEmployeeName(msg.actor_id)}</span>
                          <span className="pd-msg-time">{timeAgo(msg.occurred_at)}</span>
                        </div>
                        <div className="pd-msg-text">{msg.payload?.body || ''}</div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Activity */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">Activity</h3>
              <ActivityStream items={ticketActivity} emptyMsg="No activity recorded" />
            </div>
          </div>

          {/* Right column: Info sidebar */}
          <div className="pd-ticket-sidebar">
            {/* Assignment */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">Assignment</h3>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Assigned to</span>
                <span className="pd-ticket-field-value">{getEmployeeName(wi?.assigned_user_id ?? null)}</span>
              </div>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Department</span>
                <span className="pd-ticket-field-value">{wi ? (wi.department_id === 'support' ? 'Support' : wi.department_id || '--') : '--'}</span>
              </div>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Status</span>
                <span className="pd-ticket-field-value">{wi?.status || '--'}</span>
              </div>

              {/* Assignment controls */}
              <div className="pd-assign-controls">
                <div className="pd-assign-row">
                  <label className="pd-assign-label">Assign to</label>
                  <select
                    className="pd-assign-select"
                    value={wi?.assigned_user_id || ''}
                    onChange={e => handleAssignEmployee(e.target.value)}
                    disabled={assignLoading}
                  >
                    <option value="">Unassigned</option>
                    {detail.employees.map(emp => (
                      <option key={emp.wpUserId} value={String(emp.wpUserId)}>
                        {emp.firstName} {emp.lastName}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="pd-assign-row">
                  <label className="pd-assign-label">Move to queue</label>
                  <select
                    className="pd-assign-select"
                    value={wi?.department_id || ''}
                    onChange={e => handleAssignTeam(e.target.value)}
                    disabled={assignLoading}
                  >
                    <option value="">-- select --</option>
                    {QUEUES.map(q => (
                      <option key={q.id} value={q.id}>{q.label}</option>
                    ))}
                  </select>
                </div>
                <button
                  className="pd-assign-pull-btn"
                  onClick={handlePull}
                  disabled={assignLoading || String(wi?.assigned_user_id) === String(currentUserId)}
                  title={String(wi?.assigned_user_id) === String(currentUserId) ? 'Already assigned to you' : 'Assign this ticket to yourself'}
                >
                  {assignLoading ? 'Updating...' : '\u{1F91A} Pull to Me'}
                </button>
              </div>
            </div>

            {/* Customer */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">Customer</h3>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Name</span>
                <span className="pd-ticket-field-value">{detail.customer?.name || `Customer #${ticket.customerId}`}</span>
              </div>
              {detail.customer?.contactEmail && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Email</span>
                  <span className="pd-ticket-field-value">{detail.customer.contactEmail}</span>
                </div>
              )}
              {detail.customer?.status && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Status</span>
                  <span className="pd-ticket-field-value">{detail.customer.status}</span>
                </div>
              )}
            </div>

            {/* SLA Detail */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">SLA Detail</h3>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Response Due</span>
                <span className="pd-ticket-field-value">{formatDate(ticket.response_due_at)}</span>
              </div>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Resolution Due</span>
                <span className="pd-ticket-field-value">{formatDate(ticket.resolution_due_at)}</span>
              </div>
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Time Remaining</span>
                <span className={`pd-ticket-field-value pd-sla-timer ${slaColor}`}>{formatMinutes(slaMins)}</span>
              </div>
              {ticket.resolvedAt && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Resolved At</span>
                  <span className="pd-ticket-field-value">{formatDate(ticket.resolvedAt)}</span>
                </div>
              )}
              {ticket.closedAt && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Closed At</span>
                  <span className="pd-ticket-field-value">{formatDate(ticket.closedAt)}</span>
                </div>
              )}
            </div>

            {/* Ticket Metadata */}
            <div className="pd-ticket-section">
              <h3 className="pd-section-title">Details</h3>
              {ticket.lifecycleOwner && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Lifecycle</span>
                  <span className="pd-ticket-field-value">{ticket.lifecycleOwner}</span>
                </div>
              )}
              {ticket.lifecycleOwner === 'project' && ticket.soldMinutes != null && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Sold</span>
                  <span className="pd-ticket-field-value">{(ticket.soldMinutes / 60).toFixed(1)}h</span>
                </div>
              )}
              {ticket.lifecycleOwner === 'project' && ticket.estimatedMinutes != null && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Estimated</span>
                  <span className="pd-ticket-field-value">{(ticket.estimatedMinutes / 60).toFixed(1)}h</span>
                </div>
              )}
              {ticket.isBaselineLocked && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Baseline</span>
                  <span className="pd-ticket-field-value">Locked</span>
                </div>
              )}
              {ticket.isRollup && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Rollup</span>
                  <span className="pd-ticket-field-value">Yes (no direct time)</span>
                </div>
              )}
              {ticket.intake_source && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Source</span>
                  <span className="pd-ticket-field-value">
                    {ticket.intake_source === 'pulseway' ? (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', background: '#e8f4fd', color: '#0073aa', padding: '2px 8px', borderRadius: '10px', fontSize: '0.85em', fontWeight: 600 }}>
                        {'\u{1F5A5}\uFE0F'} Pulseway RMM
                      </span>
                    ) : ticket.intake_source}
                  </span>
                </div>
              )}
              {ticket.intake_source === 'pulseway' && (
                <div style={{ marginTop: '12px' }}>
                  <button
                    className="pd-log-work-btn"
                    style={{ width: '100%', opacity: 0.6, cursor: 'not-allowed', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px', padding: '8px 12px', background: '#0073aa', color: '#fff', border: 'none', borderRadius: '4px', fontWeight: 600, fontSize: '0.9em' }}
                    disabled
                    title="Remote Connect will be available when Pulseway agent access is configured"
                  >
                    {'\u{1F517}'} Remote Connect
                  </button>
                  <div style={{ fontSize: '0.78em', color: '#888', marginTop: '4px', textAlign: 'center' }}>Requires Pulseway agent access</div>
                </div>
              )}
              {ticket.subcategory && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Subcategory</span>
                  <span className="pd-ticket-field-value">{ticket.subcategory}</span>
                </div>
              )}
              {ticket.billingContextType && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Billing Context</span>
                  <span className="pd-ticket-field-value">{ticket.billingContextType}</span>
                </div>
              )}
              {ticket.isBillableDefault !== undefined && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Billable Default</span>
                  <span className="pd-ticket-field-value">{ticket.isBillableDefault ? 'Yes' : 'No'}</span>
                </div>
              )}
              <div className="pd-ticket-field">
                <span className="pd-ticket-field-label">Created</span>
                <span className="pd-ticket-field-value">{formatDate(ticket.createdAt)}</span>
              </div>
            </div>

            {/* Signals */}
            {wi?.signals && wi.signals.length > 0 && (
              <div className="pd-ticket-section">
                <h3 className="pd-section-title">
                  Advisory Signals
                  <span className="pd-badge">{wi.signals.length}</span>
                </h3>
                <div className="pd-signal-list">
                  {wi.signals.map((sig, i) => (
                    <div key={i} className={`pd-signal-item severity-${sig.severity}`}>
                      <div className="pd-signal-type">{sig.type}</div>
                      <div className="pd-signal-message">{sig.message}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

/* ============================================================
   TIMESHEET VIEW — "Where is the time going?"
   ============================================================ */
const TimesheetView: React.FC<{
  timeEntries: TimeEntryItem[];
  employees: EmployeeItem[];
  tickets: TicketItem[];
  customers: Map<number, string>;
  onStaffClick: (employeeId: number) => void;
}> = ({ timeEntries, employees, tickets, customers, onStaffClick }) => {
  const now = new Date();

  // Month boundaries for last 3 months + current
  const getMonthBounds = (offset: number) => {
    const d = new Date(now.getFullYear(), now.getMonth() + offset, 1);
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59);
    return { start: d, end, label: d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) };
  };
  const months = [-3, -2, -1, 0].map(getMonthBounds);

  // Build lookup maps
  const ticketCustomerMap = new Map<number, number>();
  tickets.forEach(t => ticketCustomerMap.set(t.id, t.customerId));
  const empNameMap = new Map<number, string>();
  employees.forEach(e => empNameMap.set(e.id, `${e.firstName} ${e.lastName}`));

  const getDept = (e: TimeEntryItem) => e.malleableData?.department || 'support';
  const getRate = (e: TimeEntryItem) => e.malleableData?.billing_rate || 0;

  // Filter out negative-duration reversal rows for KPI display
  const positiveEntries = timeEntries.filter(e => e.duration > 0);

  // --- KPIs ---
  const totalHours = positiveEntries.reduce((s, e) => s + e.duration, 0) / 60;
  const billableEntries = positiveEntries.filter(e => e.billable);
  const billableHours = billableEntries.reduce((s, e) => s + e.duration, 0) / 60;
  const billingValue = billableEntries.reduce((s, e) => s + (e.duration / 60) * getRate(e), 0);
  const nonBillableHours = totalHours - billableHours;
  const activeStaff = new Set(positiveEntries.map(e => e.employeeId)).size;
  const avgRate = billableHours > 0 ? billingValue / billableHours : 0;

  // --- Customer billing table ---
  const custEntries = new Map<number, TimeEntryItem[]>();
  positiveEntries.forEach(e => {
    const cid = ticketCustomerMap.get(e.ticketId) || 0;
    if (!custEntries.has(cid)) custEntries.set(cid, []);
    custEntries.get(cid)!.push(e);
  });

  const customerBilling = Array.from(custEntries.entries()).map(([custId, entries]) => ({
    custId,
    name: customers.get(custId) || `Customer #${custId}`,
    months: months.map(m => {
      const me = entries.filter(e => { const d = new Date(e.start); return d >= m.start && d <= m.end; });
      return {
        hours: me.filter(e => e.billable).reduce((s, e) => s + e.duration, 0) / 60,
        value: me.filter(e => e.billable).reduce((s, e) => s + (e.duration / 60) * getRate(e), 0),
      };
    }),
  })).sort((a, b) => b.months.reduce((s, m) => s + m.value, 0) - a.months.reduce((s, m) => s + m.value, 0));

  const daysElapsed = now.getDate();
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const projMul = daysElapsed > 0 ? daysInMonth / daysElapsed : 1;

  // --- Department matrix ---
  const deptData = new Map<string, { hours: number; billableHours: number; value: number; staff: Set<number> }>();
  positiveEntries.forEach(e => {
    const dept = getDept(e);
    if (!deptData.has(dept)) deptData.set(dept, { hours: 0, billableHours: 0, value: 0, staff: new Set() });
    const d = deptData.get(dept)!;
    d.hours += e.duration / 60;
    if (e.billable) { d.billableHours += e.duration / 60; d.value += (e.duration / 60) * getRate(e); }
    d.staff.add(e.employeeId);
  });

  // --- Staff breakdown ---
  const empEntries = new Map<number, TimeEntryItem[]>();
  positiveEntries.forEach(e => {
    if (!empEntries.has(e.employeeId)) empEntries.set(e.employeeId, []);
    empEntries.get(e.employeeId)!.push(e);
  });

  const staffData = Array.from(empEntries.entries()).map(([empId, entries]) => ({
    empId,
    name: empNameMap.get(empId) || `Employee #${empId}`,
    totalHours: entries.reduce((s, e) => s + e.duration, 0) / 60,
    billableHours: entries.filter(e => e.billable).reduce((s, e) => s + e.duration, 0) / 60,
    value: entries.filter(e => e.billable).reduce((s, e) => s + (e.duration / 60) * getRate(e), 0),
    draftHours: entries.filter(e => e.status === 'draft').reduce((s, e) => s + e.duration, 0) / 60,
    submittedHours: entries.filter(e => e.status === 'submitted').reduce((s, e) => s + e.duration, 0) / 60,
    lockedHours: entries.filter(e => e.status === 'locked').reduce((s, e) => s + e.duration, 0) / 60,
  })).sort((a, b) => b.totalHours - a.totalHours);

  const maxStaffHours = Math.max(...staffData.map(x => x.totalHours), 1);

  return (
    <>
      <div className="pd-kpi-strip">
        <KpiCard value={`${totalHours.toFixed(0)}h`} label="Total Hours" color="blue" />
        <KpiCard value={`${billableHours.toFixed(0)}h`} label="Billable Hours" color="green" />
        <KpiCard value={`$${Math.round(billingValue).toLocaleString()}`} label="Billing Value" color="teal" />
        <KpiCard value={`${nonBillableHours.toFixed(0)}h`} label="Non-billable" color="amber" />
        <KpiCard value={activeStaff} label="Active Staff" color="purple" />
        <KpiCard value={`$${avgRate.toFixed(0)}/h`} label="Avg Rate" color="blue" />
      </div>

      {/* Customer Billing */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Customer Billing</h3>
        <div className="pd-ts-table-wrap">
          <table className="pd-ts-table">
            <thead>
              <tr>
                <th>Customer</th>
                {months.map((m, i) => <th key={i}>{m.label}</th>)}
                <th>Projection</th>
              </tr>
            </thead>
            <tbody>
              {customerBilling.map(c => {
                const cur = c.months[3];
                return (
                  <tr key={c.custId}>
                    <td className="pd-ts-name-cell">{c.name}</td>
                    {c.months.map((m, i) => (
                      <td key={i} className="pd-ts-num-cell">
                        <div className="pd-ts-hours">{m.hours.toFixed(1)}h</div>
                        <div className="pd-ts-value">${Math.round(m.value).toLocaleString()}</div>
                      </td>
                    ))}
                    <td className="pd-ts-num-cell pd-ts-projection">
                      <div className="pd-ts-hours">{(cur.hours * projMul).toFixed(1)}h</div>
                      <div className="pd-ts-value">${Math.round(cur.value * projMul).toLocaleString()}</div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Department Matrix */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Department Breakdown</h3>
        <div className="pd-ts-table-wrap">
          <table className="pd-ts-table">
            <thead>
              <tr><th>Department</th><th>Total Hours</th><th>Billable</th><th>Value</th><th>Staff</th><th>Avg Rate</th></tr>
            </thead>
            <tbody>
              {Array.from(deptData.entries()).map(([dept, d]) => (
                <tr key={dept}>
                  <td className="pd-ts-name-cell">{dept.charAt(0).toUpperCase() + dept.slice(1)}</td>
                  <td className="pd-ts-num-cell">{d.hours.toFixed(1)}h</td>
                  <td className="pd-ts-num-cell">{d.billableHours.toFixed(1)}h</td>
                  <td className="pd-ts-num-cell">${Math.round(d.value).toLocaleString()}</td>
                  <td className="pd-ts-num-cell">{d.staff.size}</td>
                  <td className="pd-ts-num-cell">${d.billableHours > 0 ? (d.value / d.billableHours).toFixed(0) : '0'}/h</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Staff Breakdown */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Staff Breakdown</h3>
        <div className="pd-ts-staff-grid">
          {staffData.map(s => {
            const barW = (s.totalHours / maxStaffHours) * 100;
            const draftPct = s.totalHours > 0 ? (s.draftHours / s.totalHours) * 100 : 0;
            const submittedPct = s.totalHours > 0 ? (s.submittedHours / s.totalHours) * 100 : 0;
            const lockedPct = s.totalHours > 0 ? (s.lockedHours / s.totalHours) * 100 : 0;
            return (
              <div key={s.empId} className="pd-ts-staff-row pd-clickable" onClick={() => onStaffClick(s.empId)}>
                <div className="pd-ts-staff-info">
                  <div className="pd-ts-staff-name">{s.name}</div>
                  <div className="pd-ts-staff-meta">
                    {s.totalHours.toFixed(1)}h total &middot; {s.billableHours.toFixed(1)}h billable &middot; ${Math.round(s.value).toLocaleString()}
                  </div>
                </div>
                <div className="pd-ts-bar-container">
                  <div className="pd-ts-bar" style={{ width: `${barW}%` }}>
                    <div className="pd-ts-bar-seg bar-locked" style={{ width: `${lockedPct}%` }} title={`Locked: ${s.lockedHours.toFixed(1)}h`} />
                    <div className="pd-ts-bar-seg bar-submitted" style={{ width: `${submittedPct}%` }} title={`Submitted: ${s.submittedHours.toFixed(1)}h`} />
                    <div className="pd-ts-bar-seg bar-draft" style={{ width: `${draftPct}%` }} title={`Draft: ${s.draftHours.toFixed(1)}h`} />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
        <div className="pd-ts-legend">
          <span className="pd-ts-legend-item"><span className="pd-ts-legend-dot bar-locked" /> Locked</span>
          <span className="pd-ts-legend-item"><span className="pd-ts-legend-dot bar-submitted" /> Submitted</span>
          <span className="pd-ts-legend-item"><span className="pd-ts-legend-dot bar-draft" /> Draft</span>
        </div>
      </div>
    </>
  );
};

/* ============================================================
   STAFF TIMESHEET DRILL-DOWN
   ============================================================ */
const StaffTimesheetView: React.FC<{
  employeeId: number;
  timeEntries: TimeEntryItem[];
  employees: EmployeeItem[];
  tickets: TicketItem[];
  customers: Map<number, string>;
  onBack: () => void;
}> = ({ employeeId, timeEntries, employees, tickets, customers, onBack }) => {
  const emp = employees.find(e => e.id === employeeId);
  const empName = emp ? `${emp.firstName} ${emp.lastName}` : `Employee #${employeeId}`;
  const myEntries = timeEntries.filter(e => e.employeeId === employeeId && e.duration > 0);

  const getRate = (e: TimeEntryItem) => e.malleableData?.billing_rate || 0;
  const totalHours = myEntries.reduce((s, e) => s + e.duration, 0) / 60;
  const billableHours = myEntries.filter(e => e.billable).reduce((s, e) => s + e.duration, 0) / 60;
  const billingValue = myEntries.filter(e => e.billable).reduce((s, e) => s + (e.duration / 60) * getRate(e), 0);
  const billablePct = totalHours > 0 ? Math.round((billableHours / totalHours) * 100) : 0;

  // Weekly summary — last 4 weeks
  const now = new Date();
  const weeks = Array.from({ length: 4 }, (_, i) => {
    const off = 3 - i;
    const ws = new Date(now); ws.setDate(now.getDate() - now.getDay() - off * 7); ws.setHours(0, 0, 0, 0);
    const we = new Date(ws); we.setDate(ws.getDate() + 6); we.setHours(23, 59, 59);
    return {
      start: ws, end: we,
      label: `${ws.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} \u2013 ${we.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`,
    };
  });

  const ticketCustMap = new Map<number, number>();
  tickets.forEach(t => ticketCustMap.set(t.id, t.customerId));

  const sorted = [...myEntries].sort((a, b) => new Date(b.start).getTime() - new Date(a.start).getTime());

  return (
    <div className="pd-ticket-detail">
      <button className="pd-ticket-back" onClick={onBack}>\u2190 Back to Timesheets</button>
      <div className="pd-ticket-header"><h2 className="pd-ticket-title">{empName}\u2019s Timesheet</h2></div>

      <div className="pd-kpi-strip">
        <KpiCard value={`${totalHours.toFixed(1)}h`} label="Total Hours" color="blue" />
        <KpiCard value={`${billableHours.toFixed(1)}h`} label="Billable" color="green" />
        <KpiCard value={`$${Math.round(billingValue).toLocaleString()}`} label="Value" color="teal" />
        <KpiCard value={`${billablePct}%`} label="Billable %" color={billablePct >= 70 ? 'green' : billablePct >= 50 ? 'amber' : 'red'} />
      </div>

      {/* Weekly grid */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Weekly Summary</h3>
        <div className="pd-ts-table-wrap">
          <table className="pd-ts-table">
            <thead><tr><th>Week</th><th>Hours</th><th>Billable</th><th>Value</th><th>Status</th></tr></thead>
            <tbody>
              {weeks.map((w, i) => {
                const we = myEntries.filter(e => { const d = new Date(e.start); return d >= w.start && d <= w.end; });
                const hrs = we.reduce((s, e) => s + e.duration, 0) / 60;
                const bill = we.filter(e => e.billable).reduce((s, e) => s + e.duration, 0) / 60;
                const val = we.filter(e => e.billable).reduce((s, e) => s + (e.duration / 60) * getRate(e), 0);
                const sc: Record<string, number> = { draft: 0, submitted: 0, locked: 0 };
                we.forEach(e => { if (e.status in sc) sc[e.status]++; });
                return (
                  <tr key={i}>
                    <td>{w.label}</td>
                    <td className="pd-ts-num-cell">{hrs.toFixed(1)}h</td>
                    <td className="pd-ts-num-cell">{bill.toFixed(1)}h</td>
                    <td className="pd-ts-num-cell">${Math.round(val).toLocaleString()}</td>
                    <td className="pd-ts-week-status">
                      {sc.locked > 0 && <span className="pd-ts-status-pip bar-locked">{sc.locked}</span>}
                      {sc.submitted > 0 && <span className="pd-ts-status-pip bar-submitted">{sc.submitted}</span>}
                      {sc.draft > 0 && <span className="pd-ts-status-pip bar-draft">{sc.draft}</span>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Customer billing breakdown */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Customer Billing</h3>
        {(() => {
          const custAgg = new Map<number, { hours: number; billable: number; value: number; entries: number }>();
          myEntries.forEach(e => {
            const cid = ticketCustMap.get(e.ticketId) || 0;
            const cur = custAgg.get(cid) || { hours: 0, billable: 0, value: 0, entries: 0 };
            cur.hours += e.duration;
            cur.entries++;
            if (e.billable) { cur.billable += e.duration; cur.value += (e.duration / 60) * getRate(e); }
            custAgg.set(cid, cur);
          });
          const custRows = [...custAgg.entries()]
            .map(([cid, d]) => ({ name: customers.get(cid) || 'Unassigned', ...d }))
            .sort((a, b) => b.value - a.value);
          return (
            <div className="pd-ts-table-wrap">
              <table className="pd-ts-table">
                <thead><tr><th>Customer</th><th>Entries</th><th>Hours</th><th>Billable</th><th>Value</th><th>Util %</th></tr></thead>
                <tbody>
                  {custRows.map((r, i) => (
                    <tr key={i}>
                      <td>{r.name}</td>
                      <td className="pd-ts-num-cell">{r.entries}</td>
                      <td className="pd-ts-num-cell">{(r.hours / 60).toFixed(1)}h</td>
                      <td className="pd-ts-num-cell">{(r.billable / 60).toFixed(1)}h</td>
                      <td className="pd-ts-num-cell">${Math.round(r.value).toLocaleString()}</td>
                      <td className="pd-ts-num-cell">{r.hours > 0 ? Math.round((r.billable / r.hours) * 100) : 0}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          );
        })()}
      </div>

      {/* Entry list */}
      <div className="pd-attention-panel">
        <h3 className="pd-section-title">Time Entries <span className="pd-badge">{sorted.length}</span></h3>
        <div className="pd-worklog-list">
          {sorted.map(entry => {
            const cid = ticketCustMap.get(entry.ticketId) || 0;
            const cn = customers.get(cid) || '';
            return (
              <div key={entry.id} className="pd-worklog-entry">
                <div className="pd-worklog-body">
                  <div className="pd-worklog-header">
                    <span className="pd-worklog-author">{cn}</span>
                    <div className="pd-worklog-tags">
                      <span className={`pd-worklog-status status-${entry.status}`}>{entry.status}</span>
                      {entry.billable && <span className="pd-worklog-billable">billable</span>}
                    </div>
                  </div>
                  <div className="pd-worklog-desc">{entry.description}</div>
                  <div className="pd-worklog-meta">
                    <span className="pd-worklog-duration">{formatMinutes(entry.duration)}</span>
                    <span className="pd-worklog-date">
                      {new Date(entry.start).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}
                      {' \u2022 '}
                      {new Date(entry.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      {' \u2013 '}
                      {new Date(entry.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

/* ============================================================
   MAIN DASHBOARDS COMPONENT
   ============================================================ */
const Dashboards: React.FC = () => {
  const [persona, setPersona] = useState<Persona>('manager');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null);

  // Server-side dashboard composition (additive — gracefully degrades)
  const [serverSummary, setServerSummary] = useState<ServerSummary | null>(null);
  const [selectedTeamId, setSelectedTeamId] = useState<number | null>(null);

  const loadServerSummary = useCallback(async (teamId?: number | null) => {
    try {
      const query = teamId ? `?team_id=${encodeURIComponent(String(teamId))}` : '';
      const data = await api(`dashboards/me/summary${query}`);
      // Validate response shape — WP may return an error object if the route is unregistered
      if (!data || !Array.isArray(data.allowed_personas) || !Array.isArray(data.scopes)) return;
      setServerSummary(data as ServerSummary);
      setSelectedTeamId(data.active_scope?.scope_id ?? null);
      // If current persona is not allowed, switch to first allowed
      if (data.allowed_personas.length > 0 && !data.allowed_personas.includes(persona)) {
        setPersona(data.allowed_personas[0]);
      }
    } catch (_) {
      // Server composition unavailable — degrade gracefully, all tabs remain visible
    }
  }, [persona]);

  // Data stores
  const [overview, setOverview] = useState<DashboardOverview | null>(null);
  const [salesData, setSalesData] = useState<SalesData | null>(null);
  const [demoWow, setDemoWow] = useState<DemoWow | undefined>(undefined);
  const [tickets, setTickets] = useState<TicketItem[]>([]);
  const [workItems, setWorkItems] = useState<WorkItem[]>([]);
  const [projects, setProjects] = useState<ProjectItem[]>([]);
  const [activity, setActivity] = useState<ActivityItem[]>([]);
  const [customers, setCustomers] = useState<Map<number, string>>(new Map());
  const [leads, setLeads] = useState<LeadItem[]>([]);
  const [quotes, setQuotes] = useState<QuoteItem[]>([]);
  const [allTimeEntries, setAllTimeEntries] = useState<TimeEntryItem[]>([]);
  const [allEmployees, setAllEmployees] = useState<EmployeeItem[]>([]);

  const currentUserId = window.petSettings?.currentUserId || 0;

  const loadAllData = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const [dashRes, ticketsRes, workRes, projRes, actRes, custRes, leadsRes, quotesRes, timeRes, empRes] = await Promise.all([
        api('dashboard'),
        api('tickets').catch(() => []),
        api('work-items').catch(() => []),
        api('projects').catch(() => []),
        api('activity?limit=50&range=7d').catch(() => ({ items: [] })),
        api('customers').catch(() => []),
        api('leads').catch(() => []),
        api('quotes').catch(() => []),
        api('time-entries').catch(() => []),
        api('employees').catch(() => []),
      ]);

      setOverview(dashRes.overview);
      setSalesData(dashRes.sales || null);
      setDemoWow(dashRes.demoWow);
      setTickets(Array.isArray(ticketsRes) ? ticketsRes : []);
      setWorkItems(Array.isArray(workRes) ? workRes : []);
      const normalizedProjects = (Array.isArray(projRes) ? projRes : []).map((project: any) => ({
        ...project,
        tasks: Array.isArray(project?.tasks) ? project.tasks : [],
      }));
      setProjects(normalizedProjects);
      setActivity(Array.isArray(actRes) ? actRes : (actRes?.items || []));
      setCustomers(new Map((Array.isArray(custRes) ? custRes : []).map((c: CustomerItem) => [c.id, c.name])));
      setLeads(Array.isArray(leadsRes) ? leadsRes : []);
      setQuotes(Array.isArray(quotesRes) ? quotesRes : []);
      setAllTimeEntries(Array.isArray(timeRes) ? timeRes : []);
      setAllEmployees(Array.isArray(empRes) ? empRes : []);
      setLastUpdated(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAllData();
    loadServerSummary(null);
    // Auto-refresh every 60 seconds
    const interval = setInterval(() => { loadAllData(); loadServerSummary(selectedTeamId); }, 60000);
    return () => clearInterval(interval);
  }, [loadAllData]);

  const ALL_TABS: { key: Persona; label: string; icon: string; subtitle: string }[] = [
    { key: 'manager', label: 'Manager', icon: '\uD83C\uDFAF', subtitle: 'Am I in control?' },
    { key: 'sales', label: 'Sales', icon: '\uD83D\uDCB0', subtitle: 'What do I need to focus on today?' },
    { key: 'support', label: 'Support', icon: '\uD83C\uDFA7', subtitle: 'What should I do next?' },
    { key: 'pm', label: 'Project Manager', icon: '\uD83D\uDCCA', subtitle: 'Are we on track?' },
    { key: 'timesheets', label: 'Timesheets', icon: '\u23F1', subtitle: 'Where is the time going?' },
  ];

  // Gate tabs by server-side persona allowlist (fall back to all if unavailable)
  const TABS = useMemo(() => {
    const ap = serverSummary?.allowed_personas;
    if (!ap || !Array.isArray(ap) || ap.length === 0) return ALL_TABS;
    return ALL_TABS.filter(t => ap.includes(t.key));
  }, [serverSummary]);

  const activeTab = TABS.find(t => t.key === persona) ?? TABS[0];

  // Extract server panels for the active persona
  const activeServerPanels = serverSummary?.personas?.[persona]?.panels;

  if (loading && !overview) {
    return (
      <div className="pet-dashboards-fullscreen">
        <div className="pd-loading">
          <div className="pd-spinner" />
          Loading dashboards...
        </div>
      </div>
    );
  }

  if (error && !overview) {
    return (
      <div className="pet-dashboards-fullscreen">
        <div className="pd-content">
          <div className="pd-error">
            <strong>Error:</strong> {error}
            <br />
            <button className="pd-refresh-btn" style={{ marginTop: 12 }} onClick={loadAllData}>Retry</button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="pet-dashboards-fullscreen">
      {/* Header */}
      <div className="pd-header">
        <div>
          <h1>PET Operations</h1>
          <div className="pd-header-subtitle">{activeTab.subtitle}</div>
        </div>
        <div className="pd-header-right">
          {serverSummary && serverSummary.scopes.length > 1 && (
            <div style={{ display: 'flex', gap: 4, marginRight: 10 }}>
              {serverSummary.scopes.map(s => (
                <button
                  key={`${s.scope_type}:${s.scope_id}`}
                  type="button"
                  className="pd-refresh-btn"
                  style={{
                    background: selectedTeamId === s.scope_id ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.08)',
                    fontWeight: selectedTeamId === s.scope_id ? 700 : 400,
                    borderColor: selectedTeamId === s.scope_id ? 'rgba(255,255,255,0.4)' : 'rgba(255,255,255,0.15)',
                    fontSize: '0.78rem',
                    padding: '4px 12px',
                  }}
                  onClick={() => loadServerSummary(s.scope_id)}
                >
                  {s.label}
                </button>
              ))}
            </div>
          )}
          {serverSummary?.active_scope && (
            <span className="pd-last-updated" style={{ marginRight: 8, fontSize: 11, opacity: 0.7 }}>
              {serverSummary.active_scope.visibility_scope}
            </span>
          )}
          {lastUpdated && (
            <span className="pd-last-updated">
              Updated {lastUpdated.toLocaleTimeString()}
            </span>
          )}
          <button className="pd-refresh-btn" onClick={() => { loadAllData(); loadServerSummary(selectedTeamId); }} disabled={loading}>
            {loading ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>
      </div>

      {/* Persona tabs */}
      <div className="pd-tabs">
        {TABS.map(tab => (
          <button
            key={tab.key}
            className={`pd-tab ${persona === tab.key ? 'active' : ''}`}
            onClick={() => { setPersona(tab.key as Persona); setSelectedTicketId(null); setSelectedStaffId(null); }}
          >
            <span className="pd-tab-icon">{tab.icon}</span>
            {tab.label}
          </button>
        ))}
      </div>

      {/* Content */}
      <div className="pd-content">
        {persona === 'manager' && overview && (
          <ManagerView
            overview={overview}
            demoWow={demoWow}
            tickets={tickets}
            workItems={workItems}
            activity={activity}
            customers={customers}
            serverPanels={activeServerPanels}
          />
        )}

        {persona === 'support' && !selectedTicketId && (
          <SupportView
            tickets={tickets}
            workItems={workItems}
            activity={activity}
            customers={customers}
            currentUserId={currentUserId}
            onTicketClick={(id) => setSelectedTicketId(id)}
            serverPanels={activeServerPanels}
          />
        )}

        {persona === 'support' && selectedTicketId && (() => {
          const t = tickets.find(tk => tk.id === selectedTicketId);
          return t ? (
            <TicketDetailPanel
              ticket={t}
              workItems={workItems}
              customers={customers}
              activity={activity}
              onBack={() => setSelectedTicketId(null)}
            />
          ) : (
            <div className="pd-empty">
              Ticket not found.
              <br />
              <button className="pd-ticket-back" onClick={() => setSelectedTicketId(null)}>\u2190 Back</button>
            </div>
          );
        })()}

        {persona === 'sales' && (
          <SalesView
            salesData={salesData}
            leads={leads}
            quotes={quotes}
            activity={activity}
            customers={customers}
          />
        )}

        {persona === 'pm' && (
          <PMView
            projects={projects}
            tickets={tickets}
            workItems={workItems}
            activity={activity}
            customers={customers}
          />
        )}

        {persona === 'timesheets' && !selectedStaffId && (
          <TimesheetView
            timeEntries={allTimeEntries}
            employees={allEmployees}
            tickets={tickets}
            customers={customers}
            onStaffClick={(id) => setSelectedStaffId(id)}
          />
        )}

        {persona === 'timesheets' && selectedStaffId && (
          <StaffTimesheetView
            employeeId={selectedStaffId}
            timeEntries={allTimeEntries}
            employees={allEmployees}
            tickets={tickets}
            customers={customers}
            onBack={() => setSelectedStaffId(null)}
          />
        )}
      </div>
    </div>
  );
};

export default Dashboards;
