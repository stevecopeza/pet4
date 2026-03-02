import React, { useEffect, useState, useCallback } from 'react';
import '../dashboard-styles.css';

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
  siteId?: number;
  slaId?: number;
  contactId?: number | null;
  openedAt?: string | null;
  resolvedAt?: string | null;
  closedAt?: string | null;
  intake_source?: string | null;
  queueId?: string | null;
  ownerUserId?: string | null;
  lifecycleOwner?: string;
  isBillableDefault?: boolean;
  billingContextType?: string;
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

type Persona = 'manager' | 'support' | 'pm' | 'sales';

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
  onClick?: () => void;
}> = ({ subject, meta, severity, timer, timerClass, statusLabel, pulse, onClick }) => (
  <div
    className={`pd-attention-card severity-${severity} ${pulse ? 'pd-pulse' : ''} ${onClick ? 'pd-clickable' : ''}`}
    onClick={onClick}
  >
    <div className="pd-attention-body">
      <div className="pd-attention-subject">{subject}</div>
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
}> = ({ overview, demoWow, tickets, workItems, activity, customers }) => {
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
  const attentionItems: { subject: string; meta: string; severity: string; timer?: string; timerClass?: string; statusLabel?: string; pulse?: boolean; sort: number }[] = [];

  workItems
    .filter(wi => wi.source_type === 'ticket')
    .forEach(wi => {
      const ticket = ticketMap.get(wi.source_id);
      if (!ticket || ['closed', 'resolved'].includes(ticket.status)) return;
      const custName = customers.get(ticket.customerId) || `Customer #${ticket.customerId}`;

      if (wi.sla_time_remaining !== null && wi.sla_time_remaining < 0) {
        attentionItems.push({
          subject: ticket.subject,
          meta: `${custName} · Breached ${formatMinutes(wi.sla_time_remaining)} ago`,
          severity: 'breached',
          timer: formatMinutes(wi.sla_time_remaining),
          timerClass: 'red',
          statusLabel: 'BREACHED',
          pulse: true,
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
          sort: 1,
        });
      }

      if (!wi.assigned_user_id) {
        attentionItems.push({
          subject: ticket.subject,
          meta: `${custName} · No one assigned`,
          severity: 'unassigned',
          statusLabel: 'UNASSIGNED',
          sort: 2,
        });
      }
    });

  attentionItems.sort((a, b) => a.sort - b.sort);

  // Strategic activity (API returns UPPERCASE event types)
  const strategicTypes = ['QUOTE_ACCEPTED', 'CONTRACT_CREATED', 'PROJECT_CREATED', 'MILESTONE_COMPLETED', 'ESCALATION_TRIGGERED', 'QUOTE_SENT', 'SLA_BREACH_RECORDED'];
  const strategicActivity = activity.filter(a => strategicTypes.includes(a.event_type) || a.severity === 'breach' || a.severity === 'commercial');

  return (
    <>
      <div className="pd-kpi-strip">
        <KpiCard value={`$${overview.revenueThisMonth.toLocaleString()}`} label="Revenue MTD" color="green" />
        <KpiCard value={overview.activeProjects} label="Active Projects" color="blue" />
        <KpiCard value={`${slaHealth}%`} label="SLA Health" color={slaHealth >= 80 ? 'green' : slaHealth >= 50 ? 'amber' : 'red'} />
        <KpiCard value={`${overview.utilizationRate}%`} label="Utilisation" color="purple" />
        <KpiCard value={openTickets.length} label="Open Tickets" color="teal" />
        <KpiCard value={overview.pendingQuotes} label="Pending Quotes" color="amber" />
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
              <AttentionCard key={i} {...item} />
            ))}
          </div>
        )}
      </div>

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
}> = ({ tickets, workItems, activity, customers, currentUserId, onTicketClick }) => {
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

      return {
        ticketId: ticket.id,
        subject: ticket.subject,
        meta: `${custName} · ${ticket.priority} priority`,
        severity: breached ? 'breached' as const : warning ? 'warning' as const : 'info' as const,
        timer: formatMinutes(wi.sla_time_remaining),
        timerClass: timerColor(wi.sla_time_remaining),
        statusLabel: breached ? 'BREACHED' : warning ? 'DUE SOON' : 'ON TRACK',
        pulse: breached,
        sort: wi.sla_time_remaining ?? 9999,
      };
    })
    .sort((a, b) => a.sort - b.sort);

  // Unassigned queue items for display
  const unassignedAttention = unassignedTickets.map(wi => {
    const ticket = ticketMap.get(wi.source_id)!;
    const custName = customers.get(ticket.customerId) || `Customer #${ticket.customerId}`;
    return {
      ticketId: ticket.id,
      subject: ticket.subject,
      meta: `${custName} · Unassigned`,
      severity: 'unassigned' as const,
      timer: formatMinutes(wi.sla_time_remaining),
      timerClass: timerColor(wi.sla_time_remaining),
      statusLabel: 'UNASSIGNED',
      pulse: false,
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
              <AttentionCard key={i} {...item} onClick={() => onTicketClick(item.ticketId)} />
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
              <AttentionCard key={i} {...item} onClick={() => onTicketClick(item.ticketId)} />
            ))}
          </div>
        </div>
      )}

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

