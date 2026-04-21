import React, { useState, useEffect } from 'react';
import { Ticket, Customer, WorkItem, ActivityLog, Employee } from '../types';
import useConversation from '../hooks/useConversation';
import { computeTicketHealth } from '../healthCompute';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

interface TicketDetailsProps {
  ticket: Ticket;
  onBack: () => void;
}

const TicketDetails: React.FC<TicketDetailsProps> = ({ ticket, onBack }) => {
  const lifecycleOwner = ticket.lifecycleOwner || 'support';
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [loadingCustomer, setLoadingCustomer] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const { openConversation } = useConversation();
  const [status, setStatus] = useState(ticket.status);
  const [priority, setPriority] = useState(ticket.priority);
  const [saving, setSaving] = useState(false);
  const [workItem, setWorkItem] = useState<WorkItem | null>(null);
  const [loadingWorkItem, setLoadingWorkItem] = useState(false);
  const [assigning, setAssigning] = useState(false);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [selectedAssignee, setSelectedAssignee] = useState<string>('');
  const [updatingAssignment, setUpdatingAssignment] = useState(false);
  const [activityLogs, setActivityLogs] = useState<ActivityLog[]>([]);
  const [loadingActivity, setLoadingActivity] = useState(false);
  const [activityError, setActivityError] = useState<string | null>(null);
  const [statusOptions, setStatusOptions] = useState<Array<{ value: string; label: string }>>([]);

  useEffect(() => {
    const fetchCustomer = async () => {
      if (!ticket.customerId) return;

      try {
        setLoadingCustomer(true);
        const apiUrl = (window as any).petSettings?.apiUrl;
        const nonce = (window as any).petSettings?.nonce;
        if (!apiUrl || !nonce) {
          return;
        }
        const response = await fetch(
          `${apiUrl}/customers?id=${ticket.customerId}`,
          {
            headers: {
              'X-WP-Nonce': nonce,
            },
          }
        );

        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data) && data.length > 0) {
            setCustomer(
              data.find((c: Customer) => c.id === ticket.customerId) || null
            );
          }
        }
      } catch (err) {
        console.error('Failed to fetch customer details', err);
      } finally {
        setLoadingCustomer(false);
      }
    };

    fetchCustomer();
  }, [ticket.customerId]);

  useEffect(() => {
    const fetchWorkItem = async () => {
      try {
        setLoadingWorkItem(true);
        const apiUrl = (window as any).petSettings?.apiUrl;
        const nonce = (window as any).petSettings?.nonce;
        if (!apiUrl || !nonce) {
          return;
        }
        const response = await fetch(`${apiUrl}/work-items/by-source?source_type=ticket&source_id=${ticket.id}`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
        if (!response.ok) {
          return;
        }
        const data = await response.json();
        setWorkItem(data);
        if (data && data.assigned_user_id) {
          setSelectedAssignee(String(data.assigned_user_id));
        } else {
          setSelectedAssignee('');
        }
      } catch (err) {
        console.error('Failed to fetch work item', err);
      } finally {
        setLoadingWorkItem(false);
      }
    };

    fetchWorkItem();
  }, [ticket.id]);

  useEffect(() => {
    const fetchEmployees = async () => {
      try {
        const apiUrl = (window as any).petSettings?.apiUrl;
        const nonce = (window as any).petSettings?.nonce;
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
        console.error('Failed to fetch employees for ticket details', err);
      }
    };

    fetchEmployees();
  }, []);

  useEffect(() => {
    const fetchStatusOptions = async () => {
      try {
        const apiUrl = (window as any).petSettings?.apiUrl;
        const nonce = (window as any).petSettings?.nonce;
        if (!apiUrl || !nonce) {
          return;
        }
        const response = await fetch(
          `${apiUrl}/tickets/status-options?lifecycle_owner=${encodeURIComponent(lifecycleOwner)}`,
          {
            headers: {
              'X-WP-Nonce': nonce,
            },
          }
        );
        if (!response.ok) {
          return;
        }
        const data = await response.json();
        if (Array.isArray(data)) {
          setStatusOptions(
            data
              .filter((opt) => typeof opt?.value === 'string')
              .map((opt) => ({
                value: String(opt.value),
                label: typeof opt.label === 'string' ? opt.label : String(opt.value),
              }))
          );
        }
      } catch (err) {
        console.error('Failed to fetch ticket status options', err);
      }
    };

    fetchStatusOptions();
  }, [lifecycleOwner]);

  useEffect(() => {
    const fetchActivity = async () => {
      try {
        setLoadingActivity(true);
        setActivityError(null);
        const apiUrl = (window as any).petSettings?.apiUrl;
        const nonce = (window as any).petSettings?.nonce;
        if (!apiUrl || !nonce) {
          setLoadingActivity(false);
          return;
        }
        const response = await fetch(
          `${apiUrl}/activity?entity_type=ticket&entity_id=${ticket.id}`,
          {
            headers: {
              'X-WP-Nonce': nonce,
            },
          }
        );
        if (!response.ok) {
          throw new Error('Failed to fetch activity');
        }
        const data = await response.json();
        if (data && Array.isArray(data.items)) {
          setActivityLogs(data.items);
        } else if (Array.isArray(data)) {
          setActivityLogs(data);
        } else {
          setActivityLogs([]);
        }
      } catch (err) {
        setActivityError(
          err instanceof Error ? err.message : 'Failed to load activity'
        );
      } finally {
        setLoadingActivity(false);
      }
    };

    fetchActivity();
  }, [ticket.id]);

  const handleSave = async (overrides?: { status?: string; priority?: string }) => {
    try {
      setSaving(true);
      const apiUrl = (window as any).petSettings?.apiUrl;
      const nonce = (window as any).petSettings?.nonce;
      if (!apiUrl || !nonce) {
        throw new Error('API settings missing');
      }
      const response = await fetch(`${apiUrl}/tickets/${ticket.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          subject: ticket.subject,
          description: ticket.description,
          status: overrides?.status ?? status,
          priority: overrides?.priority ?? priority,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to update ticket');
      }

      setIsEditing(false);
    } catch (err) {
      legacyAlert(
        err instanceof Error ? err.message : 'Failed to update ticket'
      );
    } finally {
      setSaving(false);
    }
  };

  const handleReply = () => {
    openConversation({
      contextType: 'ticket',
      contextId: String(ticket.id),
      subject: `Reply: Ticket #${ticket.id}: ${ticket.subject}`,
      subjectKey: `ticket_reply:${ticket.id}`,
    });
  };

  const handleCloseTicket = async () => {
    const allowedStatuses = new Set(
      statusOptions.map((opt) => String(opt.value).toLowerCase())
    );
    const nextStatus = allowedStatuses.has('resolved')
      ? 'resolved'
      : allowedStatuses.has('closed')
      ? 'closed'
      : status;
    await handleSave({ status: nextStatus });
    setStatus(nextStatus);
  };

  const getSlaStatusColor = (status?: string) => {
    switch (status) {
      case 'breached': return '#dc3232'; // Red
      case 'warning': return '#dba617'; // Orange
      case 'achieved': return '#46b450'; // Green
      default: return '#72aee6'; // Blue
    }
  };

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString();
  };

  const formatMinutes = (minutes: number | null | undefined) => {
    if (minutes === null || minutes === undefined) {
      return 'N/A';
    }
    const negative = minutes < 0;
    const value = Math.abs(minutes);
    const hours = Math.floor(value / 60);
    const mins = value % 60;
    const base = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
    return negative ? `-${base}` : base;
  };

  const getSlaClockColor = (minutes: number | null | undefined) => {
    if (minutes === null || minutes === undefined) {
      return '#666';
    }
    if (minutes < 0) {
      return '#dc3232';
    }
    if (minutes < 60) {
      return '#dba617';
    }
    return '#46b450';
  };


  const formatStatusLabel = (s: string) => {
    return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  };

  const getDepartmentLabel = (id?: string | null) => {
    if (!id) return 'Unknown';
    if (id === 'dept_support') return 'Support';
    if (id === 'dept_delivery') return 'Delivery';
    if (id === 'dept_sales') return 'Sales';
    if (id === 'dept_admin') return 'Admin';
    return id;
  };

  const getAssigneeLabel = () => {
    if (!workItem || !workItem.assigned_user_id) {
      return 'Unassigned';
    }
    const match = employees.find(
      (e) => String(e.wpUserId) === String(workItem.assigned_user_id)
    );
    if (match) {
      return `${match.firstName} ${match.lastName}`;
    }
    const currentUserId = (window as any).petSettings?.currentUserId;
    if (
      currentUserId &&
      String(workItem.assigned_user_id) === String(currentUserId)
    ) {
      return 'You';
    }
    return `User #${workItem.assigned_user_id}`;
  };

  const handleAssignToMe = async () => {
    if (!workItem) return;
    const currentUserId = (window as any).petSettings?.currentUserId;
    if (!currentUserId) return;
    if (!legacyConfirm('Assign this ticket to yourself?')) return;

    try {
      setAssigning(true);
      const apiUrl = (window as any).petSettings?.apiUrl;
      const nonce = (window as any).petSettings?.nonce;
      if (!apiUrl || !nonce) {
        return;
      }
      const response = await fetch(`${apiUrl}/work-items/${workItem.id}/assign`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          assigned_user_id: currentUserId,
        }),
      });
      if (!response.ok) {
        throw new Error('Failed to assign work item');
      }
      setWorkItem({
        ...workItem,
        assigned_user_id: String(currentUserId),
      });
      setSelectedAssignee(String(currentUserId));
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Failed to assign ticket');
    } finally {
      setAssigning(false);
    }
  };

  const handleAssignmentUpdate = async () => {
    if (!workItem || !selectedAssignee) {
      return;
    }

    try {
      setUpdatingAssignment(true);
      const apiUrl = (window as any).petSettings?.apiUrl;
      const nonce = (window as any).petSettings?.nonce;
      if (!apiUrl || !nonce) {
        return;
      }
      const response = await fetch(
        `${apiUrl}/work-items/${workItem.id}/assign`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
          body: JSON.stringify({
            assigned_user_id: selectedAssignee,
          }),
        }
      );
      if (!response.ok) {
        throw new Error('Failed to update assignment');
      }
      setWorkItem({
        ...workItem,
        assigned_user_id: String(selectedAssignee),
      });
    } catch (err) {
      legacyAlert(
        err instanceof Error ? err.message : 'Failed to update assignment'
      );
    } finally {
      setUpdatingAssignment(false);
    }
  };

  // Compute UHB health for this ticket
  const ticketHealth = computeTicketHealth(
    { status },
    workItem?.sla_time_remaining ?? null,
  );

  return (
    <div className={`pet-ticket-details ${ticketHealth.className}`}>
      <div style={{ marginBottom: '20px' }}>
        <button 
          type="button" 
          className="button" 
          onClick={() => {
            try {
              window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
            } catch (_) {}
            onBack();
          }}
        >
          &larr; Back to Tickets
        </button>
        {!isEditing && (
          <>
            <button type="button" className="button" onClick={() => setIsEditing(true)} style={{ marginLeft: '10px' }}>Edit</button>
            <button 
              type="button"
              className="button" 
              onClick={() => openConversation({
                contextType: 'ticket',
                contextId: String(ticket.id),
                subject: `Ticket #${ticket.id}: ${ticket.subject}`,
                subjectKey: `ticket:${ticket.id}`,
              })}
              style={{ marginLeft: '10px' }}
            >
              Discuss
            </button>
            {ticket.slaId && (
              <button 
                type="button"
                className="button" 
                onClick={() => openConversation({
                  contextType: 'ticket',
                  contextId: String(ticket.id),
                  subject: `SLA Discussion: Ticket #${ticket.id}`,
                  subjectKey: `ticket_sla:${ticket.id}`,
                })}
                style={{ marginLeft: '10px' }}
              >
                SLA Discussion
              </button>
            )}
          </>
        )}
        {isEditing && (
          <>
            <button type="button" className="button button-primary" onClick={() => { void handleSave(); }} disabled={saving} style={{ marginLeft: '10px' }}>
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
            <button type="button" className="button" onClick={() => setIsEditing(false)} disabled={saving} style={{ marginLeft: '10px' }}>Cancel</button>
          </>
        )}
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
        <div>
          <h2 style={{ marginTop: 0 }}>
            {ticket.subject}
            {ticketHealth.reasons.map((r, i) => (
              <span key={i} className={`uhb-tag uhb-tag-${r.color}`}>{r.label}</span>
            ))}
          </h2>
          <div style={{ color: '#666', fontSize: '1.1em', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
            #{ticket.id} &bull; {ticket.createdAt}
            {ticket.intake_source === 'pulseway' && (
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', background: '#e8f4fd', color: '#0073aa', padding: '2px 10px', borderRadius: '10px', fontSize: '0.85em', fontWeight: 600 }}>
                {'\u{1F5A5}\uFE0F'} Pulseway RMM
              </span>
            )}
          </div>
          {ticket.malleableData && ticket.malleableData.source === 'quote' && (
            <div style={{ marginTop: '6px', fontSize: '0.95em', color: '#555' }}>
              From Quote #{ticket.malleableData.quote_id}
              {ticket.malleableData.quote_phase_name ? ` – ${ticket.malleableData.quote_phase_name}` : ''}
            </div>
          )}
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ marginBottom: '5px' }}>
            <strong>Status:</strong>{' '}
            {isEditing ? (
              <select value={status} onChange={(e) => setStatus(e.target.value)}>
                {(statusOptions.length > 0 ? statusOptions : [{ value: status, label: formatStatusLabel(status) }]).map(opt => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            ) : (
              <span className={`pet-status-badge status-${status}`}>
                {formatStatusLabel(status)}
              </span>
            )}
          </div>
          <div>
            <strong>Priority:</strong>{' '}
            {isEditing ? (
              <select value={priority} onChange={(e) => setPriority(e.target.value)}>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            ) : (
              <span className={`pet-priority-badge priority-${priority}`}>{priority}</span>
            )}
          </div>
        </div>
      </div>



      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '30px' }}>
          <div className="pet-ticket-main">
            <div
              className="pet-box"
              style={{
                background: '#fff',
                padding: '20px',
                border: '1px solid #ccd0d4',
                marginBottom: '20px',
              }}
            >
              <h3 style={{ marginTop: 0 }}>Description</h3>
              <div style={{ whiteSpace: 'pre-wrap', lineHeight: '1.5' }}>
                {ticket.description}
              </div>
            </div>

            <div
              className="pet-box"
              style={{
                background: '#fff',
                padding: '20px',
                border: '1px solid #ccd0d4',
              }}
            >
              <h3 style={{ marginTop: 0 }}>Activity & Comments</h3>
              {loadingActivity ? (
                <p>Loading activity...</p>
              ) : activityError ? (
                <p style={{ color: '#c00' }}>{activityError}</p>
              ) : activityLogs.length === 0 ? (
                <p style={{ color: '#666', fontStyle: 'italic' }}>
                  No activity recorded yet.
                </p>
              ) : (
                <ul
                  style={{
                    listStyle: 'none',
                    padding: 0,
                    margin: 0,
                  }}
                >
                  {activityLogs.map((log) => (
                    <li key={log.id} style={{ marginBottom: '8px' }}>
                      <div style={{ fontSize: '12px', color: '#666' }}>{log.occurred_at}</div>
                      <div>{log.headline}</div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>

          <div className="pet-ticket-sidebar">
          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4', marginBottom: '20px' }}>
            <h3 style={{ marginTop: 0 }}>SLA Status</h3>
            {ticket.slaId ? (
              <div>
                <div style={{ marginBottom: '10px' }}>
                  <strong>Status: </strong>
                  <span style={{ 
                    fontWeight: 'bold', 
                    color: getSlaStatusColor(ticket.sla_status),
                    textTransform: 'uppercase'
                  }}>
                    {ticket.sla_status || 'Pending'}
                  </span>
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <strong>Response Due:</strong><br/>
                  {formatDate(ticket.response_due_at)}
                </div>
                <div>
                  <strong>Resolution Due:</strong><br/>
                  {formatDate(ticket.resolution_due_at)}
                </div>
              </div>
            ) : (
              <p style={{ fontStyle: 'italic', color: '#666' }}>No SLA assigned to this ticket.</p>
            )}
          </div>

          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4', marginBottom: '20px' }}>
            <h3 style={{ marginTop: 0 }}>Customer Details</h3>
            {loadingCustomer ? (
              <p>Loading...</p>
            ) : customer ? (
              <div>
                <p><strong>Name:</strong> {customer.name}</p>
                <p><strong>Email:</strong> <a href={`mailto:${customer.contactEmail}`}>{customer.contactEmail}</a></p>
              </div>
            ) : (
              <p>Customer ID: {ticket.customerId}</p>
            )}
          </div>

          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4' }}>
            <h3 style={{ marginTop: 0 }}>Ownership & Actions</h3>
            {loadingWorkItem && <p>Loading work item...</p>}
            {workItem && (
              <>
                <p>
                  <strong>Department:</strong>{' '}
                  {getDepartmentLabel(workItem.department_id)}
                </p>
                <p>
                  <strong>Assignment:</strong> {getAssigneeLabel()}
                </p>
                {employees.length > 0 && (
                  <div style={{ marginBottom: '10px' }}>
                    <label style={{ display: 'block', marginBottom: '4px' }}>
                      Change assignment:
                    </label>
                    <select
                      value={selectedAssignee}
                      onChange={(e) => setSelectedAssignee(e.target.value)}
                      style={{ width: '100%' }}
                    >
                      <option value="">Select person</option>
                      {employees
                        .filter((e) => e.status !== 'archived')
                        .map((e) => (
                          <option key={e.wpUserId} value={e.wpUserId}>
                            {e.firstName} {e.lastName}
                          </option>
                        ))}
                    </select>
                    <button
                      type="button"
                      className="button"
                      style={{ marginTop: '6px', width: '100%' }}
                      disabled={updatingAssignment || !selectedAssignee}
                      onClick={handleAssignmentUpdate}
                    >
                      {updatingAssignment ? 'Updating...' : 'Update assignment'}
                    </button>
                  </div>
                )}
                <p>
                  <strong>SLA Clock:</strong>{' '}
                  <span
                    style={{ color: getSlaClockColor(workItem.sla_time_remaining) }}
                  >
                    {formatMinutes(workItem.sla_time_remaining)}
                  </span>
                </p>
                {!workItem.assigned_user_id &&
                  (window as any).petSettings?.currentUserId && (
                    <button
                      type="button"
                      className="button button-large button-primary"
                      onClick={handleAssignToMe}
                      disabled={assigning}
                      style={{ width: '100%', marginBottom: '10px' }}
                    >
                      {assigning ? 'Assigning...' : 'Pick Up Ticket'}
                    </button>
                  )}
              </>
            )}
            {ticket.intake_source === 'pulseway' && (
              <div style={{ marginBottom: '10px' }}>
                <button
                  type="button"
                  className="button button-large"
                  disabled
                  style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px', background: '#0073aa', color: '#fff', borderColor: '#0073aa', opacity: 0.6, cursor: 'not-allowed' }}
                  title="Remote Connect will be available when Pulseway agent access is configured"
                >
                  {'\u{1F517}'} Remote Connect
                </button>
                <div style={{ fontSize: '0.78em', color: '#888', marginTop: '4px', textAlign: 'center' }}>Requires Pulseway agent access</div>
              </div>
            )}
            <button
              type="button"
              className="button button-large"
              style={{ width: '100%', marginBottom: '10px' }}
              onClick={handleReply}
            >
              Reply
            </button>
            <button
              type="button"
              className="button button-large"
              style={{ width: '100%' }}
              onClick={handleCloseTicket}
              disabled={saving}
            >
              Close Ticket
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TicketDetails;
