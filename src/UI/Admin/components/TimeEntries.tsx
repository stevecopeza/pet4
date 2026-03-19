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
    const distinctStaff = new Set(entries.map((entry) => entry.employeeId)).size;
    const correctionCount = entries.filter((entry) => entry.isCorrection || entry.correctsEntryId).length;
    return {
      entryCount,
      totalMinutes,
      billableMinutes,
      nonBillableMinutes,
      billablePercent,
      distinctStaff,
      correctionCount,
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
        return employee?.displayName || `${entry.employeeId}`;
      },
    },
    {
      key: 'ticketId',
      header: 'Ticket',
      render: (_, entry) => {
        const ticket = ticketsById.get(entry.ticketId);
        if (!ticket) {
          return `${entry.ticketId}`;
        }
        return `#${ticket.id} · ${ticket.subject || 'Untitled ticket'}`;
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
          return customerName;
        }

        const siteName = sitesById.get(ticket.siteId)?.name || `Site ${ticket.siteId}`;
        return `${customerName} · ${siteName}`;
      },
    },
    { key: 'start', header: 'Start', width: 120, render: (val) => formatDateTimeCompact(val as string) },
    { key: 'end', header: 'End', width: 120, render: (val) => formatDateTimeCompact(val as string) },
    { key: 'duration', header: 'Duration', render: (_, entry) => formatMinutes(entry.duration || 0) },
    { key: 'description', header: 'Description' },
    {
      key: 'billable',
      header: '',
      width: 24,
      render: (_, item) => (
        <span
          className={item.billable ? 'pet-time-entry-indicator pet-time-entry-indicator--billable' : 'pet-time-entry-indicator pet-time-entry-indicator--non-billable'}
          role="img"
          aria-label={item.billable ? 'Billable' : 'Non-billable'}
          title={item.billable ? 'Billable: Billable' : 'Billable: Non-billable'}
          data-tooltip={item.billable ? 'Billable: Billable' : 'Billable: Non-billable'}
        >
          $
        </span>
      ),
    },
    {
      key: 'status',
      header: '',
      width: 24,
      render: (_, item) => (
        <span
          className={`pet-time-entry-indicator pet-time-entry-indicator--status status-${getStatusKey(item.status || 'unknown')}`}
          role="img"
          aria-label={`Status: ${item.status}`}
          title={`Status: ${item.status}`}
          data-tooltip={`Status: ${item.status}`}
        >
          ●
        </span>
      ),
    },
    {
      key: 'correctsEntryId',
      header: '',
      width: 24,
      render: (_, item) => (
        item.isCorrection || item.correctsEntryId
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
          )
      ),
    },
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
            <span className="pet-time-entries-context-label">Distinct Staff</span>
            <strong className="pet-time-entries-context-value">{summary.distinctStaff}</strong>
          </div>
          <div className="pet-time-entries-context-item">
            <span className="pet-time-entries-context-label">Corrections</span>
            <strong className="pet-time-entries-context-value">{summary.correctionCount}</strong>
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
              className="button"
              onClick={() => {
                setEmployeeFilter('');
                setTicketFilter('');
                setSelectedIds([]);
              }}
              disabled={!employeeFilter && !ticketFilter}
            >
              Clear Filters
            </button>
          </div>
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