const stateColor = (state: string): string => {
  switch (state) {
    case 'active': return 'blue';
    case 'planned': return 'purple';
    case 'on_hold': return 'amber';
    case 'completed': return 'green';
    default: return 'teal';
  }
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
  const attentionItems: { subject: string; meta: string; severity: string; timer?: string; timerClass?: string; statusLabel?: string; pulse?: boolean; sort: number }[] = [];

  // Aging sent quotes (>3 days)
  quotes.filter(q => q.state === 'sent').forEach(q => {
    const sentAge = q.updatedAt ? Math.floor((now - new Date(q.updatedAt).getTime()) / 86400000) : 0;
    const custName = customers.get(q.customerId) || `Customer #${q.customerId}`;
    if (sentAge > 3) {
      attentionItems.push({
        subject: q.title,
        meta: `${custName} · Sent ${sentAge}d ago · $${q.totalValue.toLocaleString()}`,
        severity: sentAge > 7 ? 'breached' : 'warning',
        timer: `${sentAge}d`,
        timerClass: sentAge > 7 ? 'red' : 'amber',
        statusLabel: 'FOLLOW UP',
        pulse: sentAge > 7,
        sort: sentAge > 7 ? 0 : 1,
      });
    }
  });

  // Stale leads (>7 days with no update)
  leads.filter(l => l.status === 'new' || l.status === 'qualified').forEach(l => {
    const age = Math.floor((now - new Date(l.updatedAt || l.createdAt).getTime()) / 86400000);
    const custName = customers.get(l.customerId) || `Customer #${l.customerId}`;
    if (age > 7) {
      attentionItems.push({
        subject: l.subject,
        meta: `${custName} · ${l.status} · ${age}d stale`,
        severity: 'warning',
        timer: `${age}d`,
        timerClass: 'amber',
        statusLabel: 'STALE LEAD',
        sort: 2,
      });
    }
  });

  // Draft quotes ready to send
  quotes.filter(q => q.state === 'draft' && q.totalValue > 0).forEach(q => {
    const custName = customers.get(q.customerId) || `Customer #${q.customerId}`;
    attentionItems.push({
      subject: q.title,
      meta: `${custName} · $${q.totalValue.toLocaleString()} · Ready to send`,
      severity: 'info',
      statusLabel: 'SEND QUOTE',
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
  workItems: WorkItem[];
  activity: ActivityItem[];
  customers: Map<number, string>;
}> = ({ projects, workItems, activity, customers }) => {
  const now = new Date();
  const activeProjects = projects.filter(p => p.state === 'active' || p.state === 'planned');
  const totalSold = activeProjects.reduce((sum, p) => sum + (p.soldHours || 0), 0);
  const totalHoursUsed = activeProjects.reduce((sum, p) => sum + (p.malleableData?.hours_used ?? 0), 0);
  const totalTasks = activeProjects.reduce((sum, p) => sum + p.tasks.length, 0);
  const totalCompleted = activeProjects.reduce((sum, p) => sum + p.tasks.filter(t => t.completed).length, 0);
  const overdueProjects = activeProjects.filter(p => p.endDate && new Date(p.endDate) < now);

  // Projects at risk: overdue, over-budget, or burn-ahead
  const attentionItems = activeProjects
    .map(p => {
      const taskCount = p.tasks.length;
      const completedCount = p.tasks.filter(t => t.completed).length;
      const progress = taskCount > 0 ? pct(completedCount, taskCount) : 0;
      const hoursUsed = p.malleableData?.hours_used ?? 0;
      const soldH = p.soldHours || 0;
      const burnPct = soldH > 0 ? Math.round((hoursUsed / soldH) * 100) : 0;
      const isOverBudget = soldH > 0 && hoursUsed > soldH;
      const isOverdue = p.endDate ? new Date(p.endDate) < now : false;
      const isBurnAhead = burnPct > 80 && progress < 80 && !isOverBudget;
      const custName = customers.get(p.customerId) || '';

      if (!isOverBudget && !isOverdue && !isBurnAhead) return null;

      const reasons: string[] = [];
      if (isOverdue) reasons.push('OVERDUE');
      if (isOverBudget) reasons.push('OVER BUDGET');
      if (isBurnAhead && !isOverBudget) reasons.push('AT RISK');

      return {
        subject: p.name,
        meta: `${custName ? custName + ' · ' : ''}${completedCount}/${taskCount} tasks · ${hoursUsed}h / ${soldH}h`,
        severity: isOverBudget || isOverdue ? 'breached' as const : 'warning' as const,
        timer: isOverdue ? `${Math.abs(daysUntil(p.endDate)!)}d overdue` : `${burnPct}% burn`,
        timerClass: isOverBudget || isOverdue ? 'red' : 'amber',
        statusLabel: reasons.join(' · '),
        pulse: isOverBudget || isOverdue,
        sort: isOverBudget ? 0 : isOverdue ? 1 : 2,
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
            const taskCount = p.tasks.length;
            const completedCount = p.tasks.filter(t => t.completed).length;
            const progress = taskCount > 0 ? pct(completedCount, taskCount) : 0;
            const hoursUsed = p.malleableData?.hours_used ?? 0;
            const soldH = p.soldHours || 0;
            const burnPct = soldH > 0 ? Math.round((hoursUsed / soldH) * 100) : 0;
            const days = daysUntil(p.endDate);
            const isOverdue = days !== null && days < 0;
            const custName = customers.get(p.customerId) || `Customer #${p.customerId}`;
            const pm = p.malleableData?.pm || '--';

            return (
              <div key={p.id} className={`pd-project-card ${isOverdue ? 'pd-project-overdue' : ''}`}>
                <div className="pd-project-card-header">
                  <div className="pd-project-card-title">{p.name}</div>
                  <span className={`pd-project-state-badge state-${p.state}`}>{p.state.replace('_', ' ')}</span>
                </div>
                <div className="pd-project-customer">{custName}</div>

                {/* Progress bar */}
                <div className="pd-project-progress-row">
                  <div className="pd-project-progress-bar-bg">
                    <div
                      className={`pd-project-progress-bar-fill ${progress >= 80 ? 'fill-green' : progress >= 40 ? 'fill-blue' : 'fill-teal'}`}
                      style={{ width: `${Math.min(progress, 100)}%` }}
                    />
                  </div>
                  <span className="pd-project-progress-label">{progress}%</span>
                </div>

                {/* Meta grid */}
                <div className="pd-project-meta-grid">
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">Tasks</span>
                    <span className="pd-project-meta-value">{completedCount}/{taskCount}</span>
                  </div>
                  <div className="pd-project-meta-item">
                    <span className="pd-project-meta-label">Hours</span>
                    <span className={`pd-project-meta-value ${burnPct > 100 ? 'pd-over-budget' : ''}`}>{hoursUsed}/{soldH}h</span>
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
  createdAt: string | null;
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

  const handleCorrectEntry = async (entry: TimeEntryItem) => {
    const reason = prompt('Correction description (why is this being corrected?)');
    if (!reason) return;

    try {
      await apiPost(`time-entries/${entry.id}/correct`, {
        description: `CORRECTION: ${reason}`,
        start: entry.start,
        end: entry.end,
        isBillable: entry.billable,
      });
      // Refresh time entries
      const freshEntries = await api(`time-entries?ticket_id=${ticket.id}`).catch(() => []);
      setDetail(prev => ({ ...prev, timeEntries: Array.isArray(freshEntries) ? freshEntries : [] }));
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to create correction');
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
                              {(entry.status === 'submitted' || entry.status === 'locked') && !entry.isCorrection && (
                                <button className="pd-worklog-edit-btn" onClick={() => handleCorrectEntry(entry)} title="Create correction">
                                  \u21BA
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
              {ticket.ticketMode && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Mode</span>
                  <span className="pd-ticket-field-value">{ticket.ticketMode}</span>
                </div>
              )}
              {ticket.intake_source && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Source</span>
                  <span className="pd-ticket-field-value">{ticket.intake_source}</span>
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
              {ticket.lifecycleOwner && ticket.lifecycleOwner !== 'support' && (
                <div className="pd-ticket-field">
                  <span className="pd-ticket-field-label">Lifecycle</span>
                  <span className="pd-ticket-field-value">{ticket.lifecycleOwner}</span>
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
   MAIN DASHBOARDS COMPONENT
   ============================================================ */
const Dashboards: React.FC = () => {
  const [persona, setPersona] = useState<Persona>('manager');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);

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

  const currentUserId = window.petSettings?.currentUserId || 0;

  const loadAllData = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const [dashRes, ticketsRes, workRes, projRes, actRes, custRes, leadsRes, quotesRes] = await Promise.all([
        api('dashboard'),
        api('tickets').catch(() => []),
        api('work-items').catch(() => []),
        api('projects').catch(() => []),
        api('activity?limit=50&range=7d').catch(() => ({ items: [] })),
        api('customers').catch(() => []),
        api('leads').catch(() => []),
        api('quotes').catch(() => []),
      ]);

      setOverview(dashRes.overview);
      setSalesData(dashRes.sales || null);
      setDemoWow(dashRes.demoWow);
      setTickets(Array.isArray(ticketsRes) ? ticketsRes : []);
      setWorkItems(Array.isArray(workRes) ? workRes : []);
      setProjects(Array.isArray(projRes) ? projRes : []);
      setActivity(Array.isArray(actRes) ? actRes : (actRes?.items || []));
      setCustomers(new Map((Array.isArray(custRes) ? custRes : []).map((c: CustomerItem) => [c.id, c.name])));
      setLeads(Array.isArray(leadsRes) ? leadsRes : []);
      setQuotes(Array.isArray(quotesRes) ? quotesRes : []);
      setLastUpdated(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAllData();
    // Auto-refresh every 60 seconds
    const interval = setInterval(loadAllData, 60000);
    return () => clearInterval(interval);
  }, [loadAllData]);

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

  const TABS: { key: Persona; label: string; icon: string; subtitle: string }[] = [
    { key: 'manager', label: 'Manager', icon: '\uD83C\uDFAF', subtitle: 'Am I in control?' },
    { key: 'sales', label: 'Sales', icon: '\uD83D\uDCB0', subtitle: 'What do I need to focus on today?' },
    { key: 'support', label: 'Support', icon: '\uD83C\uDFA7', subtitle: 'What should I do next?' },
    { key: 'pm', label: 'Project Manager', icon: '\uD83D\uDCCA', subtitle: 'Are we on track?' },
  ];

  const activeTab = TABS.find(t => t.key === persona)!;

  return (
    <div className="pet-dashboards-fullscreen">
      {/* Header */}
      <div className="pd-header">
        <div>
          <h1>PET Operations</h1>
          <div className="pd-header-subtitle">{activeTab.subtitle}</div>
        </div>
        <div className="pd-header-right">
          {lastUpdated && (
            <span className="pd-last-updated">
              Updated {lastUpdated.toLocaleTimeString()}
            </span>
          )}
          <button className="pd-refresh-btn" onClick={loadAllData} disabled={loading}>
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
            onClick={() => setPersona(tab.key)}
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
            workItems={workItems}
            activity={activity}
            customers={customers}
          />
        )}
      </div>
    </div>
  );
};

export default Dashboards;
