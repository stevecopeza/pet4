
export interface WorkItem {
  id: string;
  source_type: string;
  source_id: string;
  assigned_user_id: string | null;
  department_id: string;
  priority_score: number;
  status: string;
  sla_time_remaining: number | null;
  due_date: string | null;
  manager_override: number;
  revenue: number;
  client_tier: number;
  signals: WorkItemSignal[];
}

export interface DemoEnvironmentHealth {
  readiness_status: 'GREEN' | 'AMBER' | 'RED';
  seed: {
    active_seed_run_id: string | null;
    active_seed_run_created_at: string | null;
    active_seed_run_last_seen_at: string | null;
    active_seed_run_registry_rows: number;
    tracked_runs_count: number;
    active_runs_count: number;
    last_clean_baseline_run: string | null;
    seed_error_in_last_run: boolean;
  };
  integrity: {
    duplicate_employee_emails: number;
    duplicate_skill_pairs: number;
    duplicate_certification_pairs: number;
  };
  environment: {
    has_untracked_rows: boolean;
    untracked_rows_by_table: Record<string, number>;
    notes: string[];
  };
  flags: {
    no_active_seed_run: boolean;
    has_duplicate_staff_metadata_pairs: boolean;
    has_integrity_violation: boolean;
    has_contamination_risk: boolean;
  };
}

export interface WorkItemSignal {
  type: string;
  severity: string;
  message: string;
  created_at: string;
}

export interface DashboardData {
  overview: {
    activeProjects: number;
    pendingQuotes: number;
    utilizationRate: number;
    revenueThisMonth: number;
  };
  recentActivity: Array<{
    id: number;
    type: string;
    message: string;
    time: string;
  }>;
  skillHeatmap: Array<{
    skill_name: string;
    avg_rating: number;
  }>;
  kpiPerformance: Array<{
    kpi_name: string;
    avg_score: number;
  }>;
  demoWow?: {
    escalationRules: {
      enabledCount: number;
      totalCount: number;
    };
    slaRisk: {
      warningCount: number;
      breachedCount: number;
    };
    workload: {
      unassignedTicketsCount: number;
    };
    actions: {
      escalationRulesUrl: string;
      helpdeskUrl: string;
      advisoryUrl?: string;
    };
  };
}

export interface Project {
  id: number;
  name: string;
  customerId: number;
  sourceQuoteId?: number | null;
  soldHours: number;
  soldValue: number;
  state: string;
  startDate?: string;
  endDate?: string;
  malleableData?: Record<string, any>;
  tasks: Task[];
  archivedAt: string | null;
}

export interface Task {
  id: number;
  name: string;
  estimatedHours: number;
  completed: boolean;
}

export interface QuoteApprovalState {
  rejectionNote: string | null;
  submittedForApprovalAt: string | null;
  approvedAt: string | null;
  approvedByUserId: number | null;
  requiresApprovalForSend: boolean;
  approvalReasons: string[];
}

export interface Quote {
  id: number;
  customerId: number;
  title: string;
  description?: string;
  state: string;
  version: number;
  totalValue: number;
  totalInternalCost?: number;
  adjustedTotalInternalCost?: number;
  margin?: number;
  currency: string;
  acceptedAt?: string;
  lines?: QuoteLine[]; // Deprecated
  components?: QuoteComponent[];
  sections?: QuoteSection[];
  blocks?: QuoteBlock[];
  costAdjustments?: CostAdjustment[];
  malleableData?: Record<string, any>;
  approvalState?: QuoteApprovalState;
  paymentSchedule?: Array<{
    id: number;
    title: string;
    amount: number;
    dueDate: string | null;
    isPaid: boolean;
  }>;
}

export interface ConversationParticipant {
  type: 'user' | 'contact' | 'team';
  id: number;
  name: string;
  added_at: string;
}

export interface Conversation {
  uuid: string;
  context_type: string;
  context_id: string;
  subject: string;
  state: string;
  created_at: string;
  participants: ConversationParticipant[];
  decisions: Decision[];
  timeline: TimelineEvent[];
}

export interface Decision {
  uuid: string;
  decision_type: string;
  state: 'pending' | 'approved' | 'rejected' | 'cancelled';
  payload: any;
  outcome: string | null;
  requested_at: string;
  finalized_at: string | null;
}

export interface TimelineEvent {
  id: number;
  type: string;
  payload: any;
  occurred_at: string;
  actor_id: number;
}

export interface CostAdjustment {
  id: number;
  description: string;
  amount: number;
  reason: string;
  approvedBy: string;
  appliedAt: string;
}

