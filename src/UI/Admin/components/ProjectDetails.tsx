import React, { useEffect, useMemo, useState } from 'react';
import { Customer, Employee, Project, Ticket } from '../types';
import { DataTable, Column } from './DataTable';
import useConversation from '../hooks/useConversation';
import { computeProjectHealth } from '../healthCompute';

interface ProjectDetailsProps {
  projectId: number;
  onBack?: () => void;
  embedded?: boolean;
}

type ProjectTicketRow = Ticket & { depth: number };

const ProjectDetails: React.FC<ProjectDetailsProps> = ({ projectId, onBack, embedded = false }) => {
  const [project, setProject] = useState<Project | null>(null);
  const [projectTickets, setProjectTickets] = useState<Ticket[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { openConversation } = useConversation();
  const customerNameById = useMemo(() => {
    const map = new Map<number, string>();
    for (const customer of customers) {
      map.set(Number(customer.id), customer.name);
    }
    return map;
  }, [customers]);
  const employeeNameById = useMemo(() => {
    const map = new Map<number, string>();
    for (const employee of employees) {
      const fullName = `${employee.firstName} ${employee.lastName}`.trim();
      map.set(Number(employee.wpUserId), fullName || employee.displayName || `User #${employee.wpUserId}`);
    }
    return map;
  }, [employees]);

  const orderedTicketRows = useMemo<ProjectTicketRow[]>(() => {
    if (projectTickets.length === 0) return [];
    const byParent = new Map<number, Ticket[]>();
    const byId = new Map<number, Ticket>();
    projectTickets.forEach((ticket) => byId.set(Number(ticket.id), ticket));

    for (const ticket of projectTickets) {
      const parentId = Number(ticket.parentTicketId || 0);
      if (parentId > 0 && byId.has(parentId)) {
        const children = byParent.get(parentId) || [];
        children.push(ticket);
        byParent.set(parentId, children);
      }
    }

    const roots = projectTickets
      .filter((ticket) => {
        const parentId = Number(ticket.parentTicketId || 0);
        return !(parentId > 0 && byId.has(parentId));
      })
      .sort((a, b) => Number(Boolean(b.isRollup)) - Number(Boolean(a.isRollup)) || Number(a.id) - Number(b.id));

    const result: ProjectTicketRow[] = [];
    const visit = (ticket: Ticket, depth: number) => {
      result.push({ ...ticket, depth });
      const children = (byParent.get(Number(ticket.id)) || [])
        .sort((a, b) => Number(Boolean(b.isRollup)) - Number(Boolean(a.isRollup)) || Number(a.id) - Number(b.id));
      children.forEach((child) => visit(child, depth + 1));
    };
    roots.forEach((root) => visit(root, 0));
    return result;
  }, [projectTickets]);

  const fetchProject = async () => {
    setLoading(true);
    setError(null);
    try {
      const nonce = window.petSettings.nonce;
      const apiUrl = window.petSettings.apiUrl;
      const [projectRes, ticketsRes, customersRes, employeesRes] = await Promise.all([
        fetch(`${apiUrl}/projects/${projectId}`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/tickets?project_id=${projectId}`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/customers`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/employees`, { headers: { 'X-WP-Nonce': nonce } }),
      ]);

      if (!projectRes.ok) {
        throw new Error('Failed to fetch project details');
      }

      const projectData = await projectRes.json();
      setProject(projectData);

      if (ticketsRes.ok) {
        const ticketData = await ticketsRes.json();
        setProjectTickets(Array.isArray(ticketData) ? ticketData : []);
      } else {
        setProjectTickets([]);
      }

      if (customersRes.ok) {
        const customerData = await customersRes.json();
        setCustomers(Array.isArray(customerData) ? customerData : []);
      } else {
        setCustomers([]);
      }

      if (employeesRes.ok) {
        const employeeData = await employeesRes.json();
        setEmployees(Array.isArray(employeeData) ? employeeData : []);
      } else {
        setEmployees([]);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
      setProjectTickets([]);
      setEmployees([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProject();
  }, [projectId]);
  useEffect(() => {
    setSelectedTicketId(null);
  }, [projectId]);
  useEffect(() => {
    if (!selectedTicketId) return;
    if (!projectTickets.some((ticket) => Number(ticket.id) === selectedTicketId)) {
      setSelectedTicketId(null);
    }
  }, [projectTickets, selectedTicketId]);
  useEffect(() => {
    if (!selectedTicketId) return;
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setSelectedTicketId(null);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [selectedTicketId]);

  const ticketColumns: Column<ProjectTicketRow>[] = [
    {
      key: 'subject',
      header: 'Ticket',
      render: (_, item) => (
        <div style={{ paddingLeft: `${item.depth * 20}px`, display: 'flex', alignItems: 'center', gap: 6 }}>
          {item.isRollup ? <span title="Rollup">▾</span> : item.depth > 0 ? <span title="Child ticket">└</span> : null}
          <span>{item.subject}</span>
        </div>
      ),
    },
    { key: 'ticketKind', header: 'Kind', render: (val) => val ? String(val) : '—' },
    { key: 'status', header: 'Status', render: (val) => <span className={`pet-status-badge status-${String(val).toLowerCase()}`}>{String(val)}</span> },
    { key: 'priority', header: 'Priority', render: (val) => <span className={`pet-priority-badge priority-${String(val).toLowerCase()}`}>{String(val)}</span> },
    {
      key: 'estimatedMinutes',
      header: 'Estimate',
      render: (_, item) => {
        const minutes = Number(item.estimatedMinutes ?? item.soldMinutes ?? 0);
        if (!minutes || Number.isNaN(minutes)) return '—';
        return `${(minutes / 60).toFixed(1)}h`;
      },
    },
    { key: 'id', header: 'Ref', render: (val) => `#${val}` },
  ];

  if (loading) return <div>Loading project details...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!project) return <div>Project not found</div>;

  const projHealth = project ? computeProjectHealth(project) : null;
  const quoteValueLabel = `R${Number(project.soldValue || 0).toLocaleString('en-ZA', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
  const customerLabel = customerNameById.get(Number(project.customerId)) || `Customer #${project.customerId}`;
  const selectedTicket = orderedTicketRows.find((ticket) => Number(ticket.id) === selectedTicketId) || null;
  const selectedTicketEstimateMinutes = Number(selectedTicket?.estimatedMinutes ?? selectedTicket?.soldMinutes ?? 0);
  const selectedTicketEstimateLabel = selectedTicketEstimateMinutes > 0
    ? `${(selectedTicketEstimateMinutes / 60).toFixed(1)}h`
    : '—';
  const selectedTicketAssigneeLabel = selectedTicket?.assignedUserId
    ? employeeNameById.get(Number(selectedTicket.assignedUserId)) || `User #${selectedTicket.assignedUserId}`
    : 'Unassigned';
  const handleOpenTicketDiscussion = (ticket: Ticket) => {
    openConversation({
      contextType: 'ticket',
      contextId: String(ticket.id),
      subject: `Ticket #${ticket.id}: ${ticket.subject}`,
      subjectKey: `ticket:${ticket.id}`,
    });
  };
  const handleOpenFullTicket = (ticket: Ticket) => {
    const nextUrl = new URL(window.location.href);
    nextUrl.search = '?page=pet-support';
    nextUrl.hash = `ticket=${ticket.id}`;
    window.location.assign(nextUrl.toString());
  };

  return (
    <div className={`pet-project-details ${projHealth?.className || ''}`}>
      {!embedded && (
        <div style={{ marginBottom: '20px' }}>
          <button className="button" onClick={() => onBack?.()}>&larr; Back to Projects</button>
          {project && (
            <button 
              className="button" 
              onClick={() => openConversation({
                contextType: 'project',
                contextId: String(project.id),
                subject: `Project: ${project.name}`,
                subjectKey: `project:${project.id}`,
              })}
              style={{ marginLeft: '10px' }}
            >
              Discuss
            </button>
          )}
        </div>
      )}

      {!embedded && (
        <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#fff', border: '1px solid #ccd0d4' }}>
          <h2>
            {project.name}
            {projHealth && projHealth.reasons.map((r, i) => (
              <span key={i} className={`uhb-tag uhb-tag-${r.color}`}>{r.label}</span>
            ))}
          </h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
            <div>
              <p><strong>Customer:</strong> {customerLabel}</p>
              <p><strong>Source Quote:</strong> {project.sourceQuoteId ? `#${project.sourceQuoteId}` : '-'}</p>
              <p><strong>Total Sold Hours:</strong> {project.soldHours}</p>
            </div>
            <div>
              <p><strong>Total Tickets:</strong> {orderedTicketRows.length}</p>
              <p><strong>Estimated Hours (Sum):</strong> {(orderedTicketRows.reduce((sum, ticket) => sum + Number(ticket.estimatedMinutes || 0), 0) / 60).toFixed(2)}</p>
            </div>
          </div>
        </div>
      )}

      {!embedded && project.sourceQuoteId && (
        <div className="card" style={{ marginBottom: '14px', padding: '12px 16px', background: '#eef4ff', border: '1px solid #bfd6ff' }}>
          <strong>From Quote #{project.sourceQuoteId} · {quoteValueLabel}</strong>
        </div>
      )}
      {!embedded && <h3>Project Tickets</h3>}
      <div className={`pet-project-ticket-workspace${selectedTicket ? ' pet-project-ticket-workspace--drawer-open' : ''}`}>
        <div className={`pet-project-details-ticket-table${embedded ? ' pet-project-details-ticket-table--embedded' : ''}`}>
          <DataTable 
            columns={ticketColumns} 
            data={orderedTicketRows} 
            emptyMessage="No tickets yet."
            rowClassName={(ticket) => Number(ticket.id) === selectedTicketId ? 'pet-project-ticket-row--selected' : ''}
            onRowClick={(ticket) => setSelectedTicketId(Number(ticket.id))}
          />
        </div>

        {selectedTicket && (
          <aside className="pet-project-ticket-drawer" role="complementary" aria-label={`Ticket #${selectedTicket.id} details`}>
            <div className="pet-project-ticket-drawer-header">
              <div className="pet-project-ticket-drawer-title-wrap">
                <h4 className="pet-project-ticket-drawer-title">{selectedTicket.subject}</h4>
                <div className="pet-project-ticket-drawer-meta">
                  #{selectedTicket.id}
                  {selectedTicket.ticketKind ? ` · ${selectedTicket.ticketKind}` : ''}
                  {selectedTicket.lifecycleOwner ? ` · ${selectedTicket.lifecycleOwner}` : ''}
                </div>
              </div>
              <button
                type="button"
                className="button pet-project-ticket-drawer-close"
                onClick={() => setSelectedTicketId(null)}
                aria-label="Close ticket details"
              >
                ×
              </button>
            </div>

            <div className="pet-project-ticket-drawer-badges">
              <span className={`pet-status-badge status-${String(selectedTicket.status).toLowerCase()}`}>{String(selectedTicket.status)}</span>
              <span className={`pet-priority-badge priority-${String(selectedTicket.priority).toLowerCase()}`}>{String(selectedTicket.priority)}</span>
              <span className="pd-badge">{`Estimate ${selectedTicketEstimateLabel}`}</span>
            </div>

            <div className="pet-project-ticket-drawer-section">
              <h5>Description</h5>
              <div className="pet-project-ticket-drawer-description">
                {selectedTicket.description ? selectedTicket.description : 'No description provided.'}
              </div>
            </div>

            <div className="pet-project-ticket-drawer-section">
              <h5>Context</h5>
              <div className="pet-project-ticket-drawer-context-grid">
                <div><strong>Project:</strong> {project.name}</div>
                <div><strong>Customer:</strong> {customerLabel}</div>
                <div><strong>Assignment:</strong> {selectedTicketAssigneeLabel}</div>
                <div><strong>Created:</strong> {selectedTicket.createdAt || '—'}</div>
              </div>
            </div>

            <div className="pet-project-ticket-drawer-actions">
              <button
                type="button"
                className="button button-primary"
                onClick={() => handleOpenTicketDiscussion(selectedTicket)}
              >
                Discuss
              </button>
              <button
                type="button"
                className="button"
                onClick={() => handleOpenFullTicket(selectedTicket)}
              >
                Open Full Ticket
              </button>
            </div>
          </aside>
        )}
      </div>
      {embedded && <div id={`project-${project.id}-tickets`} style={{ height: 1 }} />}
    </div>
  );
};

export default ProjectDetails;
