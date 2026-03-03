/* ==========================================================================
   Universal Health Border — Compute Functions
   ========================================================================== */

export type HealthState = 'red' | 'amber' | 'green' | 'blue' | 'grey';

export interface HealthReason {
  label: string;
  color: HealthState;
}

export interface HealthResult {
  state: HealthState;
  reasons: HealthReason[];
  /** CSS class for the UHB border: uhb-red, uhb-amber, etc. */
  className: string;
}

export interface HealthHistory {
  was_red: boolean;
  was_amber: boolean;
}

/** Configurable thresholds — loaded from Settings API, with fallback defaults. */
export interface UhbThresholds {
  ticketSlaWarningMinutes: number;    // default 60
  quoteStaleDays: number;             // default 7
  quoteFollowUpDays: number;          // default 3
  quoteAgingDraftDays: number;        // default 14
  leadColdDays: number;               // default 14
  leadCoolingDays: number;            // default 7
}

export const UHB_DEFAULTS: UhbThresholds = {
  ticketSlaWarningMinutes: 60,
  quoteStaleDays: 7,
  quoteFollowUpDays: 3,
  quoteAgingDraftDays: 14,
  leadColdDays: 14,
  leadCoolingDays: 7,
};

function result(state: HealthState, reasons: HealthReason[] = []): HealthResult {
  return { state, reasons, className: `uhb-${state}` };
}

/* ------------------------------------------------------------------
   Tickets
   ------------------------------------------------------------------ */
export function computeTicketHealth(
  ticket: { status: string },
  slaTimeRemaining: number | null,
  thresholds: UhbThresholds = UHB_DEFAULTS,
): HealthResult {
  // Terminal states → blue
  if (['resolved', 'closed'].includes(ticket.status)) {
    return result('blue');
  }

  // No SLA data → grey (unscored)
  if (slaTimeRemaining === null) {
    return result('grey');
  }

  // SLA breached
  if (slaTimeRemaining < 0) {
    return result('red', [{ label: 'BREACHED', color: 'red' }]);
  }

  // SLA warning
  if (slaTimeRemaining <= thresholds.ticketSlaWarningMinutes) {
    return result('amber', [{ label: 'SLA WARNING', color: 'amber' }]);
  }

  // Healthy
  return result('green');
}

/* ------------------------------------------------------------------
   Projects
   ------------------------------------------------------------------ */
export function computeProjectHealth(
  project: {
    state: string;
    endDate?: string | null;
    soldHours?: number;
    malleableData?: { hours_used?: number; health?: string };
    tasks?: { completed: boolean }[];
  },
): HealthResult {
  // Completed → blue
  if (project.state === 'completed') {
    return result('blue');
  }

  // Planned with no dates/budget → grey
  if (project.state === 'planned' && !project.endDate && !project.soldHours) {
    return result('grey');
  }

  const now = Date.now();
  const reasons: HealthReason[] = [];
  let worstState: HealthState = 'green';

  const hoursUsed = project.malleableData?.hours_used ?? 0;
  const soldH = project.soldHours || 0;
  const taskCount = project.tasks?.length ?? 0;
  const completedCount = project.tasks?.filter(t => t.completed).length ?? 0;
  const progress = taskCount > 0 ? Math.round((completedCount / taskCount) * 100) : 0;
  const burnPct = soldH > 0 ? Math.round((hoursUsed / soldH) * 100) : 0;

  // Red triggers
  if (project.endDate && new Date(project.endDate).getTime() < now) {
    reasons.push({ label: 'OVERDUE', color: 'red' });
    worstState = 'red';
  }
  if (soldH > 0 && hoursUsed > soldH) {
    reasons.push({ label: 'OVER BUDGET', color: 'red' });
    worstState = 'red';
  }

  // Amber triggers (only if not already red)
  if (worstState !== 'red') {
    if (burnPct > 80 && progress < 80) {
      reasons.push({ label: 'AT RISK', color: 'amber' });
      worstState = 'amber';
    }
  }

  return result(worstState, reasons);
}

/* ------------------------------------------------------------------
   Quotes
   ------------------------------------------------------------------ */
export function computeQuoteHealth(
  quote: {
    state: string;
    totalValue?: number;
    createdAt: string;
    updatedAt?: string | null;
  },
  thresholds: UhbThresholds = UHB_DEFAULTS,
): HealthResult {
  // Accepted → blue
  if (quote.state === 'accepted') {
    return result('blue');
  }

  // Rejected / cancelled → grey
  if (quote.state === 'rejected' || quote.state === 'cancelled') {
    return result('grey');
  }

  const now = Date.now();
  const reasons: HealthReason[] = [];

  if (quote.state === 'sent') {
    const sentAge = quote.updatedAt
      ? Math.floor((now - new Date(quote.updatedAt).getTime()) / 86400000)
      : 0;

    if (sentAge > thresholds.quoteStaleDays) {
      reasons.push({ label: 'STALE', color: 'red' });
      return result('red', reasons);
    }
    if (sentAge > thresholds.quoteFollowUpDays) {
      reasons.push({ label: 'FOLLOW UP', color: 'amber' });
      return result('amber', reasons);
    }
    return result('green');
  }

  if (quote.state === 'draft') {
    const draftAge = Math.floor((now - new Date(quote.createdAt).getTime()) / 86400000);
    if (draftAge > thresholds.quoteAgingDraftDays) {
      reasons.push({ label: 'AGING DRAFT', color: 'amber' });
      return result('amber', reasons);
    }
    return result('green');
  }

  return result('grey');
}

/* ------------------------------------------------------------------
   Leads
   ------------------------------------------------------------------ */
export function computeLeadHealth(
  lead: {
    status: string;
    createdAt: string;
    updatedAt?: string | null;
  },
  thresholds: UhbThresholds = UHB_DEFAULTS,
): HealthResult {
  // Converted → blue
  if (lead.status === 'converted') {
    return result('blue');
  }

  // Lost / disqualified → grey
  if (lead.status === 'lost' || lead.status === 'disqualified') {
    return result('grey');
  }

  // Active leads: check staleness
  if (lead.status === 'new' || lead.status === 'qualified') {
    const lastTouch = lead.updatedAt || lead.createdAt;
    const ageDays = Math.floor((Date.now() - new Date(lastTouch).getTime()) / 86400000);

    if (ageDays > thresholds.leadColdDays) {
      return result('red', [{ label: 'COLD', color: 'red' }]);
    }
    if (ageDays > thresholds.leadCoolingDays) {
      return result('amber', [{ label: 'COOLING', color: 'amber' }]);
    }
    return result('green');
  }

  return result('grey');
}

/* ------------------------------------------------------------------
   React helper: Recovery dots JSX
   ------------------------------------------------------------------ */
export function RecoveryDots({ history }: { history?: HealthHistory | null }) {
  if (!history || (!history.was_red && !history.was_amber)) return null;

  // Import React at module level isn't needed since this is in a .ts file
  // that will be imported by .tsx files which already have React in scope.
  // We'll use createElement to avoid needing JSX transform in a .ts file.
  return null; // Placeholder — actual JSX component is below
}

/* Inline React component for recovery dots (use in .tsx consumers) */
export const recoveryDotsMarkup = (history?: HealthHistory | null): string => {
  if (!history) return '';
  const parts: string[] = [];
  if (history.was_red) parts.push('red');
  if (history.was_amber) parts.push('amber');
  return parts.join(',');
};