export interface QuoteComponent {
  id: string;
  type: string;
  section: string;
  description: string;
  sellValue: number;
  internalCost: number;
  topology?: string;
  // ImplementationComponent
  milestones?: {
    id: number | null;
    title: string;
    description?: string | null;
    tasks: {
      id: number | null;
      title: string;
      description?: string | null;
      durationHours: number;
      sellRate: number;
      baseInternalRate: number;
      sellValue: number;
      internalCost: number;
    }[];
    sellValue: number;
    internalCost: number;
  }[];
  // CatalogComponent
  items?: {
    description: string;
    quantity: number;
    unitSellPrice: number;
    sellValue: number;
  }[];
  // OnceOffServiceComponent (simple)
  units?: {
    id: number | null;
    title: string;
    description?: string | null;
    quantity: number;
    unitSellPrice: number;
    unitInternalCost: number;
    sellValue: number;
    internalCost: number;
  }[];
  // OnceOffServiceComponent (complex)
  phases?: {
    id: number | null;
    name: string;
    description?: string | null;
    units: {
      id: number | null;
      title: string;
      description?: string | null;
      quantity: number;
      unitSellPrice: number;
      unitInternalCost: number;
      sellValue: number;
      internalCost: number;
    }[];
    sellValue: number;
    internalCost: number;
  }[];
  // RecurringServiceComponent
  serviceName?: string;
  cadence?: string;
  termMonths?: number;
  renewalModel?: string;
  sellPricePerPeriod?: number;
  internalCostPerPeriod?: number;
}

export interface QuoteSection {
  id: number;
  quoteId: number;
  name: string;
  orderIndex: number;
  showTotalValue: boolean;
  showItemCount: boolean;
  showTotalHours: boolean;
}

export interface QuoteBlock {
  id: number;
  quoteId: number | null;
  sectionId: number | null;
  type: string;
  orderIndex: number;
  componentId: number | null;
  priced: boolean;
  payload: Record<string, any>;
  lineSellValue?: number | null;
  lineCostValue?: number | null;
  marginAmount?: number | null;
  marginPercentage?: number | null;
  hasMarginData?: boolean;
}

export interface QuoteLine {
  id: number;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
  group: string;
}

export interface TimeEntry {
  id: number;
  employeeId: number;
  ticketId: number;
  start: string;
  end: string;
  duration: number;
  description: string;
  billable: boolean;
  status: string;
  malleableData?: Record<string, any>;
  correctsEntryId?: number | null;
  isCorrection?: boolean;
  createdAt?: string;
  archivedAt?: string | null;
  billingStatus?: 'ready' | 'blocked' | 'billed' | 'non_billable';
  billingBlockReason?: string | null;
}

export interface SlaTier {
  id?: number | null;
  priority: number;
  label: string;
  calendar_id: number;
  calendar_name?: string | null;
  response_target_minutes: number;
  resolution_target_minutes: number;
  escalation_rules: EscalationRule[];
}

export interface Sla {
  id: number;
  name: string;
  response_target_minutes: number | null;
  resolution_target_minutes: number | null;
  calendar_id?: number;
  calendar_name?: string | null;
  escalation_rules?: EscalationRule[];
  is_tiered?: boolean;
  tier_transition_cap_percent?: number;
  tiers?: SlaTier[];
}

export interface ContactAffiliation {
  customerId: number;
  siteId: number | null;
  role: string | null;
  isPrimary: boolean;
}

