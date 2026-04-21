import React, { useEffect, useMemo, useState } from 'react';
import {
  Contact,
  Customer,
  DashboardData,
  DemoEnvironmentHealth,
  Employee,
  FeedEvent,
  Project,
  Quote,
  Site,
  Ticket,
  TimeEntry,
} from '../types';
import { computeProjectHealth } from '../healthCompute';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';

type QueueDescriptor = {
  queue_key: string;
  label: string;
  visibility_scope: 'SELF' | 'TEAM' | 'MANAGERIAL' | 'ADMIN';
};

type QueueItem = {
  source_type: string;
  source_id: string;
  reference_code: string;
  title: string | null;
  customer_id: number | null;
  status: string | null;
  assignment_mode: string | null;
  assigned_user_id: string | null;
  due_at: string | null;
  created_at: string;
  updated_at: string;
};

type EscalationItem = {
  id: number;
  severity: string;
  reason: string;
  summary?: string | null;
  source_entity_type: string;
  source_entity_id: number;
  opened_at?: string | null;
};

type EscalationListResponse = {
  items: EscalationItem[];
  total: number;
  page: number;
  per_page: number;
};

type AdvisorySignal = {
  id: string;
  signal_type: string;
  severity: string;
  status: string;
  title: string | null;
  summary: string | null;
  message: string | null;
  source_entity_type: string | null;
  source_entity_id: string | null;
  customer_id: number | null;
  created_at: string;
};

type CriticalAttentionItem = {
  key: string;
  title: string;
  detail: string;
  link: string;
  urgency: number;
  tags: string[];
  createdAt?: string | null;
};

type QuoteInsight = {
  quote: Quote;
  blocked: boolean;
  ageDays: number | null;
};

const CLOSED_TICKET_STATUSES = new Set(['closed', 'resolved']);
const ACTIVE_EMPLOYEE_STATUSES = new Set(['', 'active']);
const COMPLIANT_TIME_STATUSES = new Set(['approved', 'locked']);

const severityRank = (value: string): number => {
  const v = String(value || '').toLowerCase();
  if (v === 'critical' || v === 'high') return 4;
  if (v === 'medium' || v === 'warning') return 3;
  if (v === 'low' || v === 'attention') return 2;
  return 1;
};

const parseTs = (value?: string | null): number | null => {
  if (!value) return null;
  const ts = new Date(value).getTime();
  return Number.isFinite(ts) ? ts : null;
};

const timeAgo = (value?: string | null): string => {
  const ts = parseTs(value);
  if (ts === null) return 'time unknown';
  const diffMs = Date.now() - ts;
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
};

const overdueBy = (dueAt?: string | null): string => {
  const ts = parseTs(dueAt);
  if (ts === null) return 'due date unavailable';
  const diffMins = Math.floor((Date.now() - ts) / 60000);
  if (diffMins <= 0) return 'not overdue';
  if (diffMins < 60) return `${diffMins}m overdue`;
  const hours = Math.floor(diffMins / 60);
  const minutes = diffMins % 60;
  return `${hours}h ${minutes}m overdue`;
};

const getJson = async <T,>(apiUrl: string, nonce: string, path: string): Promise<T> => {
  const response = await fetch(`${apiUrl}/${path}`, {
    headers: {
      'X-WP-Nonce': nonce,
    },
  });
  if (!response.ok) {
    throw new Error(`${path} (${response.status})`);
  }
  return response.json();
};

const getQuoteTimestamp = (quote: Quote): string | null => {
  const q = quote as any;
  const md = (quote.malleableData || {}) as Record<string, any>;
  const candidates = [
    q.updatedAt,
    q.createdAt,
    q.updated_at,
    q.created_at,
    md.updatedAt,
    md.createdAt,
    md.updated_at,
    md.created_at,
    md.sentAt,
    md.sent_at,
    quote.acceptedAt,
  ];
  for (const candidate of candidates) {
    if (!candidate) continue;
    const ts = parseTs(String(candidate));
    if (ts !== null) {
      return String(candidate);
    }
  }
  return null;
};

const quoteHasPricedBlocks = (quote: Quote): boolean => {
  const blocks = (quote as any).blocks;
  if (!Array.isArray(blocks)) return quote.totalValue > 0;
  return blocks.some((block: any) => Boolean(block?.priced) && block?.type !== 'TextBlock');
};

const displayName = (employee: Employee): string => (
  employee.displayName
  || [employee.firstName, employee.lastName].filter(Boolean).join(' ').trim()
  || `Employee #${employee.id}`
);


