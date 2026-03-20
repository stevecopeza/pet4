import React, { useEffect, useMemo, useState } from 'react';
import { Customer, Employee, Site, Ticket, TimeEntry } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import TimeEntryForm from './TimeEntryForm';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import ActionBar from './foundation/ActionBar';
import useConversation from '../hooks/useConversation';
import useConversationStatus from '../hooks/useConversationStatus';

const formatMinutes = (minutes: number) => {
  const safeMinutes = Math.max(0, Math.round(minutes));
  const hours = Math.floor(safeMinutes / 60);
  const remainder = safeMinutes % 60;
  if (hours > 0) {
    return `${hours}h ${remainder}m`;
  }
  return `${remainder}m`;
};

const formatDateTimeCompact = (value: string) => (
  new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
);

type AttentionSignal = {
  key: string;
  label: string;
  title: string;
  tone: 'high' | 'medium' | 'low';
};

type BillingStatus = 'ready' | 'blocked' | 'billed' | 'non_billable';

const BILLING_STATUS_LABELS: Record<BillingStatus, string> = {
  ready: 'Ready',
  blocked: 'Blocked',
  billed: 'Billed',
  non_billable: 'Non-billable',
};

const getAttentionSignals = (entry: TimeEntry): AttentionSignal[] => {
  const signals: AttentionSignal[] = [];
  const duration = entry.duration || 0;
  const description = (entry.description || '').trim();

  if (entry.isCorrection || entry.correctsEntryId) {
    signals.push({
      key: 'correction',
      label: 'Correction',
      title: 'Correction entry',
      tone: 'medium',
    });
  }

  if (!description) {
    signals.push({
      key: 'missing-description',
      label: 'No Description',
      title: 'Missing description',
      tone: 'high',
    });
  }

  if (duration >= 480) {
    signals.push({
      key: 'large-duration',
      label: 'Long Entry',
      title: `Large single duration (${formatMinutes(duration)})`,
      tone: 'medium',
    });
  }

  if (!entry.billable && duration >= 240) {
    signals.push({
      key: 'long-non-billable',
      label: 'Long Non-billable',
      title: `Long non-billable duration (${formatMinutes(duration)})`,
      tone: 'low',
    });
  }

  if (entry.billingStatus === 'blocked') {
    signals.push({
      key: 'billing-blocked',
      label: 'Billing Blocked',
      title: entry.billingBlockReason || 'Blocked for billing',
      tone: 'high',
    });
  }

  return signals;
};

