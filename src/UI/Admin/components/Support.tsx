import React, { useEffect, useState, useMemo } from 'react';
import { Ticket, Employee } from '../types';
import { DataTable, Column } from './DataTable';
import TicketForm from './TicketForm';
import TicketDetails from './TicketDetails';
import { computeTicketHealth } from '../healthCompute';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import useConversation from '../hooks/useConversation';
import useConversationStatus from '../hooks/useConversationStatus';
import SupportOperational from './SupportOperational';
import { legacyAlert, legacyConfirm } from './legacyDialogs';
export interface StatusOption {
  value: string;
  label: string;
}
export const buildSupportStatusOptions = (optionsMap: Map<string, StatusOption>): StatusOption[] =>
  Array.from(optionsMap.values());

const Support = () => {
  // @ts-ignore
  const supportOperationalEnabled = Boolean(window.petSettings?.featureFlags?.support_operational_improvements_enabled);
  if (supportOperationalEnabled) {
    return <SupportOperational />;
  }

  const { openConversation } = useConversation();
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingTicket, setEditingTicket] = useState<Ticket | null>(null);
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [lifecycleFilter, setLifecycleFilter] = useState<string>('');
  const [assignmentFilter, setAssignmentFilter] = useState<string>('all');
  const [customerFilter, setCustomerFilter] = useState<string>('');
  const [statusOptions, setStatusOptions] = useState<StatusOption[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [pendingSelectId, setPendingSelectId] = useState<number | null>(null);

  const ticketIds = useMemo(() => tickets.map(t => String(t.id)), [tickets]);
  const { statuses: convStatuses } = useConversationStatus('ticket', ticketIds);

  useEffect(() => {
    // Restore selection from URL hash (e.g., #ticket=123)
    try {
      const hash = window.location.hash || '';
      const m = hash.match(/ticket=(\d+)/);
      if (m) {
        setPendingSelectId(Number(m[1]));
      }
    } catch (_) {}
  }, []);

  useEffect(() => {
    if (pendingSelectId && tickets.length > 0) {
      const found = tickets.find(t => Number(t.id) === Number(pendingSelectId));
      if (found) {
        setSelectedTicket(found);
        setPendingSelectId(null);
      }
    }
  }, [pendingSelectId, tickets]);

  useEffect(() => {
    const fetchEmployees = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        if (!apiUrl || !nonce) {
          return;
        }

        const response = await fetch(`${apiUrl}/employees`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          return;
        }

        const data = await response.json();
        setEmployees(data);
      } catch (err) {
        console.error('Failed to fetch employees for Support list', err);
      }
    };

    fetchEmployees();
  }, []);

  useEffect(() => {
    const fetchStatusOptions = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;
        if (!apiUrl || !nonce) {
          return;
        }

        const lifecycles = lifecycleFilter ? [lifecycleFilter] : ['support', 'project', 'internal'];
        const responses = await Promise.all(
          lifecycles.map((owner) =>
            fetch(`${apiUrl}/tickets/status-options?lifecycle_owner=${encodeURIComponent(owner)}`, {
              headers: { 'X-WP-Nonce': nonce },
            })
          )
        );

        const optionsMap = new Map<string, StatusOption>();
        for (const res of responses) {
          if (!res.ok) {
            continue;
          }
          const data = await res.json();
          if (!Array.isArray(data)) {
            continue;
          }
          for (const option of data) {
            if (typeof option?.value !== 'string') {
              continue;
            }
            optionsMap.set(option.value, {
              value: option.value,
              label: typeof option?.label === 'string' ? option.label : option.value,
            });
          }
        }

        const options = buildSupportStatusOptions(optionsMap);
        setStatusOptions(options);
        if (statusFilter && !options.some((option) => option.value === statusFilter)) {
          setStatusFilter('');
        }
      } catch (err) {
        console.error('Failed to fetch support status options', err);
      }
    };

    fetchStatusOptions();
  }, [lifecycleFilter, statusFilter]);

  const fetchTickets = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      if (!apiUrl || !nonce) {
        setError('API settings missing');
        setTickets([]);
        return;
      }

      const params = new URLSearchParams();
      if (statusFilter) {
        params.append('status', statusFilter);
      }
      if (lifecycleFilter) {
        params.append('lifecycle_owner', lifecycleFilter);
      }
      if (customerFilter) {
        params.append('customer_id', customerFilter);
      }
      if (assignmentFilter === 'unassigned') {
        params.append('unassigned', '1');
      }
      if (assignmentFilter === 'mine') {
        // @ts-ignore
        const currentUserId = window.petSettings?.currentUserId;
        if (currentUserId) {
          params.append('assigned_user_id', String(currentUserId));
        }
      }
      if (assignmentFilter === 'assigned') {
        params.append('assigned', '1');
      }

      const query = params.toString();
      const response = await fetch(`${apiUrl}/tickets${query ? `?${query}` : ''}`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        if (response.status === 404) {
          setError('Helpdesk is disabled (feature flag off)');
          setTickets([]);
          return;
        }
        throw new Error('Failed to fetch tickets');
      }

      const data: Ticket[] = await response.json();

      setTickets(data);
      // If we have a pending selection and it exists in the new data, select it
      if (pendingSelectId) {
        const found = data.find(t => Number(t.id) === Number(pendingSelectId));
        if (found) {
          setSelectedTicket(found);
          setPendingSelectId(null);
        }
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, [statusFilter, lifecycleFilter, assignmentFilter, customerFilter]);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingTicket(null);
    fetchTickets();
  };

  const handleEdit = (ticket: Ticket) => {
    setEditingTicket(ticket);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!legacyConfirm('Are you sure you want to archive this ticket?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/tickets/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive ticket');
      }

      fetchTickets();
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!legacyConfirm(`Are you sure you want to archive ${selectedIds.length} tickets?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/tickets/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchTickets();
  };

  const statusColors: Record<string, string> = { red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' };

  const columns: Column<Ticket>[] = [
    { key: 'id', header: 'ID' },
    { key: 'subject', header: 'Subject', render: (val, item) => {
      const cs = convStatuses.get(String(item.id));
      const dot = cs && cs.status !== 'none' ? (
        <button
          type="button"
          title={`Conversation: ${cs.status} — click to open`}
          onClick={(e) => { e.stopPropagation(); openConversation({ contextType: 'ticket', contextId: String(item.id), subject: `Ticket #${item.id}: ${item.subject}`, subjectKey: `ticket:${item.id}` }); }}
          style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: statusColors[cs.status] || 'transparent', marginRight: 6, verticalAlign: 'middle', border: 'none', padding: 0, cursor: 'pointer', flexShrink: 0 }}
        />
      ) : null;
      return (<>
        {dot}
        <button 
        type="button"
        onClick={(e) => { 
          e.preventDefault(); 
          e.stopPropagation();
          setSelectedTicket(item); 
          try {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#ticket=${item.id}`);
          } catch (_) {}
        }}
        style={{ fontWeight: 'bold', background: 'none', border: 'none', padding: 0, margin: 0, color: '#2271b1', cursor: 'pointer' }}
        className="button-link"
        aria-label={`View ticket ${String(val)}`}
      >
        {String(val)}
      </button>
      </>);
    } },
    { 
      key: 'malleableData', 
      header: 'Source', 
      render: (_val, item) => {
        if (item.intake_source === 'pulseway') {
          return (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', background: '#e8f4fd', color: '#0073aa', padding: '2px 8px', borderRadius: '10px', fontSize: '0.85em', fontWeight: 600 }}>
              <span style={{ fontSize: '1em' }}>{'\u{1F5A5}\uFE0F'}</span> Pulseway RMM
            </span>
          );
        }
        const data = item.malleableData || {};
        if (data.source === 'quote') {
          const quoteId = data.quote_id;
          const phaseName = data.quote_phase_name;
          if (quoteId && phaseName) {
            return `Quote #${quoteId} – ${phaseName}`;
          }
          if (quoteId) {
            return `Quote #${quoteId}`;
          }
        }
        return '-';
      } 
    },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'lifecycleOwner', header: 'Lifecycle' },
    { 
      key: 'assignedUserId', 
      header: 'Assigned To',
      render: (val) => {
        if (!val) {
          return '-';
        }
        const match = employees.find(e => String(e.wpUserId) === String(val));
        if (match) {
          return `${match.firstName} ${match.lastName}`;
        }
        return String(val);
      }
    },
    { key: 'priority', header: 'Priority', render: (val) => <span className={`pet-priority-badge priority-${val}`}>{String(val)}</span> },
    { key: 'status', header: 'Status', render: (val) => <span className={`pet-status-badge status-${val}`}>{String(val)}</span> },
    { key: 'createdAt', header: 'Created' },
  ];

  if (selectedTicket) {
    return (
      <TicketDetails 
        ticket={selectedTicket} 
        onBack={() => {
          setSelectedTicket(null);
          fetchTickets();
        }} 
      />
    );
  }

  if (loading && !tickets.length) return <div>Loading tickets...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-support">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Support (Tickets)</h2>
        {!showAddForm && (
          <button type="button" className="button button-primary" onClick={() => setShowAddForm(true)}>
            Create New Ticket
          </button>
        )}
      </div>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginBottom: '15px' }}>
        <div>
          <label style={{ display: 'block', marginBottom: '4px' }}>Status</label>
          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
            <option value="">All</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label style={{ display: 'block', marginBottom: '4px' }}>Lifecycle</label>
          <select value={lifecycleFilter} onChange={(e) => setLifecycleFilter(e.target.value)}>
            <option value="">All</option>
            <option value="support">Support</option>
            <option value="project">Project</option>
            <option value="internal">Internal</option>
          </select>
        </div>
        <div>
          <label style={{ display: 'block', marginBottom: '4px' }}>Assignment</label>
          <select value={assignmentFilter} onChange={(e) => setAssignmentFilter(e.target.value)}>
            <option value="all">All</option>
            <option value="unassigned">Unassigned</option>
            <option value="assigned">Assigned (any)</option>
            <option value="mine">Assigned to me</option>
          </select>
        </div>
        <div>
          <label style={{ display: 'block', marginBottom: '4px' }}>Customer ID</label>
          <input
            type="number"
            value={customerFilter}
            onChange={(e) => setCustomerFilter(e.target.value)}
            style={{ width: '120px' }}
          />
        </div>
        <div style={{ alignSelf: 'flex-end' }}>
          <button
            type="button"
            className="button"
            onClick={() => {
              setStatusFilter('');
              setLifecycleFilter('');
              setAssignmentFilter('all');
              setCustomerFilter('');
            }}
          >
            Clear filters
          </button>
        </div>
      </div>

      {showAddForm && (
        <TicketForm 
          onSuccess={handleFormSuccess} 
          onCancel={() => { setShowAddForm(false); setEditingTicket(null); }} 
          initialData={editingTicket || undefined}
        />
      )}

      {selectedIds.length > 0 && (
        <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
          <strong>{selectedIds.length} items selected</strong>
          <button type="button" className="button" onClick={handleBulkArchive}>Archive Selected</button>
        </div>
      )}

      <DataTable 
        columns={columns} 
        data={tickets} 
        emptyMessage="No tickets found." 
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        rowClassName={(ticket) => {
          // Map sla_status → approximate SLA minutes for health compute
          const slaMinutes = ticket.sla_status === 'breached' ? -1
            : ticket.sla_status === 'warning' ? 30
            : ticket.sla_status === 'achieved' ? null
            : 120; // default healthy
          return computeTicketHealth(ticket, slaMinutes).className;
        }}
        actions={(item) => (
          <KebabMenu items={[
            { type: 'action', label: 'Discuss', onClick: () => {
              openConversation({
                contextType: 'ticket',
                contextId: String(item.id),
                subject: `Ticket #${item.id}: ${item.subject}`,
                subjectKey: `ticket:${item.id}`,
              });
            }},
            { type: 'action', label: 'View', onClick: () => {
              setSelectedTicket(item);
              try { window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#ticket=${item.id}`); } catch (_) {}
            }},
            { type: 'action', label: 'Edit', onClick: () => {
              try { window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#ticket=${item.id}`); } catch (_) {}
              handleEdit(item);
            }},
            { type: 'action', label: 'Archive', onClick: () => handleArchive(item.id), danger: true },
          ]} />
        )}
        rowDetails={(item: Ticket) => (
          <div>
            <strong>Malleable data</strong>
            <pre style={{ marginTop: '8px', whiteSpace: 'pre-wrap' }}>
              {item.malleableData ? JSON.stringify(item.malleableData, null, 2) : 'None'}
            </pre>
          </div>
        )}
      />
    </div>
  );
};

export default Support;