export interface Contact {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  phone: string | null;
  affiliations: ContactAffiliation[];
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Customer {
  id: number;
  name: string;
  legalName?: string;
  contactEmail: string;
  status: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Site {
  id: number;
  customerId: number;
  name: string;
  addressLines: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  country: string | null;
  status: string;
  malleableData?: Record<string, any>;
  createdAt?: string;
  archivedAt: string | null;
}

export interface Employee {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  email: string;
  displayName?: string;
  jobTitle?: string;
  status?: string;
  hireDate?: string;
  managerId?: number;
  teamIds?: number[];
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface TeamVisual {
  type: string | null;
  ref: string | null;
  version?: string | null;
}

export interface Team {
  id: number;
  name: string;
  parent_team_id?: number | null;
  manager_id?: number | null;
  escalation_manager_id?: number | null;
  status: string;
  visual?: TeamVisual;
  member_ids?: number[];
  member_roles?: Record<string, string>;
  created_at: string;
  children?: Team[];
}

export interface SchemaFieldDefinition {
  label: string;
  key: string;
  type: string;
  required?: boolean;
  options?: string[];
}

export interface SchemaDefinition {
  id: number;
  entityType: string;
  status: string;
  version: number;
  schema: SchemaFieldDefinition[];
  fields?: SchemaFieldDefinition[];
  publishedAt?: string | null;
}

export interface Ticket {
  id: number;
  customerId: number;
  siteId?: number;
  slaId?: number;
  subject: string;
  description: string;
  status: string;
  priority: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt?: string | null;
  openedAt?: string | null;
  resolvedAt: string | null;
  closedAt?: string | null;
  sla_status?: string;
  response_due_at?: string;
  resolution_due_at?: string;
  ticketMode?: string;
  assignedUserId?: string | null;
  category?: string | null;
  subcategory?: string | null;
  intake_source?: string | null;
  contactId?: number | null;
  queueId?: string | null;
  ownerUserId?: string | null;
  lifecycleOwner?: string;
  isBillableDefault?: boolean;
  billingContextType?: string;
  // Backbone fields
  soldMinutes?: number | null;
  estimatedMinutes?: number | null;
  isBaselineLocked?: boolean;
  isRollup?: boolean;
  parentTicketId?: number | null;
  rootTicketId?: number | null;
  changeOrderSourceTicketId?: number | null;
  projectId?: number | null;
  quoteId?: number | null;
  ticketKind?: string;
  soldValueCents?: number | null;
}

export interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  status: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt: string | null;
}

export interface ActivityLog {
  id: number;
  occurred_at: string;
  actor_type: string;
  actor_id: string | null;
  actor_display_name: string;
  event_type: string;
  severity: string;
  reference_type: string | null;
  reference_id: string | null;
  headline: string;
  subline: string;
  tags: string[];
}

export interface Setting {
  key: string;
  value: string;
  type: string;
  description: string;
  updatedAt: string | null;
}

export interface FeedEvent {
  id: string;
  eventType: string;
  sourceEngine: string;
  sourceEntityId: string;
  classification: 'critical' | 'operational' | 'informational' | 'strategic';
  title: string;
  summary: string;
  metadata: Record<string, any>;
  audienceScope: 'global' | 'department' | 'role' | 'user';
  audienceReferenceId: string | null;
  pinned: boolean;
  expiresAt: string | null;
  createdAt: string;
}

export interface Announcement {
  id: string;
  title: string;
  body: string;
  priorityLevel: 'low' | 'normal' | 'high' | 'critical';
  pinned: boolean;
  acknowledgementRequired: boolean;
  gpsRequired: boolean;
  acknowledgementDeadline: string | null;
  audienceScope: 'global' | 'department' | 'role';
  audienceReferenceId: string | null;
  authorUserId: string;
  expiresAt: string | null;
  createdAt: string;
}

export interface Calendar {
  id: number;
  name: string;
  timezone: string;
  is_default: boolean;
  is_24x7: boolean;
  exclude_public_holidays: boolean;
  public_holiday_country: string | null;
  working_windows: WorkingWindow[];
  holidays: Holiday[];
}

export interface WorkingWindow {
  day_of_week: number;
  start_time: string;
  end_time: string;
}

export interface Holiday {
  name: string;
  date: string;
  is_recurring: boolean;
}

export interface Certification {
  id: number;
  name: string;
  issuing_body: string;
  expiry_months: number;
}

export interface PersonCertification {
  id: number;
  employee_id: number;
  certification_id: number;
  obtained_date: string;
  expiry_date: string | null;
  evidence_url: string | null;
  status: string;
  certification_name?: string;
  issuing_body?: string;
}

export interface Skill {
  id: number;
  name: string;
  category?: string;
}

export interface KpiDefinition {
  id: number;
  name: string;
  description: string;
  default_frequency: string;
  unit: string;
}

export interface PersonKpi {
  id: number;
  employee_id: number;
  kpi_definition_id: number;
  role_id?: number;
  period_start: string;
  period_end: string;
  target_value: number;
  actual_value: number | null;
  score: number | null;
  kpi_name?: string;
  kpi_unit?: string;
}

export interface RoleKpi {
  id: number;
  role_id: number;
  kpi_definition_id: number;
  weight_percentage: number;
  target_value: number;
  measurement_frequency: string;
  kpi_name?: string;
  kpi_unit?: string;
}

export interface Lead {
  id: number;
  customerId: number | null;
  customerName: string | null;
  subject: string;
  description: string;
  status: string;
  source: string | null;
  estimatedValue: number | null;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt: string | null;
  convertedAt: string | null;
  archivedAt: string | null;
}

export interface EscalationRule {
  percentage: number;
  action: string;
  notify_role_id?: number;
}

export interface Role {
  id: number;
  name: string;
  description?: string;
}