const Dashboard = () => {
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [timeEntries, setTimeEntries] = useState<TimeEntry[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [feedEvents, setFeedEvents] = useState<FeedEvent[]>([]);
  const [advisorySignals, setAdvisorySignals] = useState<AdvisorySignal[]>([]);
  const [escalations, setEscalations] = useState<EscalationItem[]>([]);
  const [supportQueueItems, setSupportQueueItems] = useState<QueueItem[]>([]);
  const [demoHealth, setDemoHealth] = useState<DemoEnvironmentHealth | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);

  useEffect(() => {
    let cancelled = false;

    const fetchData = async () => {
      try {
        setLoading(true);
        setRefreshing(refreshNonce > 0);
        setError(null);

        const settings = window.petSettings as any;
        const apiUrl = settings?.apiUrl;
        const nonce = settings?.nonce;
        if (!apiUrl || !nonce) {
          throw new Error('PET settings are not initialized');
        }

        const [
          dashboardRes,
          ticketsRes,
          projectsRes,
          quotesRes,
          timeEntriesRes,
          employeesRes,
          customersRes,
          sitesRes,
          contactsRes,
          feedRes,
          advisoryRes,
          escalationsRes,
          queuesRes,
          demoHealthRes,
        ] = await Promise.allSettled([
          getJson<DashboardData>(apiUrl, nonce, 'dashboard'),
          getJson<Ticket[]>(apiUrl, nonce, 'tickets'),
          getJson<Project[]>(apiUrl, nonce, 'projects'),
          getJson<Quote[]>(apiUrl, nonce, 'quotes'),
          getJson<TimeEntry[]>(apiUrl, nonce, 'time-entries'),
          getJson<Employee[]>(apiUrl, nonce, 'employees'),
          getJson<Customer[]>(apiUrl, nonce, 'customers'),
          getJson<Site[]>(apiUrl, nonce, 'sites'),
          getJson<Contact[]>(apiUrl, nonce, 'contacts'),
          getJson<FeedEvent[]>(apiUrl, nonce, 'feed'),
          getJson<AdvisorySignal[]>(apiUrl, nonce, 'advisory/signals/recent?limit=50'),
          getJson<EscalationListResponse>(apiUrl, nonce, 'escalations?status=open&page=1&per_page=50'),
          getJson<QueueDescriptor[]>(apiUrl, nonce, 'work/queues'),
          getJson<DemoEnvironmentHealth>(apiUrl, nonce, 'system/demo/health'),
        ]);

        const isFulfilled = <T,>(result: PromiseSettledResult<T>): result is PromiseFulfilledResult<T> => result.status === 'fulfilled';

        if (!isFulfilled(dashboardRes)) {
          throw new Error(`Failed to load overview projection: ${String(dashboardRes.reason)}`);
        }

        const supportQueueList = isFulfilled(queuesRes)
          ? queuesRes.value.filter((queue) => queue.queue_key.startsWith('support:'))
          : [];

        const queueItemResults = supportQueueList.length > 0
          ? await Promise.allSettled(
              supportQueueList.map((queue) =>
                getJson<QueueItem[]>(
                  apiUrl,
                  nonce,
                  `work/queues/${encodeURIComponent(queue.queue_key)}/items`
                )
              )
            )
          : [];

        const queueItemByKey = new Map<string, QueueItem>();
        queueItemResults.forEach((result) => {
          if (!isFulfilled(result)) return;
          result.value.forEach((item) => {
            const key = `${item.source_type}:${item.source_id}`;
            if (!queueItemByKey.has(key)) {
              queueItemByKey.set(key, item);
            }
          });
        });

        if (cancelled) return;

        setDashboardData(dashboardRes.value);
        setTickets(isFulfilled(ticketsRes) && Array.isArray(ticketsRes.value) ? ticketsRes.value : []);
        setProjects(isFulfilled(projectsRes) && Array.isArray(projectsRes.value) ? projectsRes.value : []);
        setQuotes(isFulfilled(quotesRes) && Array.isArray(quotesRes.value) ? quotesRes.value : []);
        setTimeEntries(isFulfilled(timeEntriesRes) && Array.isArray(timeEntriesRes.value) ? timeEntriesRes.value : []);
        setEmployees(isFulfilled(employeesRes) && Array.isArray(employeesRes.value) ? employeesRes.value : []);
        setCustomers(isFulfilled(customersRes) && Array.isArray(customersRes.value) ? customersRes.value : []);
        setSites(isFulfilled(sitesRes) && Array.isArray(sitesRes.value) ? sitesRes.value : []);
        setContacts(isFulfilled(contactsRes) && Array.isArray(contactsRes.value) ? contactsRes.value : []);
        setFeedEvents(isFulfilled(feedRes) && Array.isArray(feedRes.value) ? feedRes.value : []);
        setAdvisorySignals(isFulfilled(advisoryRes) && Array.isArray(advisoryRes.value) ? advisoryRes.value : []);
        setEscalations(isFulfilled(escalationsRes) && Array.isArray(escalationsRes.value.items) ? escalationsRes.value.items : []);
        setSupportQueueItems(Array.from(queueItemByKey.values()));
        setDemoHealth(isFulfilled(demoHealthRes) ? demoHealthRes.value : null);

        const failedOptional = [
          ticketsRes,
          projectsRes,
          quotesRes,
          timeEntriesRes,
          employeesRes,
          customersRes,
          sitesRes,
          contactsRes,
          feedRes,
          advisoryRes,
          escalationsRes,
          queuesRes,
          demoHealthRes,
        ].filter((result) => result.status === 'rejected').length;

        if (failedOptional > 0) {
          setError('Some overview panels have limited data because one or more optional projections were unavailable.');
        }
      } catch (err) {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : 'Failed to load overview');
      } finally {
        if (!cancelled) {
          setLoading(false);
          setRefreshing(false);
        }
      }
    };

    fetchData();

    return () => {
      cancelled = true;
    };
  }, [refreshNonce]);

  const activeEmployees = useMemo(
    () => employees.filter((employee) => {
      if (employee.archivedAt) return false;
      return ACTIVE_EMPLOYEE_STATUSES.has(String(employee.status || '').toLowerCase());
    }),
    [employees]
  );

  const activeProjects = useMemo(
    () => projects.filter((project) => !['completed', 'archived', 'cancelled'].includes(String(project.state || '').toLowerCase())),
    [projects]
  );

  const activeTickets = useMemo(
    () => tickets.filter((ticket) => !CLOSED_TICKET_STATUSES.has(String(ticket.status || '').toLowerCase())),
    [tickets]
  );

  const projectHealthRows = useMemo(
    () => activeProjects.map((project) => {
      const health = computeProjectHealth(project);
      const hardRisk = String(project.malleableData?.health || '').toLowerCase() === 'at_risk';
      const riskState = hardRisk || health.state === 'red' || health.state === 'amber';
      const riskRank = health.state === 'red' ? 3 : health.state === 'amber' || hardRisk ? 2 : 1;
      return { project, health, riskState, riskRank };
    }),
    [activeProjects]
  );

  const atRiskProjects = useMemo(
    () => projectHealthRows
      .filter((row) => row.riskState)
      .sort((a, b) => b.riskRank - a.riskRank || a.project.id - b.project.id),
    [projectHealthRows]
  );

  const supportTicketRows = useMemo(() => (
    supportQueueItems
      .filter((item) => item.source_type === 'ticket')
      .map((item) => {
        const dueTs = parseTs(item.due_at);
        const now = Date.now();
        const breached = dueTs !== null && dueTs < now;
        const warning = dueTs !== null && dueTs >= now && dueTs <= now + (60 * 60 * 1000);
        const slaState = breached ? 'breached' : warning ? 'risk' : 'on_track';
        return { item, dueTs, breached, warning, slaState };
      })
  ), [supportQueueItems]);

  const breachedTickets = useMemo(
    () => supportTicketRows
      .filter((row) => row.breached)
      .sort((a, b) => (a.dueTs || 0) - (b.dueTs || 0)),
    [supportTicketRows]
  );

  const unassignedTickets = useMemo(
    () => supportTicketRows
      .filter((row) => !row.item.assigned_user_id)
      .sort((a, b) => parseTs(a.item.created_at) && parseTs(b.item.created_at)
        ? (parseTs(a.item.created_at) || 0) - (parseTs(b.item.created_at) || 0)
        : 0),
    [supportTicketRows]
  );

  const criticalAttentionItems = useMemo(() => {
    const items = new Map<string, CriticalAttentionItem>();
    const addOrMerge = (incoming: CriticalAttentionItem) => {
      const existing = items.get(incoming.key);
      if (!existing) {
        items.set(incoming.key, incoming);
        return;
      }
      const mergedTags = Array.from(new Set([...existing.tags, ...incoming.tags]));
      items.set(incoming.key, {
        ...existing,
        urgency: Math.max(existing.urgency, incoming.urgency),
        tags: mergedTags,
        detail: existing.detail.length >= incoming.detail.length ? existing.detail : incoming.detail,
      });
    };

    breachedTickets.forEach(({ item }) => {
      const title = item.title || `Ticket #${item.source_id}`;
      addOrMerge({
        key: `ticket:${item.source_id}`,
        title,
        detail: `${overdueBy(item.due_at)} · ${item.reference_code}`,
        link: `/wp-admin/admin.php?page=pet-support#ticket=${item.source_id}`,
        urgency: 95,
        tags: ['SLA breach'],
        createdAt: item.updated_at,
      });
    });

    unassignedTickets.forEach(({ item }) => {
      const title = item.title || `Ticket #${item.source_id}`;
      addOrMerge({
        key: `ticket:${item.source_id}`,
        title,
        detail: 'No assignee owner in queue.',
        link: `/wp-admin/admin.php?page=pet-support#ticket=${item.source_id}`,
        urgency: 80,
        tags: ['Unassigned'],
        createdAt: item.updated_at,
      });
    });

    escalations.forEach((escalation) => {
      const sev = String(escalation.severity || '').toUpperCase();
      addOrMerge({
        key: `escalation:${escalation.id}`,
        title: `Escalation #${escalation.id} · ${escalation.source_entity_type} #${escalation.source_entity_id}`,
        detail: escalation.summary || escalation.reason || 'Escalation requires acknowledgement.',
        link: '/wp-admin/admin.php?page=pet-escalations',
        urgency: 100 + severityRank(sev),
        tags: [sev || 'OPEN'],
        createdAt: escalation.opened_at,
      });
    });

    atRiskProjects.forEach(({ project, health }) => {
      const reasonText = health.reasons.length > 0
        ? health.reasons.map((reason) => reason.label).join(' · ')
        : 'Project health flagged as at risk.';
      addOrMerge({
        key: `project:${project.id}`,
        title: `${project.name} (Project #${project.id})`,
        detail: reasonText,
        link: `/wp-admin/admin.php?page=pet-delivery#project=${project.id}`,
        urgency: health.state === 'red' ? 88 : 74,
        tags: ['Project risk'],
      });
    });

    return Array.from(items.values()).sort((a, b) => {
      if (b.urgency !== a.urgency) return b.urgency - a.urgency;
      const aTs = parseTs(a.createdAt) || 0;
      const bTs = parseTs(b.createdAt) || 0;
      return bTs - aTs;
    });
  }, [atRiskProjects, breachedTickets, escalations, unassignedTickets]);

  const quoteInsights = useMemo<QuoteInsight[]>(() => {
    const now = Date.now();
    return quotes
      .filter((quote) => ['draft', 'sent'].includes(String(quote.state || '').toLowerCase()))
      .map((quote) => {
        const timestamp = getQuoteTimestamp(quote);
        const ts = parseTs(timestamp);
        const ageDays = ts === null ? null : Math.floor((now - ts) / (1000 * 60 * 60 * 24));
        const blocked = String(quote.state || '').toLowerCase() === 'draft'
          && (quote.totalValue <= 0 || !quoteHasPricedBlocks(quote));
        return { quote, blocked, ageDays };
      })
      .sort((a, b) => {
        const aAge = a.ageDays ?? -1;
        const bAge = b.ageDays ?? -1;
        if (bAge !== aAge) return bAge - aAge;
        return a.quote.id - b.quote.id;
      });
  }, [quotes]);

  const agingQuotes = useMemo(
    () => quoteInsights.filter((insight) => insight.ageDays !== null || String(insight.quote.state).toLowerCase() === 'sent').slice(0, 6),
    [quoteInsights]
  );

  const blockedQuotes = useMemo(
    () => quoteInsights.filter((insight) => insight.blocked).slice(0, 6),
    [quoteInsights]
  );

  const executeTicketRows = useMemo(
    () => supportTicketRows
      .filter((row) => row.slaState !== 'on_track')
      .sort((a, b) => {
        const rankA = rowRank(a.slaState);
        const rankB = rowRank(b.slaState);
        if (rankB !== rankA) return rankB - rankA;
        return (a.dueTs || Number.MAX_SAFE_INTEGER) - (b.dueTs || Number.MAX_SAFE_INTEGER);
      })
      .slice(0, 8),
    [supportTicketRows]
  );

  const weeklyEntrySummary = useMemo(() => {
    const weekStart = Date.now() - (7 * 24 * 60 * 60 * 1000);
    const weeklyEntries = timeEntries.filter((entry) => {
      const ts = parseTs(entry.start);
      return ts !== null && ts >= weekStart;
    });

    const totalEntries = weeklyEntries.length;
    const compliantEntries = weeklyEntries.filter((entry) => COMPLIANT_TIME_STATUSES.has(String(entry.status || '').toLowerCase())).length;
    const compliancePct = totalEntries > 0 ? Math.round((compliantEntries / totalEntries) * 100) : 100;
    const actualHours = Math.round((weeklyEntries.reduce((sum, entry) => sum + (entry.duration || 0), 0) / 60) * 10) / 10;
    const expectedHours = Math.round((activeEmployees.length * 40) * 10) / 10;
    const varianceHours = Math.round((actualHours - expectedHours) * 10) / 10;

    const employeeMap = new Map<number, { minutes: number; total: number; compliant: number }>();
    weeklyEntries.forEach((entry) => {
      const existing = employeeMap.get(entry.employeeId) || { minutes: 0, total: 0, compliant: 0 };
      const compliant = COMPLIANT_TIME_STATUSES.has(String(entry.status || '').toLowerCase()) ? 1 : 0;
      employeeMap.set(entry.employeeId, {
        minutes: existing.minutes + (entry.duration || 0),
        total: existing.total + 1,
        compliant: existing.compliant + compliant,
      });
    });

    const nonCompliantStaff = activeEmployees
      .map((employee) => {
        const stats = employeeMap.get(employee.id);
        const total = stats?.total ?? 0;
        const compliant = stats?.compliant ?? 0;
        const rate = total > 0 ? compliant / total : 0;
        const hours = Math.round(((stats?.minutes ?? 0) / 60) * 10) / 10;
        return { employee, rate, total, hours };
      })
      .filter((row) => row.total === 0 || row.rate < 0.8)
      .sort((a, b) => a.rate - b.rate || a.hours - b.hours);

    return {
      compliancePct,
      varianceHours,
      expectedHours,
      actualHours,
      nonCompliantStaff,
      hoursByEmployeeId: employeeMap,
    };
  }, [activeEmployees, timeEntries]);

  const peopleCapacity = useMemo(() => {
    const overloaded: Array<{ employee: Employee; hours: number }> = [];
    const underutilized: Array<{ employee: Employee; hours: number }> = [];

    activeEmployees.forEach((employee) => {
      const minutes = weeklyEntrySummary.hoursByEmployeeId.get(employee.id)?.minutes || 0;
      const hours = Math.round((minutes / 60) * 10) / 10;
      if (hours > 45) {
        overloaded.push({ employee, hours });
      } else if (hours < 20) {
        underutilized.push({ employee, hours });
      }
    });

    overloaded.sort((a, b) => b.hours - a.hours);
    underutilized.sort((a, b) => a.hours - b.hours);

    const demandUnits = supportTicketRows.length + (atRiskProjects.length * 2);
    const capacityUnits = activeEmployees.length * 5;
    const ratio = capacityUnits > 0 ? Math.round((demandUnits / capacityUnits) * 100) : 0;

    return {
      overloaded,
      underutilized,
      demandUnits,
      capacityUnits,
      ratio,
    };
  }, [activeEmployees, atRiskProjects.length, supportTicketRows.length, weeklyEntrySummary.hoursByEmployeeId]);

  const customerReadiness = useMemo(() => {
    const rows = customers.map((customer) => {
      const branchCount = sites.filter((site) => site.customerId === customer.id).length;
      const contactCount = contacts.filter((contact) =>
        (contact.affiliations || []).some((aff) => aff.customerId === customer.id)
        || (contact as any).customerId === customer.id
      ).length;

      const readiness: 'incomplete' | 'partial' | 'ready' = branchCount === 0
        ? 'incomplete'
        : (contactCount === 0 ? 'partial' : 'ready');

      const openTicketCount = activeTickets.filter((ticket) => ticket.customerId === customer.id).length;
      const activeProjectCount = activeProjects.filter((project) => project.customerId === customer.id).length;
      const impactScore = (activeProjectCount * 3) + openTicketCount;

      return {
        customer,
        readiness,
        branchCount,
        contactCount,
        openTicketCount,
        activeProjectCount,
        impactScore,
      };
    });

    const grouped = {
      incomplete: rows.filter((row) => row.readiness === 'incomplete').sort((a, b) => b.impactScore - a.impactScore || a.customer.name.localeCompare(b.customer.name)),
      partial: rows.filter((row) => row.readiness === 'partial').sort((a, b) => b.impactScore - a.impactScore || a.customer.name.localeCompare(b.customer.name)),
      ready: rows.filter((row) => row.readiness === 'ready').sort((a, b) => b.impactScore - a.impactScore || a.customer.name.localeCompare(b.customer.name)),
    };

    return grouped;
  }, [activeProjects, activeTickets, contacts, customers, sites]);

  const meaningfulEvents = useMemo(
    () => [...feedEvents]
      .filter((event) => {
        const classification = String(event.classification || '').toLowerCase();
        return event.pinned || ['critical', 'operational', 'strategic'].includes(classification);
      })
      .sort((a, b) => (parseTs(b.createdAt) || 0) - (parseTs(a.createdAt) || 0))
      .slice(0, 10),
    [feedEvents]
  );

  const advisorySnapshot = useMemo(
    () => advisorySignals
      .filter((signal) => String(signal.status || '').toUpperCase() !== 'RESOLVED')
      .sort((a, b) => {
        const sev = severityRank(b.severity) - severityRank(a.severity);
        if (sev !== 0) return sev;
        return (parseTs(b.created_at) || 0) - (parseTs(a.created_at) || 0);
      })
      .slice(0, 3),
    [advisorySignals]
  );

  if (loading) {
    return (
      <PageShell
        className="pet-overview-page"
        title="PET Overview"
        subtitle="Priority-driven operational control surface."
      >
        <Panel className="pet-overview-state-panel">
          <div className="pet-overview-state-message">Loading operational overview…</div>
        </Panel>
      </PageShell>
    );
  }

  if (!dashboardData && error) {
    return (
      <PageShell
        className="pet-overview-page"
        title="PET Overview"
        subtitle="Priority-driven operational control surface."
      >
        <Panel className="pet-overview-state-panel pet-overview-state-panel--error">
          <div className="pet-overview-state-message pet-overview-state-message--error">Error: {error}</div>
          <button type="button" className="button button-secondary" onClick={() => setRefreshNonce((value) => value + 1)}>
            Retry
          </button>
        </Panel>
      </PageShell>
    );
  }

  const overview = dashboardData?.overview;
  const readinessTone = String(demoHealth?.readiness_status || 'AMBER').toLowerCase();
  const readinessLabel = demoHealth?.readiness_status || 'AMBER';
  const readinessWarnings: string[] = [];
  if (demoHealth) {
    if (demoHealth.flags.no_active_seed_run) readinessWarnings.push('No active seed run');
    if (demoHealth.flags.has_duplicate_staff_metadata_pairs) readinessWarnings.push('Duplicate staff metadata pairs');
    if (demoHealth.integrity.duplicate_employee_emails > 0) readinessWarnings.push('Duplicate employee emails');
    if (demoHealth.environment.has_untracked_rows) readinessWarnings.push('Untracked legacy rows detected');
    if (demoHealth.seed.seed_error_in_last_run) readinessWarnings.push('Seed error in last run detected');
  }
  const capacityTone = peopleCapacity.ratio > 90 ? 'tight' : peopleCapacity.ratio > 70 ? 'busy' : 'healthy';
  const varianceTone = weeklyEntrySummary.varianceHours >= 0 ? 'positive' : 'negative';

  return (
    <PageShell
      className="pet-overview-page"
      title="PET Overview"
      subtitle="Priority-driven operational control surface for plan, execution, and readiness."
      actions={(
        <div className="pet-overview-shell-actions">
          <button
            type="button"
            className="button button-secondary"
            onClick={() => setRefreshNonce((value) => value + 1)}
            disabled={refreshing}
          >
            {refreshing ? 'Refreshing…' : 'Refresh data'}
          </button>
        </div>
      )}
    >
      {overview && (
        <Panel className="pet-overview-summary-panel">
          <div className="pet-overview-summary-grid">
            <div className="pet-overview-summary-item">
              <span className="pet-overview-summary-label">Active projects</span>
              <strong className="pet-overview-summary-value">{overview.activeProjects}</strong>
            </div>
            <div className="pet-overview-summary-item">
              <span className="pet-overview-summary-label">Pending quotes</span>
              <strong className="pet-overview-summary-value">{overview.pendingQuotes}</strong>
            </div>
            <div className="pet-overview-summary-item">
              <span className="pet-overview-summary-label">Utilization</span>
              <strong className="pet-overview-summary-value">{overview.utilizationRate}%</strong>
            </div>
            <div className="pet-overview-summary-item">
              <span className="pet-overview-summary-label">Revenue MTD</span>
              <strong className="pet-overview-summary-value">${overview.revenueThisMonth.toLocaleString()}</strong>
            </div>
          </div>
        </Panel>
      )}

      <Panel className="pet-overview-panel pet-overview-demo-health-panel">
        <div className="pet-overview-section-header">
          <div>
            <h3>Demo Readiness</h3>
            <p>Read-only environment health signal for demo safety.</p>
          </div>
        </div>
        {!demoHealth ? (
          <p className="pet-overview-empty">Demo readiness health is currently unavailable.</p>
        ) : (
          <div className="pet-overview-demo-health-grid">
            <div className="pet-overview-demo-health-status">
              <span className={`pet-overview-demo-health-badge pet-overview-demo-health-badge--${readinessTone}`}>
                {readinessLabel}
              </span>
            </div>
            <div className="pet-overview-demo-health-metrics">
              <div><strong>Last clean baseline run:</strong> {demoHealth.seed.last_clean_baseline_run || 'Unknown'}</div>
              <div><strong>Active seed run ID:</strong> {demoHealth.seed.active_seed_run_id || 'None'}</div>
              <div><strong>Tracked runs count:</strong> {demoHealth.seed.tracked_runs_count}</div>
              <div><strong>Duplicate emails / skill pairs / cert pairs:</strong> {demoHealth.integrity.duplicate_employee_emails} / {demoHealth.integrity.duplicate_skill_pairs} / {demoHealth.integrity.duplicate_certification_pairs}</div>
            </div>
            {readinessWarnings.length > 0 && (
              <ul className="pet-overview-list">
                {readinessWarnings.map((warning) => (
                  <li key={warning} className="pet-overview-list-row">
                    <span className="pet-overview-inline-status pet-overview-inline-status--risk">Warning</span> {warning}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </Panel>

      <Panel className="pet-overview-panel pet-overview-critical-panel">
        <div className="pet-overview-section-header">
          <div>
            <h3>Critical Attention</h3>
            <p>Urgency-sorted operational issues requiring immediate action.</p>
          </div>
          <a className="button button-small" href="/wp-admin/admin.php?page=pet-support">Support queues</a>
        </div>
        {criticalAttentionItems.length === 0 ? (
          <p className="pet-overview-empty">
            No SLA breaches, escalations, at-risk projects, or unassigned tickets need immediate attention.
          </p>
        ) : (
          <ul className="pet-overview-list pet-overview-critical-list">
            {criticalAttentionItems.slice(0, 12).map((item) => (
              <li key={item.key} className="pet-overview-list-item pet-overview-critical-item">
                <div className="pet-overview-item-main">
                  <div className="pet-overview-item-title">{item.title}</div>
                  <div className="pet-overview-item-detail">{item.detail}</div>
                  <div className="pet-overview-item-meta">
                    {item.tags.map((tag) => (
                      <span key={`${item.key}:${tag}`} className="pet-overview-tag">
                        {tag}
                      </span>
                    ))}
                    {item.createdAt && (
                      <span className="pet-overview-muted">{timeAgo(item.createdAt)}</span>
                    )}
                  </div>
                </div>
                <a className="button button-small pet-overview-item-action" href={item.link}>
                  Open
                </a>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <div className="pet-overview-grid pet-overview-grid--triptych">
        <Panel className="pet-overview-panel">
          <div className="pet-overview-section-header">
            <div>
              <h3>Plan</h3>
              <p>Quote pipeline readiness and blockers.</p>
            </div>
            <a className="button button-small" href="/wp-admin/admin.php?page=pet-quotes-sales">Quotes</a>
          </div>
          <div className="pet-overview-stack">
            <section className="pet-overview-subsection">
              <h4>Aging quotes</h4>
              {agingQuotes.length === 0 ? (
                <p className="pet-overview-empty">No aging quotes detected from available quote timestamps.</p>
              ) : (
                <ul className="pet-overview-list">
                  {agingQuotes.map(({ quote, ageDays }) => (
                    <li key={`aging:${quote.id}`} className="pet-overview-list-row">
                      <strong>#{quote.id}</strong> {quote.title || '(untitled)'}{' '}
                      <span className="pet-overview-muted">
                        · {ageDays === null ? 'age unknown' : `${ageDays}d open`} · {quote.state}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
            <section className="pet-overview-subsection">
              <h4>Blocked quotes</h4>
              {blockedQuotes.length === 0 ? (
                <p className="pet-overview-empty">No blocked draft quotes.</p>
              ) : (
                <ul className="pet-overview-list">
                  {blockedQuotes.map(({ quote }) => (
                    <li key={`blocked:${quote.id}`} className="pet-overview-list-row">
                      <strong>#{quote.id}</strong> {quote.title || '(untitled)'}{' '}
                      <span className="pet-overview-muted">· missing priced structure or total value</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        </Panel>

        <Panel className="pet-overview-panel">
          <div className="pet-overview-section-header">
            <div>
              <h3>Execute</h3>
              <p>Delivery health and ticket SLA state.</p>
            </div>
            <div className="pet-overview-inline-actions">
              <a className="button button-small" href="/wp-admin/admin.php?page=pet-delivery">Projects</a>
              <a className="button button-small" href="/wp-admin/admin.php?page=pet-support">Tickets</a>
            </div>
          </div>
          <div className="pet-overview-stack">
            <section className="pet-overview-subsection">
              <h4>Projects (health)</h4>
              {atRiskProjects.length === 0 ? (
                <p className="pet-overview-empty">All active projects are currently on track.</p>
              ) : (
                <ul className="pet-overview-list">
                  {atRiskProjects.slice(0, 6).map(({ project, health }) => (
                    <li key={`proj:${project.id}`} className="pet-overview-list-row">
                      <strong>{project.name}</strong>{' '}
                      <span className="pet-overview-muted">
                        · {health.reasons.length > 0 ? health.reasons.map((reason) => reason.label).join(' · ') : 'At risk'}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
            <section className="pet-overview-subsection">
              <h4>Tickets (SLA state)</h4>
              {executeTicketRows.length === 0 ? (
                <p className="pet-overview-empty">No ticket SLA breaches or near-breach risks in visible support queues.</p>
              ) : (
                <ul className="pet-overview-list">
                  {executeTicketRows.map(({ item, slaState }) => (
                    <li key={`sla:${item.source_id}`} className="pet-overview-list-row">
                      <strong>{item.title || `Ticket #${item.source_id}`}</strong>{' '}
                      <span className={`pet-overview-inline-status pet-overview-inline-status--${slaState}`}>
                        · {slaState === 'breached' ? overdueBy(item.due_at) : 'Due within 1h'}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        </Panel>

        <Panel className="pet-overview-panel">
          <div className="pet-overview-section-header">
            <div>
              <h3>Track</h3>
              <p>Timesheet compliance and weekly variance.</p>
            </div>
            <a className="button button-small" href="/wp-admin/admin.php?page=pet-time">Time</a>
          </div>
          <div className="pet-overview-stack">
            <div className="pet-overview-metric-grid">
              <div className="pet-overview-metric-card">
                <span className="pet-overview-metric-label">Timesheet compliance</span>
                <strong className="pet-overview-metric-value">{weeklyEntrySummary.compliancePct}%</strong>
              </div>
              <div className="pet-overview-metric-card">
                <span className="pet-overview-metric-label">Weekly variance</span>
                <strong className={`pet-overview-metric-value pet-overview-metric-value--${varianceTone}`}>
                  {weeklyEntrySummary.varianceHours >= 0 ? '+' : ''}{weeklyEntrySummary.varianceHours}h
                </strong>
              </div>
            </div>
            <p className="pet-overview-muted">
              Actual {weeklyEntrySummary.actualHours}h vs expected {weeklyEntrySummary.expectedHours}h
            </p>
            <section className="pet-overview-subsection">
              <h4>At-risk timesheets</h4>
              {weeklyEntrySummary.nonCompliantStaff.length === 0 ? (
                <p className="pet-overview-empty">No staff currently below compliance thresholds.</p>
              ) : (
                <ul className="pet-overview-list">
                  {weeklyEntrySummary.nonCompliantStaff.slice(0, 6).map((row) => (
                    <li key={`track:${row.employee.id}`} className="pet-overview-list-row">
                      <strong>{displayName(row.employee)}</strong>{' '}
                      <span className="pet-overview-muted">
                        · {row.total === 0 ? 'no entries this week' : `${Math.round(row.rate * 100)}% compliant`}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        </Panel>
      </div>

      <Panel className="pet-overview-panel">
        <div className="pet-overview-section-header">
          <div>
            <h3>People &amp; Capacity</h3>
            <p>Overload risk, under-utilization, and demand pressure.</p>
          </div>
          <a className="button button-small" href="/wp-admin/admin.php?page=pet-people">Staff</a>
        </div>
        <div className="pet-overview-grid pet-overview-grid--three">
          <section className="pet-overview-subpanel">
            <h4>Overloaded staff</h4>
            {peopleCapacity.overloaded.length === 0 ? (
              <p className="pet-overview-empty">No overloaded staff based on weekly logged hours.</p>
            ) : (
              <ul className="pet-overview-list">
                {peopleCapacity.overloaded.slice(0, 6).map((row) => (
                  <li key={`over:${row.employee.id}`} className="pet-overview-list-row">
                    <strong>{displayName(row.employee)}</strong>{' '}
                    <span className="pet-overview-inline-status pet-overview-inline-status--breached">· {row.hours}h / 7d</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
          <section className="pet-overview-subpanel">
            <h4>Underutilized staff</h4>
            {peopleCapacity.underutilized.length === 0 ? (
              <p className="pet-overview-empty">No underutilized staff based on weekly logged hours.</p>
            ) : (
              <ul className="pet-overview-list">
                {peopleCapacity.underutilized.slice(0, 6).map((row) => (
                  <li key={`under:${row.employee.id}`} className="pet-overview-list-row">
                    <strong>{displayName(row.employee)}</strong>{' '}
                    <span className="pet-overview-muted">· {row.hours}h / 7d</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
          <section className="pet-overview-subpanel">
            <h4>Capacity vs demand</h4>
            <p className="pet-overview-muted">
              Demand units: {peopleCapacity.demandUnits} · Capacity units: {peopleCapacity.capacityUnits}
            </p>
            <div className="pet-overview-capacity-track">
              <span
                className={`pet-overview-capacity-fill pet-overview-capacity-fill--${capacityTone}`}
                style={{ width: `${Math.min(peopleCapacity.ratio, 100)}%` }}
              />
            </div>
            <p className="pet-overview-capacity-summary">
              {peopleCapacity.ratio > 90
                ? 'Capacity is tight against current support and project demand.'
                : peopleCapacity.ratio > 70
                ? 'Capacity is balanced but trending busy.'
                : 'Capacity currently exceeds measured operational demand.'}
            </p>
          </section>
        </div>
      </Panel>

      <Panel className="pet-overview-panel">
        <div className="pet-overview-section-header">
          <div>
            <h3>Customer Readiness</h3>
            <p>Operational onboarding completeness and delivery impact.</p>
          </div>
          <a className="button button-small" href="/wp-admin/admin.php?page=pet-crm">Customers</a>
        </div>
        <div className="pet-overview-grid pet-overview-grid--three">
          <ReadinessGroup
            title="Incomplete"
            tone="incomplete"
            rows={customerReadiness.incomplete}
            emptyLabel="No incomplete customers."
          />
          <ReadinessGroup
            title="Partial"
            tone="partial"
            rows={customerReadiness.partial}
            emptyLabel="No partially configured customers."
          />
          <ReadinessGroup
            title="Ready"
            tone="ready"
            rows={customerReadiness.ready}
            emptyLabel="No ready customers found."
          />
        </div>
      </Panel>

      <div className="pet-overview-grid pet-overview-grid--two">
        <Panel className="pet-overview-panel">
          <div className="pet-overview-section-header">
            <div>
              <h3>Event Feed</h3>
              <p>Recent meaningful operational and strategic events.</p>
            </div>
            <a className="button button-small" href="/wp-admin/admin.php?page=pet-activity">Activity</a>
          </div>
          {meaningfulEvents.length === 0 ? (
            <p className="pet-overview-empty">No meaningful recent feed events.</p>
          ) : (
            <ul className="pet-overview-list">
              {meaningfulEvents.map((event) => (
                <li key={event.id} className="pet-overview-list-item">
                  <div className="pet-overview-item-title">{event.title}</div>
                  <div className="pet-overview-item-detail">{event.summary}</div>
                  <div className="pet-overview-muted">
                    {String(event.classification || 'operational').toUpperCase()} · {timeAgo(event.createdAt)}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        <Panel className="pet-overview-panel">
          <div className="pet-overview-section-header">
            <div>
              <h3>Advisory Snapshot</h3>
              <p>Top active signals with entity scope and reason.</p>
            </div>
            <a className="button button-small" href="/wp-admin/admin.php?page=pet-advisory">Advisory</a>
          </div>
          {advisorySnapshot.length === 0 ? (
            <p className="pet-overview-empty">No active advisory signals available.</p>
          ) : (
            <ul className="pet-overview-list">
              {advisorySnapshot.map((signal) => {
                const entity = signal.source_entity_type && signal.source_entity_id
                  ? `${signal.source_entity_type} #${signal.source_entity_id}`
                  : signal.customer_id
                  ? `customer #${signal.customer_id}`
                  : 'unscoped entity';
                const reason = signal.summary || signal.message || signal.title || signal.signal_type;
                return (
                  <li key={signal.id} className="pet-overview-list-item">
                    <div className="pet-overview-item-title">{signal.title || signal.signal_type}</div>
                    <div className="pet-overview-item-detail"><strong>Entity:</strong> {entity}</div>
                    <div className="pet-overview-item-detail"><strong>Reason:</strong> {reason}</div>
                    <div className="pet-overview-muted">
                      {String(signal.severity || 'unknown').toUpperCase()} · {timeAgo(signal.created_at)}
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </Panel>
      </div>

      {error && (
        <div className="pet-overview-warning">{error}</div>
      )}
    </PageShell>
  );
};

const rowRank = (slaState: string): number => {
  if (slaState === 'breached') return 3;
  if (slaState === 'risk') return 2;
  return 1;
};

const ReadinessGroup: React.FC<{
  title: string;
  tone: 'incomplete' | 'partial' | 'ready';
  rows: Array<{
    customer: Customer;
    branchCount: number;
    contactCount: number;
    openTicketCount: number;
    activeProjectCount: number;
  }>;
  emptyLabel: string;
}> = ({ title, tone, rows, emptyLabel }) => (
  <section className={`pet-overview-readiness-group pet-overview-readiness-group--${tone}`}>
    <div className="pet-overview-readiness-header">
      <div className="pet-overview-readiness-title">{title}</div>
      <div className="pet-overview-readiness-count">{rows.length}</div>
    </div>
    {rows.length === 0 ? (
      <p className="pet-overview-empty">{emptyLabel}</p>
    ) : (
      <ul className="pet-overview-list">
        {rows.slice(0, 7).map((row) => (
          <li key={`${title}:${row.customer.id}`} className="pet-overview-list-row">
            <div className="pet-overview-item-title">{row.customer.name}</div>
            <div className="pet-overview-muted">
              {row.branchCount} branches · {row.contactCount} contacts
            </div>
            <div className="pet-overview-item-detail">
              Delivery impact: {row.activeProjectCount} active projects · {row.openTicketCount} open tickets
            </div>
          </li>
        ))}
      </ul>
    )}
  </section>
);

export default Dashboard;