const TimeEntries = () => {
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingEntry, setEditingEntry] = useState<TimeEntry | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [employeeFilter, setEmployeeFilter] = useState<string>('');
  const [ticketFilter, setTicketFilter] = useState<string>('');
  const [activePreset, setActivePreset] = useState<'none' | 'attention'>('none');
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);
  const toast = useToast();
  const { openConversation } = useConversation();
  const entryIds = useMemo(() => entries.map((entry) => String(entry.id)), [entries]);
  const { statuses: convStatuses } = useConversationStatus('time_entry', entryIds);
  const statusColors: Record<string, string> = { red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' };

  const getStatusKey = (status: string) => status.toLowerCase().replace(/[^a-z0-9]+/g, '-');

  const employeesById = useMemo(() => {
    const byId = new Map<number, Employee>();
    employees.forEach((employee) => {
      byId.set(employee.id, employee);
    });
    return byId;
  }, [employees]);

  const ticketsById = useMemo(() => {
    const byId = new Map<number, Ticket>();
    tickets.forEach((ticket) => {
      byId.set(ticket.id, ticket);
    });
    return byId;
  }, [tickets]);

  const customersById = useMemo(() => {
    const byId = new Map<number, Customer>();
    customers.forEach((customer) => {
      byId.set(customer.id, customer);
    });
    return byId;
  }, [customers]);

  const sitesById = useMemo(() => {
    const byId = new Map<number, Site>();
    sites.forEach((site) => {
      byId.set(site.id, site);
    });
    return byId;
  }, [sites]);

  const employeeFilterOptions = useMemo(() => {
    const uniqueEmployeeIds = Array.from(new Set(entries.map((entry) => entry.employeeId))).sort((a, b) => a - b);
    return uniqueEmployeeIds.map((employeeId) => {
      const employee = employeesById.get(employeeId);
      const composedName = [employee?.firstName, employee?.lastName].filter(Boolean).join(' ').trim();
      const name = employee?.displayName || composedName;
      return {
        value: `${employeeId}`,
        label: name ? name : `${employeeId}`,
      };
    });
  }, [entries, employeesById]);

  const summary = useMemo(() => {
    const entryCount = entries.length;
    const totalMinutes = entries.reduce((sum, entry) => sum + (entry.duration || 0), 0);
    const billableMinutes = entries.reduce((sum, entry) => sum + (entry.billable ? (entry.duration || 0) : 0), 0);
    const nonBillableMinutes = totalMinutes - billableMinutes;
    const billablePercent = totalMinutes > 0 ? Math.round((billableMinutes / totalMinutes) * 100) : 0;
    const readyToBillCount = entries.filter((entry) => entry.billingStatus === 'ready').length;
    const blockedCount = entries.filter((entry) => entry.billingStatus === 'blocked').length;
    const billedCount = entries.filter((entry) => entry.billingStatus === 'billed').length;
    const attentionCount = entries.filter((entry) => getAttentionSignals(entry).length > 0).length;
    return {
      entryCount,
      totalMinutes,
      billableMinutes,
      nonBillableMinutes,
      billablePercent,
      readyToBillCount,
      blockedCount,
      billedCount,
      attentionCount,
    };
  }, [entries]);

  const fetchEntries = async () => {
    try {
      setLoading(true);
      setError(null);
      const params = new URLSearchParams();
      if (employeeFilter) {
        params.append('employee_id', employeeFilter);
      }
      if (ticketFilter) {
        params.append('ticket_id', ticketFilter);
      }
      const query = params.toString();
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/time-entries${query ? `?${query}` : ''}`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch time entries');
      }

      const data = await response.json();
      setEntries(Array.isArray(data) ? data : []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchLookups = async () => {
    // Lookup enrichment is additive only; failures are intentionally non-blocking.
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const [employeesRes, ticketsRes, customersRes, sitesRes] = await Promise.allSettled([
        fetch(`${apiUrl}/employees`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/tickets`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/customers`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/sites`, { headers: { 'X-WP-Nonce': nonce } }),
      ]);

      if (employeesRes.status === 'fulfilled' && employeesRes.value.ok) {
        const employeeData = await employeesRes.value.json();
        setEmployees(Array.isArray(employeeData) ? employeeData : []);
      }

      if (ticketsRes.status === 'fulfilled' && ticketsRes.value.ok) {
        const ticketData = await ticketsRes.value.json();
        setTickets(Array.isArray(ticketData) ? ticketData : []);
      }

      if (customersRes.status === 'fulfilled' && customersRes.value.ok) {
        const customerData = await customersRes.value.json();
        setCustomers(Array.isArray(customerData) ? customerData : []);
      }

      if (sitesRes.status === 'fulfilled' && sitesRes.value.ok) {
        const siteData = await sitesRes.value.json();
        setSites(Array.isArray(siteData) ? siteData : []);
      }
    } catch (_) {
      // Preserve core list usability on enrichment failure.
    }
  };

  useEffect(() => {
    fetchEntries();
  }, [employeeFilter, ticketFilter]);

  useEffect(() => {
    fetchLookups();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingEntry(null);
    fetchEntries();
  };

  const handleEdit = (entry: TimeEntry) => {
    setEditingEntry(entry);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    setArchiveBusy(true);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/time-entries/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive time entry');
      }

      fetchEntries();
      setSelectedIds((prev) => prev.filter((sid) => sid !== id));
      toast.success('Time entry archived');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to archive');
    } finally {
      setArchiveBusy(false);
      setPendingArchiveId(null);
    }
  };

  const handleBulkArchive = async () => {
    setArchiveBusy(true);

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    try {
      const results = await Promise.allSettled(selectedIds.map((id) =>
        fetch(`${apiUrl}/time-entries/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        }).then((response) => {
          if (!response.ok) {
            throw new Error(`Failed to archive time entry ${id}`);
          }
        })
      ));
      const failedCount = results.filter((result) => result.status === 'rejected').length;
      const successCount = selectedIds.length - failedCount;
      setSelectedIds([]);
      fetchEntries();
      if (failedCount > 0) {
        toast.error(`Archived ${successCount} time entries; ${failedCount} failed.`);
      } else {
        toast.success(`Archived ${successCount} time entries.`);
      }
    } catch (e) {
      console.error('Failed to archive items', e);
      toast.error('Failed to archive selected time entries.');
    } finally {
      setArchiveBusy(false);
      setConfirmBulkArchive(false);
    }
  };

  const columns: Column<TimeEntry>[] = [
    {
      key: 'id',
      header: 'ID',
      render: (_, item) => {
        const cs = convStatuses.get(String(item.id));
        const dot = cs && cs.status !== 'none' ? (
          <button
            type="button"
            className="pet-time-entry-conversation-dot"
            title={`Conversation: ${cs.status} — click to open`}
            onClick={(event) => {
              event.stopPropagation();
              openConversation({
                contextType: 'time_entry',
                contextId: String(item.id),
                subject: `Time Entry #${item.id}`,
                subjectKey: `time_entry:${item.id}`,
              });
            }}
            aria-label={`Conversation: ${cs.status}`}
            style={{ background: statusColors[cs.status] || 'transparent' }}
          />
        ) : null;

        return (
          <span className="pet-time-entry-id-cell">
            {dot}
            {item.id}
          </span>
        );
      },
    },
    {
      key: 'employeeId',
      header: 'Employee',
      render: (_, entry) => {
        const employee = employeesById.get(entry.employeeId);
        const name = employee?.displayName || `${entry.employeeId}`;
        return (
          <span className="pet-time-entry-cell-identity">
            <span className="pet-time-entry-cell-identity-primary">{name}</span>
            <span className="pet-time-entry-cell-identity-secondary">Emp #{entry.employeeId}</span>
          </span>
        );
      },
    },
    {
      key: 'ticketId',
      header: 'Ticket',
      render: (_, entry) => {
        const ticket = ticketsById.get(entry.ticketId);
        const href = `/wp-admin/admin.php?page=pet-support#ticket=${entry.ticketId}`;
        if (!ticket) {
          return (
            <a
              className="pet-time-entry-ticket-link"
              href={href}
              aria-label={`View ticket ${entry.ticketId}`}
              title={`Open ticket #${entry.ticketId} in Support`}
            >
              {entry.ticketId}
            </a>
          );
        }
        return (
          <a
            className="pet-time-entry-ticket-link"
            href={href}
            aria-label={`View ticket ${ticket.id}`}
            title={`Open ticket #${ticket.id} in Support`}
          >
            <span className="pet-time-entry-cell-context">{`#${ticket.id} · ${ticket.subject || 'Untitled ticket'}`}</span>
          </a>
        );
      },
    },
    {
      id: 'customerSite',
      key: 'ticketId',
      header: 'Customer / Site',
      render: (_, entry) => {
        const ticket = ticketsById.get(entry.ticketId);
        if (!ticket) {
          return '—';
        }

        const customerId = ticket.customerId;
        const customerName = customersById.get(customerId)?.name || `Customer ${customerId}`;

        if (!ticket.siteId) {
          return <span className="pet-time-entry-cell-context">{customerName}</span>;
        }

        const siteName = sitesById.get(ticket.siteId)?.name || `Site ${ticket.siteId}`;
        return <span className="pet-time-entry-cell-context">{`${customerName} · ${siteName}`}</span>;
      },
    },
    {
      id: 'signalsAndTime',
      key: 'duration',
      header: 'Signals / Time',
      width: 270,
      render: (_, entry) => {
        const signals = getAttentionSignals(entry);
        const statusKey = getStatusKey(entry.status || 'unknown');
        const billingStatus = entry.billingStatus || 'unknown';
        const billingLabel = billingStatus === 'unknown' ? 'Unknown' : BILLING_STATUS_LABELS[billingStatus];
        const billingReason = billingStatus === 'blocked'
          ? (entry.billingBlockReason || 'Blocked for billing')
          : null;
        const billingTooltip = billingReason
          ? `Billing: ${billingLabel} — ${billingReason}`
          : `Billing: ${billingLabel}`;
        return (
          <div className="pet-time-entry-cell-signals-time">
            <div className="pet-time-entry-signal-cluster">
              <span
                className={entry.billable ? 'pet-time-entry-indicator pet-time-entry-indicator--billable' : 'pet-time-entry-indicator pet-time-entry-indicator--non-billable'}
                role="img"
                aria-label={entry.billable ? 'Billable' : 'Non-billable'}
                title={entry.billable ? 'Billable: Billable' : 'Billable: Non-billable'}
                data-tooltip={entry.billable ? 'Billable: Billable' : 'Billable: Non-billable'}
              >
                $
              </span>
              <span
                className={`pet-time-entry-indicator pet-time-entry-indicator--status status-${statusKey}`}
                role="img"
                aria-label={`Status: ${entry.status}`}
                title={`Status: ${entry.status}`}
                data-tooltip={`Status: ${entry.status}`}
              >
                ●
              </span>
              {entry.isCorrection || entry.correctsEntryId
                ? (
                  <span
                    className="pet-time-entry-indicator pet-time-entry-indicator--correction"
                    role="img"
                    aria-label="Correction entry"
                    title="Correction: Correction entry"
                    data-tooltip="Correction: Correction entry"
                  >
                    ↺
                  </span>
                )
                : (
                  <span
                    className="pet-time-entry-indicator pet-time-entry-indicator--original"
                    role="img"
                    aria-label="Original entry"
                    title="Correction: Original entry"
                    data-tooltip="Correction: Original entry"
                  >
                    •
                  </span>
                )}
              <span
                className={`pet-time-entry-billing-badge pet-time-entry-billing-badge--${billingStatus.replace('_', '-')}`}
                aria-label={`Billing status: ${billingStatus}`}
                title={billingTooltip}
              >
                Billing: {billingLabel}
              </span>
              {signals.length > 0 ? (
                <span className="pet-time-entry-attention-list" aria-label={`Attention signals: ${signals.map((s) => s.label).join(', ')}`}>
                  {signals.map((signal) => (
                    <span
                      key={`${entry.id}-${signal.key}`}
                      className={`pet-time-entry-attention-tag pet-time-entry-attention-tag--${signal.tone}`}
                      title={signal.title}
                    >
                      {signal.label}
                    </span>
                  ))}
                </span>
              ) : <span className="pet-time-entry-attention-empty">—</span>}
            </div>
            <div className="pet-time-entry-time-cluster">
              <span className="pet-time-entry-cell-duration">{formatMinutes(entry.duration || 0)}</span>
              <span className="pet-time-entry-cell-datetime">{formatDateTimeCompact(entry.start)} → {formatDateTimeCompact(entry.end)}</span>
            </div>
          </div>
        );
      },
    },
    { key: 'description', header: 'Description', render: (_, entry) => <span className="pet-time-entry-cell-description">{entry.description}</span> },
  ];

  return (
    <PageShell
      title="Time (Entries)"
      subtitle="Track and manage billable and non-billable technician effort."
      className="pet-time-entries"
      testId="time-entries-shell"
      actions={!showAddForm ? (
        <button className="button button-primary pet-time-entries-primary-action" onClick={() => setShowAddForm(true)}>
          Log Time Entry
        </button>
      ) : null}
    >
      <Panel className="pet-time-entries-context-panel" testId="time-entries-context-panel">
        <div className="pet-time-entries-context-grid">
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Entries</span>
            <strong className="pet-time-entries-context-value">{summary.entryCount}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Total Logged</span>
            <strong className="pet-time-entries-context-value">{formatMinutes(summary.totalMinutes)}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Billable</span>
            <strong className="pet-time-entries-context-value">
              {formatMinutes(summary.billableMinutes)} ({summary.billablePercent}%)
            </strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Non-billable</span>
            <strong className="pet-time-entries-context-value">{formatMinutes(summary.nonBillableMinutes)}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Needs Attention</span>
            <strong className="pet-time-entries-context-value">{summary.attentionCount}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Ready to Bill</span>
            <strong className="pet-time-entries-context-value">{summary.readyToBillCount}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Blocked</span>
            <strong className="pet-time-entries-context-value">{summary.blockedCount}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Billed</span>
            <strong className="pet-time-entries-context-value">{summary.billedCount}</strong>
          </div>
        </div>
      </Panel>

      <Panel className="pet-time-entries-filters-panel" testId="time-entries-filters-panel">
        <div className="pet-time-entries-filters-grid">
          <label className="pet-time-entries-filter-field" htmlFor="pet-time-filter-employee-id">
            <span>Employee</span>
            <select
              id="pet-time-filter-employee-id"
              value={employeeFilter}
              onChange={(event) => {
                setEmployeeFilter(event.target.value);
                setSelectedIds([]);
              }}
            >
              <option value="">All employees</option>
              {employeeFilterOptions.map((employeeOption) => (
                <option key={employeeOption.value} value={employeeOption.value}>
                  {employeeOption.label}
                </option>
              ))}
            </select>
          </label>
          <label className="pet-time-entries-filter-field" htmlFor="pet-time-filter-ticket-id">
            <span>Ticket ID</span>
            <input
              id="pet-time-filter-ticket-id"
              type="number"
              min="1"
              value={ticketFilter}
              onChange={(event) => {
                setTicketFilter(event.target.value.trim());
                setSelectedIds([]);
              }}
              placeholder="All tickets"
            />
          </label>
          <div className="pet-time-entries-filter-actions">
            <button
              type="button"
              className={`button pet-time-entries-clear-filters`}
              onClick={() => {
                setEmployeeFilter('');
                setTicketFilter('');
                setActivePreset('none');
                setSelectedIds([]);
              }}
              disabled={!employeeFilter && !ticketFilter}
            >
              Clear Filters
            </button>
          </div>
        </div>
        <div className="pet-time-entries-preset-bar" role="group" aria-label="Quick presets">
          <button
            type="button"
            className={`button pet-time-entries-preset-btn ${activePreset === 'attention' ? 'is-active' : ''}`}
            onClick={() => setActivePreset((current) => (current === 'attention' ? 'none' : 'attention'))}
            title="Highlights attention rows in the current dataset"
          >
            {activePreset === 'attention' ? 'Attention Highlight On' : 'Needs Attention'}
          </button>
        </div>
      </Panel>

      {showAddForm && (
        <Panel className="pet-time-entries-form-panel">
          <TimeEntryForm
            onSuccess={handleFormSuccess}
            onCancel={() => { setShowAddForm(false); setEditingEntry(null); }}
            initialData={editingEntry || undefined}
          />
        </Panel>
      )}

      {selectedIds.length > 0 && (
        <ActionBar className="pet-time-entries-bulk-strip" testId="time-entries-bulk-strip">
          <div className="pet-time-entries-bulk-text">
            <span className="pet-time-entries-bulk-eyebrow">Bulk actions</span>
            <strong>{selectedIds.length} items selected</strong>
          </div>
          <button className="button button-link-delete pet-action-danger" onClick={() => setConfirmBulkArchive(true)}>Archive Selected</button>
        </ActionBar>
      )}

      <Panel className="pet-time-entries-table-panel" testId="time-entries-main-panel">
        <div className="pet-time-entries-table-header">
          <h3>Entry List</h3>
          <p>Review, edit, and archive time entries from a single operational surface.</p>
        </div>
        <DataTable
          columns={columns}
          data={entries}
          loading={loading}
          error={error}
          onRetry={fetchEntries}
          emptyMessage="No time entries found."
          compatibilityMode="wp"
          rowClassName={(item) => (
            activePreset === 'attention' && getAttentionSignals(item).length > 0
              ? 'pet-time-entry-row--attention'
              : ''
          )}
          selection={{
            selectedIds,
            onSelectionChange: setSelectedIds,
          }}
          actions={(item) => (
            <KebabMenu items={[
              { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
              {
                type: 'action',
                label: 'Discuss',
                onClick: () => openConversation({
                  contextType: 'time_entry',
                  contextId: String(item.id),
                  subject: `Time Entry #${item.id}`,
                  subjectKey: `time_entry:${item.id}`,
                }),
              },
              { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
            ]}
            />
          )}
        />
      </Panel>

      <ConfirmationDialog
        open={pendingArchiveId !== null}
        title="Archive time entry?"
        description="This action will archive the selected time entry."
        confirmLabel="Archive"
        busy={archiveBusy}
        onCancel={() => setPendingArchiveId(null)}
        onConfirm={() => {
          if (pendingArchiveId !== null) {
            handleArchive(pendingArchiveId);
          }
        }}
      />

      <ConfirmationDialog
        open={confirmBulkArchive}
        title="Archive selected time entries?"
        description={`This action will archive ${selectedIds.length} selected time entries.`}
        confirmLabel="Archive selected"
        busy={archiveBusy}
        onCancel={() => setConfirmBulkArchive(false)}
        onConfirm={handleBulkArchive}
      />
    </PageShell>
  );
};

export default TimeEntries;
